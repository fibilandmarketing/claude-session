import json,sys
a=json.load(open(sys.argv[1])); b=json.load(open(sys.argv[2])); diffs=0
for x,y in zip(a['hits'],b['hits']):
    for k in ('status','body','cookies'):
        if x[k]!=y[k]: diffs+=1; print('HIT DIFF',x['scenario'],x['hit'],k,'\n  A:',json.dumps(x[k],ensure_ascii=False)[:300],'\n  B:',json.dumps(y[k],ensure_ascii=False)[:300])
if len(a['rows'])!=len(b['rows']): print('ROW COUNT', len(a['rows']), len(b['rows'])); diffs+=1
for i,(x,y) in enumerate(zip(a['rows'],b['rows'])):
    if x!=y: diffs+=1; print('ROW DIFF',i,'\n  A:',x,'\n  B:',y)
print(sys.argv[1].split('/')[-1],'vs',sys.argv[2].split('/')[-1],'=> differences:',diffs, '| rows', len(a['rows']), '| hits', len(a['hits']))
