#!/usr/bin/env python3
"""Mixed load: each virtual user = GET page then POST tracker hit (like a browser).
usage: load.py <beacon: none|ajax|rest|URL> <users> <seconds>"""
import sys, time, uuid, random, statistics, threading, requests, json
BASE='http://localhost:8080'
UA='Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36'
beacon, users, secs = sys.argv[1], int(sys.argv[2]), float(sys.argv[3])
PAGES=['/','/shop/']+['/product/%d9%85%d8%ad%d8%b5%d9%88%d9%84-%d8%aa%d8%b3%d8%aa-'+str(i)+'/' for i in range(1,20)]
REFS=['https://www.google.com/','https://instagram.com/','','https://t.me/x','https://torob.com/p/1']
page_lat, hit_lat, errors = [], [], [0]
stop = time.time()+secs
def worker(seed):
    rnd=random.Random(seed); s=None
    while time.time()<stop:
        if s is None or rnd.random()<0.6:
            s=requests.Session(); s.headers['User-Agent']=UA; vid=str(uuid.uuid4()); sid=str(uuid.uuid4()); new='1'
        else: new='0'
        p=rnd.choice(PAGES); t=time.perf_counter(); r=s.get(BASE+p); page_lat.append((time.perf_counter()-t)*1000)
        if r.status_code!=200: errors[0]+=1
        if beacon=='none': continue
        d={'page_url':BASE+p,'page_type':'page','object_id':'0','referrer':rnd.choice(REFS) if new=='1' else BASE+'/','visitor_id':vid,'session_id':sid,'new_session':new,'transport':'head_beacon'}
        t=time.perf_counter()
        if beacon=='ajax': d['action']='mm_record_visit'; r=s.post(BASE+'/wp-admin/admin-ajax.php', data=d)
        elif beacon=='rest': r=s.post(BASE+'/wp-json/monitor-man/v1/visit', json=d)
        else: r=s.post(beacon, data=d)
        hit_lat.append((time.perf_counter()-t)*1000)
        if r.status_code not in (200,204): errors[0]+=1
th=[threading.Thread(target=worker,args=(i,)) for i in range(users)]
[t.start() for t in th]; [t.join() for t in th]
q=lambda a,p: round(sorted(a)[max(0,int(len(a)*p)-1)],1) if a else None
print(json.dumps({'beacon':beacon if len(beacon)<20 else 'collector','users':users,'pages':len(page_lat),'pages_per_s':round(len(page_lat)/secs,1),
 'page_p50':q(page_lat,.5),'page_p95':q(page_lat,.95),'hit_p50':q(hit_lat,.5),'hit_p95':q(hit_lat,.95),'errors':errors[0]}))
