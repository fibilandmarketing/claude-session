#!/usr/bin/env python3
"""Fire each admin report request at the original (8082) and optimised (8081)
instance at the same moment (same DB) and compare the JSON, ignoring list order
and timestamps. Retries a mismatch twice in case the clock crossed a second."""
import json, sys, requests, itertools, threading
A=json.load(open('/tmp/claude-0/auth.json'))
ORIG='http://localhost:8082/wp-admin/admin-ajax.php'; NEW='http://localhost:8081/wp-admin/admin-ajax.php'
VOL={'generated_at','scan','native_now','wp_date_now','plugin','freshness_minutes','tracker_freshness_minutes','wpdb_last_error','scanned_at','finished_at','started_at'}
def canon(o):
    if isinstance(o,dict): return {k:canon(v) for k,v in o.items() if k not in VOL}
    if isinstance(o,list): return sorted((canon(x) for x in o), key=lambda x: json.dumps(x,sort_keys=True,ensure_ascii=False))
    return o
def call(url, action, kw):
    d=[('action',action),('nonce',A['nonce'])]
    for k,v in kw.items(): d += [(k+'[]',x) for x in v] if isinstance(v,list) else [(k,v)]
    r=requests.post(url,data=d,headers={'Cookie':A['cookie']},timeout=300)
    try: return r.status_code, r.json()
    except Exception: return r.status_code, r.text[:200]
def pair(action, kw):
    res={}
    ts=[threading.Thread(target=lambda u=u: res.__setitem__(u, call(u, action, kw))) for u in (ORIG, NEW)]
    [t.start() for t in ts]; [t.join() for t in ts]
    return res[ORIG], res[NEW]
ranges=[{'range':'24h'},{'range':'7d'},{'range':'10d'},{'range':'30d'},{'range':'custom','from':'۱۴۰۵/۰۵/۱۰','to':'۱۴۰۵/۰۸/۰۵'}]
filters=[{}, {'statuses':['completed','processing']}, {'statuses':['completed','processing'],'sources':['google','instagram','direct']}]
jobs=[]
for r,f in itertools.product(ranges,filters):
    jobs += [('mm_dashboard_data',{**r,**f}),('mm_comparison_data',{**r,**f}),
             ('mm_products_data',{**r,**f,'page':'1','per_page':'all','sort':'revenue','order':'desc'}),
             ('mm_product_sources',{**r,**f,'product_id':'15'})]
jobs.append(('mm_logs',{}))
ok=0; bad=[]
for action,kw in jobs:
    for attempt in range(3):
        (sa,a),(sb,b)=pair(action,kw)
        if action=='mm_logs' and isinstance(a,dict) and isinstance(b,dict):
            a=a['data']['diagnostics']['report']; b=b['data']['diagnostics']['report']   # log rows/env differ by design
        same = sa==sb==200 and json.dumps(canon(a),sort_keys=True)==json.dumps(canon(b),sort_keys=True)
        if same: break
    if same: ok+=1
    else: bad.append((action,kw,sa,sb))
    print(('OK  ' if same else 'DIFF'), action, json.dumps(kw,ensure_ascii=False)[:90], flush=True)
print(f'IDENTICAL {ok}/{len(jobs)}'); [print('DIFF',x) for x in bad]
