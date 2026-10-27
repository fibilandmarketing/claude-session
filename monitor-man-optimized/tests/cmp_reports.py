import json,sys
VOLATILE={'generated_at','scan','native_now','wp_date_now','plugin','freshness_minutes','tracker_freshness_minutes','wpdb_last_error','logs','scanned_at','finished_at','started_at'}
def strip(o):
    if isinstance(o,dict): return {k:strip(v) for k,v in o.items() if k not in VOLATILE}
    if isinstance(o,list): return [strip(x) for x in o]
    return o
a=json.load(open(sys.argv[1])); b=json.load(open(sys.argv[2])); bad=0
def walk(x,y,path):
    global bad
    if type(x)!=type(y): bad+=1; print('TYPE',path,str(x)[:80],str(y)[:80]); return
    if isinstance(x,dict):
        for k in set(x)|set(y):
            if k not in x or k not in y: bad+=1; print('KEY',path+'.'+k); continue
            walk(x[k],y[k],path+'.'+k)
    elif isinstance(x,list):
        if len(x)!=len(y): bad+=1; print('LEN',path,len(x),len(y)); return
        for i,(p,q) in enumerate(zip(x,y)): walk(p,q,f'{path}[{i}]')
    elif x!=y: bad+=1; print('VAL',path,repr(x)[:80],'!=',repr(y)[:80])
for k in a: walk(strip(a[k]),strip(b[k]),k[:60])
print('endpoints compared:',len(a),'| differing values:',bad)
