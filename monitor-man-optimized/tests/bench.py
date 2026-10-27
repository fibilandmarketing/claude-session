#!/usr/bin/env python3
"""Latency/throughput bench. usage: bench.py <mode> <n> <concurrency> [url]
modes: page:<path>  ajax  rest  collector:<url>"""
import sys, time, uuid, statistics, requests, json
from concurrent.futures import ThreadPoolExecutor
BASE='http://localhost:8080'
UA='Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36'
mode, n, c = sys.argv[1], int(sys.argv[2]), int(sys.argv[3])
REFS=['https://www.google.com/','https://instagram.com/','','https://t.me/x','https://torob.com/p/1','http://localhost:8080/shop/']
def payload(i):
    return {'page_url': f'{BASE}/shop/?i={i}', 'page_type':'page','object_id':'0','referrer':REFS[i%len(REFS)],
            'visitor_id':str(uuid.uuid4()),'session_id':str(uuid.uuid4()),'new_session':'1','transport':'head_beacon'}
def one(i):
    s=requests.Session(); s.headers['User-Agent']=UA
    t=time.perf_counter()
    if mode.startswith('page:'):
        r=s.get(BASE+mode[5:])
    elif mode=='ajax':
        d=payload(i); d['action']='mm_record_visit'; r=s.post(BASE+'/wp-admin/admin-ajax.php', data=d)
    elif mode=='rest':
        r=s.post(BASE+'/wp-json/monitor-man/v1/visit', json=payload(i))
    elif mode.startswith('collector:'):
        r=s.post(mode[10:], data=payload(i))
    dt=(time.perf_counter()-t)*1000
    return dt, r.status_code, len(r.content), r.headers.get('Set-Cookie','')
t0=time.perf_counter()
with ThreadPoolExecutor(c) as ex: res=list(ex.map(one, range(n)))
wall=time.perf_counter()-t0
lat=sorted(r[0] for r in res); codes={}
for r in res: codes[r[1]]=codes.get(r[1],0)+1
print(json.dumps({'mode':mode,'n':n,'c':c,'p50_ms':round(statistics.median(lat),1),'p95_ms':round(lat[int(len(lat)*.95)-1],1),
 'mean_ms':round(statistics.mean(lat),1),'rps':round(n/wall,1),'codes':codes,'bytes':res[0][2],'set_cookie':bool(res[0][3])}, ensure_ascii=False))
