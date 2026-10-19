import csv, re, sys, math, random, collections, json
D="/tmp/unified_dl/Unified-Bangla/Unified Bangla Dataset/"
MAP={'ড়':'ড়','ঢ়':'ঢ়','য়':'য়'}
def norm(s):
    s=s.replace('‍','').replace('‌','')
    for a,b in MAP.items(): s=s.replace(a,b)
    s=re.sub(r'[?!,.;:।"\'()\[\]‘’“”—\-]+',' ',s)
    return re.sub(r'\s+',' ',s).strip()
COLS={'standard':'bangla_speech','Barishal':'barishal_bangla_speech','Chattogram':'chittagong_bangla_speech','Mymensingh':'mymensingh_bangla_speech','Noakhali':'noakhali_bangla_speech','Sylheti':'sylhet_bangla_speech'}
def load(f):
    rows=[]
    with open(D+f,encoding='utf-8-sig') as fh:
        for r in csv.DictReader(fh):
            rows.append({k:norm(r[c] or '') for k,c in COLS.items()})
    return rows
train=load('Unified_train_dataset.csv'); val=load('Unified_validation_dataset.csv'); test=load('Unified_test_dataset.csv')
def lev(a,b):
    if a==b: return 0
    p=list(range(len(b)+1))
    for i,x in enumerate(a,1):
        c=[i]
        for j,y in enumerate(b,1): c.append(min(p[j]+1,c[j-1]+1,p[j-1]+(x!=y)))
        p=c
    return p[-1]
def sim(a,b):
    m=max(len(a),len(b)); return 1-lev(a,b)/m if m else 1
def model1(pairs,iters=10):
    # pairs: list of (src_tokens, tgt_tokens); learn t(tgt|src)
    t=collections.defaultdict(dict)
    for s,g in pairs:
        for a in s+['<N>']:
            for b in g:
                if b not in t[a]: t[a][b]=(1+6*sim(a,b)**2) if a!='<N>' else 0.5
    for a in t:
        z=sum(t[a].values())
        for b in t[a]: t[a][b]/=z
    for _ in range(iters):
        cnt=collections.defaultdict(lambda: collections.defaultdict(float)); tot=collections.defaultdict(float)
        for s,g in pairs:
            ss=s+['<N>']
            for b in g:
                z=sum(t[a][b] for a in ss)
                for a in ss:
                    c=t[a][b]/z; cnt[a][b]+=c; tot[a]+=c
        for a in cnt:
            for b in cnt[a]: t[a][b]=cnt[a][b]/tot[a]
    return t
def pairs_for(rows,d,rev=False):
    out=[]
    for r in rows:
        a=r[d].split(); b=r['standard'].split()
        if a and b: out.append((b,a) if rev else (a,b))
    return out
def translate(t,toks,minp=0.0):
    out=[]
    for w in toks:
        c=t.get(w)
        if not c: out.append(w); continue
        b,p=max(c.items(),key=lambda x:x[1])
        out.append(b if p>=minp else w)
    return out
def wer(h,r):
    return lev(h,r)/max(1,len(r))
def evaluate(trainrows,testrows,dialects):
    res={}
    for d in dialects:
        t=model1(pairs_for(trainrows,d))
        base=[];mod=[]
        for r in testrows:
            if not r[d] or not r['standard']: continue
            ref=r['standard'].split(); src=r[d].split()
            base.append(wer(src,ref)); mod.append(wer(translate(t,src),ref))
        res[d]=(sum(base)/len(base),sum(mod)/len(mod),len(base))
    return res
DIAL=['Barishal','Chattogram','Mymensingh','Noakhali','Sylheti']
if __name__=='__main__':
    print('rows',len(train),len(val),len(test))
    ev=evaluate(train+val,test,DIAL)
    for d,v in ev.items(): print(d,'WER copy=%.3f model=%.3f n=%d'%v)
