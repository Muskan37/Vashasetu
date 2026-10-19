from build import *
import math
V=['standard']+DIAL
def fit(rows):
    c={k:collections.Counter() for k in V}
    for r in rows:
        for k in V: c[k].update(r[k].split())
    return c
def predict(c,sent,alpha=0.1,usechar=True):
    toks=sent.split(); allv=set().union(*[set(x) for x in c.values()])
    sc={}
    for k in V:
        N=sum(c[k].values()); s=0
        for w in toks:
            if w not in allv: continue
            s+=math.log((c[k][w]+alpha)/(N+alpha*len(allv)))
        sc[k]=s
    return sc
c=fit(train+val)
for alpha in (0.02,0.1,0.5):
    ok=tot=0; byl=collections.Counter(); byt=collections.Counter()
    for r in test:
        for k in V:
            if not r[k]: continue
            sc=predict(c,r[k],alpha); p=max(sc,key=sc.get)
            tot+=1; byt[k]+=1
            if p==k: ok+=1; byl[k]+=1
    print(alpha,'acc %.3f'%(ok/tot),{k:round(byl[k]/byt[k],2) for k in V})
