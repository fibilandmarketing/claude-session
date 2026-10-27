#!/usr/bin/env python3
"""Replay a fixed set of tracker scenarios against one transport and dump
normalised responses, cookies and stored rows.  usage: parity_hits.py <mode> <tag> <out.json>
mode: orig_ajax | collector | ajax | rest"""
import sys, json, base64, uuid, requests, subprocess, re
BASE='http://localhost:8080'
UA='Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36'
mode, tag, out = sys.argv[1], sys.argv[2], sys.argv[3]
AUTH=json.load(open('/tmp/claude-0/auth.json'))['cookie']
ids={}
def U(name):  # deterministic per (tag,name) uuid, normalised later
    if name not in ids: ids[name]=str(uuid.uuid5(uuid.NAMESPACE_URL, tag+'/'+name))
    return ids[name]
P=lambda p: BASE+p
PROD='/product/%d9%85%d8%ad%d8%b5%d9%88%d9%84-%d8%aa%d8%b3%d8%aa-3/'
# (scenario, [ (page, page_type, object_id, referrer, new_session, extra) ])
S=[
 ('google_then_internal', [('/', 'page',0,'https://www.google.com/search?q=x','1',{}), ('/shop/','page',0,P('/'),'0',{}), (PROD,'product',13,P('/shop/'),'0',{})]),
 ('utm_instagram', [('/?utm_source=instagram&utm_medium=social&utm_campaign=fall','page',0,'','1',{}), ('/cart/','cart',0,P('/'),'0',{})]),
 ('gclid', [('/shop/?gclid=abc123','page',0,'','1',{})]),
 ('fbclid', [('/?fbclid=zz','page',0,'https://l.facebook.com/','1',{})]),
 ('torob', [(PROD+'?torob_clid=t1','product',13,'https://torob.com/p/1/','1',{})]),
 ('direct_then_internal', [('/','page',0,'','1',{}), ('/checkout/','checkout',0,P('/cart/'),'0',{})]),
 ('referral', [('/shop/','page',0,'https://example.org/blog/post','1',{})]),
 ('gateway_return', [('/','page',0,'https://www.google.com/','1',{}), ('/checkout/order-received/55/?key=wc_order_x','page',0,'https://www.zarinpal.com/pg/StartPay','1',{})]),
 ('session_continue_direct', [('/','page',0,'https://t.me/channel','1',{}), ('/shop/','page',0,'','0',{})]),
 ('invalid_url', [('https://evil.example.com/','page',0,'','1',{})]),
 ('bot', [('/','page',0,'','1',{'ua':'Googlebot/2.1 (+http://www.google.com/bot.html)'})]),
 ('custom_source', [('/','page',0,'https://myblog.ir/post/1','1',{})]),
 ('persian_url', [('/%d8%aa%d8%b3%d8%aa/?q=%d8%b3%d9%84%d8%a7%d9%85','page',0,'https://www.bing.com/','1',{})]),
 ('duplicate', [('/shop/','page',0,'https://www.google.com/','1',{}), ('/shop/','page',0,'https://www.google.com/','1',{})]),
 ('cross_origin', [('/','page',0,'','1',{'origin':'https://evil.example.com'})]),
 ('logged_in', [('/','page',0,'https://www.google.com/','1',{'auth':True}), ('/shop/','page',0,P('/'),'0',{'auth':True})]),
 ('utm_then_internal', [('/?utm_source=instagram&utm_campaign=x','page',0,'','1',{}), ('/shop/','page',0,P('/?utm_source=instagram&utm_campaign=x'),'0',{}), ('/shop/?utm_source=telegram','page',0,P('/shop/'),'1',{})]),
 ('whatsapp_quote', [("/shop/?utm_source=whatsapp&utm_campaign=it's",'page',0,'','1',{})]),
]
def decode_cookie(v):
    try:
        payload=v.split('.')[0]; payload+= '='*(-len(payload)%4)
        d=json.loads(base64.urlsafe_b64decode(payload))
        for k in ('updated_at','captured_at'): d.pop(k,None)
        if isinstance(d.get('source'),dict): d['source'].pop('captured_at',None)
        return d
    except Exception: return v
results=[]
for name, hits in S:
    s=requests.Session(); s.headers['User-Agent']=UA
    vid=U(name+'/v'); sid=U(name+'/s')
    for i,(page,ptype,oid,ref,new,extra) in enumerate(hits):
        if extra.get('ua'): s.headers['User-Agent']=extra['ua']
        if extra.get('auth'): s.headers['Cookie']=AUTH
        h={}
        if extra.get('origin'): h['Origin']=extra['origin']
        d={'page_url': page if page.startswith('http') else P(page),'page_type':ptype,'object_id':str(oid),'referrer':ref,'visitor_id':vid,'session_id':sid,'new_session':new}
        if mode=='orig_ajax' or mode=='ajax':
            d['action']='mm_record_visit'; d['transport']='head_beacon'; r=s.post(P('/wp-admin/admin-ajax.php'),data=d,headers=h)
        elif mode=='collector':
            d['transport']='head_beacon'; r=s.post(P('/wp-content/plugins/monitor-man/collect.php'),data=d,headers=h)
        elif mode=='rest':
            d['transport']='head_beacon'; r=s.post(P('/wp-json/monitor-man/v1/visit'),json=d,headers=h)
        try: body=r.json()
        except Exception: body=r.text[:200]
        if isinstance(body,dict):
            inner = body.get('data',body) if 'success' in body else body
            if isinstance(inner,dict): inner=dict(inner); inner.pop('session',None)
            body={'ok': body.get('success', r.ok), 'data': inner}
        cookies={c.name: (decode_cookie(c.value) if '.' in c.value else ('<uuid>' if re.match(r'^[0-9a-f-]{36}$',c.value) else c.value)) for c in r.cookies}
        cookies.pop('mm_live_refresh_at',None)
        results.append({'scenario':name,'hit':i,'status':r.status_code,'body':body,'cookies':cookies})
# stored rows
sess=[U(n+'/s') for n,_ in S]
q="SELECT session_id,visitor_id,source_key,source_label,medium,campaign,referrer_host,landing_url,page_url,page_type,object_id,user_id,is_entry,transport FROM wp_mm_visits WHERE session_id IN (%s) ORDER BY id" % ",".join("'%s'"%x for x in sess)
rows=subprocess.run(['mysql','-uroot','--default-character-set=utf8mb4','-B','wp','-e',q],capture_output=True,text=True).stdout.splitlines()
inv={v:k.replace(tag,'') for k,v in ids.items()}
norm=[]
for line in rows[1:]:
    f=line.split('\t'); f[0]=inv.get(f[0],f[0]); f[1]=inv.get(f[1],f[1]); norm.append(f)
cookies_norm=json.loads(json.dumps(results).replace(tag,''))
for r in cookies_norm:
    for k,v in r['cookies'].items():
        if isinstance(v,dict) and 'session_id' in v: v['session_id']=inv.get(v['session_id'],v['session_id'])
json.dump({'hits':cookies_norm,'rows':[r[:13] for r in norm],'transport':[r[13] for r in norm]},open(out,'w'),ensure_ascii=False,indent=1)
print(mode, 'hits',len(results),'rows',len(norm))
