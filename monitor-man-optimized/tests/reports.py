#!/usr/bin/env python3
"""Call every admin report endpoint with many filter combinations; dump JSON.
usage: reports.py <out.json>"""
import json, sys, requests, itertools
A=json.load(open('/tmp/claude-0/auth.json')); U='http://localhost:8081/wp-admin/admin-ajax.php'
s=requests.Session(); s.headers['Cookie']=A['cookie']
def call(action, **kw):
    d=[('action',action),('nonce',A['nonce'])]
    for k,v in kw.items():
        if isinstance(v,list): d+= [(k+'[]',x) for x in v]
        else: d.append((k,v))
    r=s.post(U,data=d); 
    try: return r.status_code, r.json()
    except Exception: return r.status_code, r.text[:300]
ranges=[{'range':'24h'},{'range':'7d'},{'range':'30d'},{'range':'custom','from':'۱۴۰۵/۰۶/۱۰','to':'۱۴۰۵/۰۸/۰۵'}]
filters=[{}, {'statuses':['completed','processing'],'sources':['google','instagram']}]
out={}
for r,f in itertools.product(ranges,filters):
    key=json.dumps({**r,**f},ensure_ascii=False)
    out['dashboard '+key]=call('mm_dashboard_data',**r,**f)
    out['comparison '+key]=call('mm_comparison_data',**r,**f)
    for sort in ('revenue',):
        out[f'products {sort} '+key]=call('mm_products_data',**r,**f,page='1',per_page='all',sort=sort,order='desc')
    for pid in ('15',):
        out[f'product_sources {pid} '+key]=call('mm_product_sources',**r,**f,product_id=pid)
out['logs']=call('mm_logs')
json.dump(out,open(sys.argv[1],'w'),ensure_ascii=False)
print(len(out),'responses', sum(1 for v in out.values() if v[0]!=200),'non-200')
