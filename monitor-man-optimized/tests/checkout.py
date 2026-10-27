#!/usr/bin/env python3
"""Real checkout through the WooCommerce Store API after a tracked landing.
usage: checkout.py <hit: ajax|collector> <n> <out.json>"""
import sys, json, time, uuid, requests, subprocess
B='http://localhost:8080'; O=sys.argv[4] if len(sys.argv)>4 else 'http://localhost:8081'
UA='Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36'
mode, n, out = sys.argv[1], int(sys.argv[2]), sys.argv[3]
REFS=['https://www.google.com/','https://instagram.com/','https://torob.com/p/9/','']
res=[]
for i in range(n):
    s=requests.Session(); s.headers['User-Agent']=UA
    d={'page_url':B+'/shop/','page_type':'page','object_id':'0','referrer':REFS[i%4],'visitor_id':str(uuid.uuid4()),'session_id':str(uuid.uuid4()),'new_session':'1','transport':'head_beacon'}
    if mode=='ajax': d['action']='mm_record_visit'; s.post(O+'/wp-admin/admin-ajax.php',data=d)
    else: s.post(O+'/wp-content/plugins/monitor-man/collect.php',data=d)
    r=s.get(O+'/wp-json/wc/store/v1/cart'); nonce=r.headers.get('Nonce')
    s.headers['Nonce']=nonce
    s.post(O+'/wp-json/wc/store/v1/cart/add-item',json={'id':11+(i%10),'quantity':1+(i%2)})
    addr={'first_name':'Ali','last_name':'Test','address_1':'Street 1','city':'Tehran','state':'THR','postcode':'1234567890','country':'IR','email':f'u{i}@example.com','phone':'09120000000'}
    t=time.perf_counter()
    r=s.post(O+'/wp-json/wc/store/v1/checkout',json={'billing_address':addr,'shipping_address':addr,'payment_method':'cod'})
    dt=(time.perf_counter()-t)*1000
    j=r.json(); oid=j.get('order_id')
    q=f"SELECT order_id,status,gross_total,payment_method,source_key,source_label,attribution_method,confidence FROM wp_mm_order_facts WHERE order_id={int(oid or 0)}; SELECT COUNT(*) FROM wp_mm_order_items WHERE order_id={int(oid or 0)}"
    row=subprocess.run(['mysql','-uroot','--default-character-set=utf8mb4','-N','-B','wp','-e',q],capture_output=True,text=True).stdout.strip().split('\n')
    res.append({'i':i,'http':r.status_code,'order_id':oid,'status':j.get('status') or j.get('code'),'ms':round(dt,1),'fact':row[0].split('\t')[1:] if row and row[0] else None,'items':row[-1] if len(row)>1 else None})
json.dump(res,open(out,'w'),ensure_ascii=False,indent=0)
ms=sorted(r['ms'] for r in res); print(mode,'checkout p50 %.0fms'%ms[len(ms)//2], 'orders',sum(1 for r in res if r['order_id']), 'facts_present',sum(1 for r in res if r['fact']))
for r in res[:4]: print(' ',r)
