#!/usr/bin/env python3
"""Word candidates for Chakma/Marma/Garo from MELD sentence pairs (co-occurrence, mutual best, Dice).
Marked needs_native_review. Short 1-2 word rows are used directly."""
import csv, hashlib, re, collections, pathlib
ROOT=pathlib.Path(__file__).resolve().parent.parent/'database'/'languages'
SD='MELD (word candidates from sentence pairs)'
for lang in ('chakma','marma','garo'):
    rows=list(csv.DictReader(open(ROOT/lang/'sentences.csv',encoding='utf-8')))
    cs=collections.Counter(); cb=collections.Counter(); co=collections.Counter(); out={}
    data=[(set(r['source_key'].split()),set(r['standard_key'].split())) for r in rows]
    for S,B in data:
        cs.update(S); cb.update(B)
        for a in S:
            for b in B: co[(a,b)]+=1
    best_s={}; best_b={}
    for (a,b),c in co.items():
        d=2*c/(cs[a]+cb[b])
        if d>best_s.get(a,(0,))[0]: best_s[a]=(d,b,c)
        if d>best_b.get(b,(0,))[0]: best_b[b]=(d,a,c)
    for a,(d,b,c) in best_s.items():
        if best_b.get(b,(0,None))[1]==a and c>=2 and d>=0.5: out[(a,b)]=c
    for r in rows:  # direct short rows
        s,b=r['source_key'],r['standard_key']
        if len(s.split())==1 and len(b.split())==1: out[(s,b)]=max(out.get((s,b),0),1)
    with open(ROOT/lang/'words.csv','w',encoding='utf-8',newline='') as f:
        w=csv.writer(f); w.writerow(['language','source_word','standard_word','meaning_status','source_dataset','evidence_count','hash'])
        for (a,b),c in sorted(out.items()):
            w.writerow([lang,a,b,'needs_native_review',SD,c,hashlib.sha256(f'w|{lang}|{a}|{b}'.encode()).hexdigest()])
    print(lang,len(out),list(out.items())[:12])
