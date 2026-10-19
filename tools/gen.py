import sys; sys.path.insert(0,'.')
from build import *
import csv
allrows=train+val+test
seen=set(); rows=[]
for r in allrows:
    k=r['standard']
    if k and k not in seen: seen.add(k); rows.append(r)
print('dedup rows',len(rows))
# --- extra open datasets (see README.txt) ---
import os, glob, extra_sources as X
if 'Rajshahi' not in DIAL: DIAL=DIAL+['Rajshahi']
for r in rows: r.setdefault('Rajshahi','')
extra=[]; have=set()
for r in rows:
    for d in DIAL:
        if r.get(d): have.add((d,r['standard'],r[d]))
def add_extra(src,std,kind,**dl):
    row={k:'' for k in rows[0]}; row['standard']=std; ok=False
    for d,v in dl.items():
        if (d,std,v) in have: continue
        if len(std.split())>30 or len(v.split())>30: continue
        have.add((d,std,v)); row[d]=v; ok=True
    if ok: row['source']=src; extra.append(row)
EXTRA='/tmp/claude-0/work/data/bangla-chittagonian-translation/data/raw/dataset.csv'
if os.path.exists(EXTRA):
    for r in list(csv.reader(open(EXTRA,encoding='utf-8-sig')))[1:]:
        if len(r)>=4:
            st,ch=norm(r[2]),norm(r[3])
            if st and ch: add_extra('Safin-2025',st,'sent',Chattogram=ch)
for r in X.load_all():
    add_extra(r['src'],r['standard'],r['kind'],**{d:r[d] for d in DIAL if d in r})
print('extra rows',len(extra), collections.Counter(r['source'] for r in extra))
for r in rows: r.setdefault('source','Vashantor')
rows=rows+extra

OUT='/home/claude/vashasetu_app/database/'
with open(OUT+'corpus.csv','w',encoding='utf-8',newline='') as f:
    w=csv.writer(f); w.writerow(['standard']+DIAL+['source'])
    for i,r in enumerate(rows): w.writerow([r['standard']]+[r[d] for d in DIAL]+[r.get('source','Vashantor')])
lex=[]
for d in DIAL:
    f=model1(pairs_for(rows,d)); g=model1(pairs_for(rows,d,rev=True))
    cnt=collections.Counter(w for r in rows for w in r[d].split())
    scnt=collections.Counter(w for r in rows for w in r['standard'].split())
    pairs={}
    for a,c in f.items():
        if a=='<N>': continue
        top=sorted(c.items(),key=lambda x:-x[1])[:2]
        for rank,(b,p) in enumerate(top):
            if p>=0.25 and (rank==0 or p>=0.3): pairs[(a,b)]=[p,0,rank]
    for s,c in g.items():
        if s=='<N>': continue
        for b,p in sorted(c.items(),key=lambda x:-x[1])[:2]:
            pass
    # reverse: g maps standard word s -> dialect word a
    for s,c in g.items():
        if s=='<N>': continue
        top=sorted(c.items(),key=lambda x:-x[1])[:2]
        for rank,(a,p) in enumerate(top):
            if p>=0.25:
                e=pairs.setdefault((a,s),[0,0,9]); e[1]=p
    for (a,b),(p,q,rank) in pairs.items():
        lex.append([d,a,b,round(p,3),round(q,3),cnt[a],0])
# verified Wikivoyage (Chittagonian phrasebook) pairs
ver=[('আঁই','আমি'),('তুঁই','তুমি'),('আঁর','আমার'),('তোঁয়ার','তোমার'),('কডে','কোথায়'),('হন্ডে','কোথায়'),('কেন','কেমন')]
lexd={(l[0],l[1],l[2]):l for l in lex}
for a,b in ver:
    k=('Chattogram',a,b)
    if k in lexd: lexd[k][3]=max(lexd[k][3],0.99); lexd[k][4]=max(lexd[k][4],0.9); lexd[k][6]=1
    else: lex.append(['Chattogram',a,b,0.99,0.9,1,1])
prev=OUT+'lexicon.csv'
if os.path.exists(prev):
    old=[r for r in csv.reader(open(prev,encoding='utf-8'))][1:]
    lexd={(l[0],l[1],l[2]):l for l in lex}
    kept=0
    for r in old:
        if len(r)>=7 and r[6]=='1':
            k=(r[0],r[1],r[2]); row=[r[0],r[1],r[2],float(r[3]),float(r[4]),int(r[5]),1]
            if k in lexd: lexd[k][3]=max(lexd[k][3],row[3]); lexd[k][4]=max(lexd[k][4],row[4]); lexd[k][6]=1
            else: lex.append(row)
            kept+=1
    print('kept verified rows',kept)
with open(OUT+'lexicon.csv','w',encoding='utf-8',newline='') as f:
    w=csv.writer(f); w.writerow(['dialect','dialect_word','standard_word','p_to_std','p_to_dialect','count','verified'])
    w.writerows(lex)
print('lexicon',len(lex), collections.Counter(l[0] for l in lex))
ch=sum(1 for l in lex if l[1]!=l[2]); print('non-identical',ch)
# vocab frequencies per variety
voc=collections.defaultdict(collections.Counter)
for r in rows:
    for k in ['standard']+DIAL:
        for w_ in r[k].split(): voc[k][w_]+=1
# BanglaDial (dialect-labelled text, no Standard pair): Rajshahi sentences feed detection vocabulary only
_bd=glob.glob('/tmp/claude-0/work/mend2/bdial/*/*.csv')
if _bd:
    for r in list(csv.reader(open(_bd[0],encoding='utf-8-sig')))[1:]:
        if len(r)>=2 and r[1]=='Rajshahi':
            for w_ in norm(r[0]).split(): voc['Rajshahi'][w_]+=1
with open(OUT+'vocab.csv','w',encoding='utf-8',newline='') as f:
    w=csv.writer(f); w.writerow(['variety','word','count'])
    for k,c in voc.items():
        for w_,n in c.items(): w.writerow([k,w_,n])
print({k:len(v) for k,v in voc.items()})
