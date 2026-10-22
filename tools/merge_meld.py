#!/usr/bin/env python3
"""Merge MELD CSV into database/languages/{chakma,marma,garo} + indigenous_pairs.csv (already done in this build)."""
import csv, hashlib, re, sys, collections, pathlib
SRC=sys.argv[1]; ROOT=pathlib.Path(__file__).resolve().parent.parent/'database'
norm=lambda s: re.sub(r'\s+',' ',re.sub(r'[?!।.,;:"“”\']','',s)).strip()
rows=list(csv.DictReader(open(SRC,encoding='utf-8-sig')))
SD='MELD (Mendeley/GitHub, full)'; ST='research_corpus_not_reaudited'
ind=[]; 
for lang in ('chakma','marma','garo'):
    pairs=collections.OrderedDict(); clash=collections.defaultdict(set)
    for r in rows:
        s=r[lang].strip(); b=r['standard bangla'].strip()
        if not s or not b: continue
        clash[norm(s)].add(norm(b))
    for r in rows:
        s=r[lang].strip(); b=r['standard bangla'].strip()
        if not s or not b: continue
        k=(norm(s),norm(b))
        if not k[0] or not k[1] or len(clash[k[0]])>1 or k in pairs: continue
        pairs[k]=(s,b,r['English'].strip())
    srows=[];wrows=[]
    for (sk,bk),(s,b,e) in pairs.items():
        h=hashlib.sha256(f'{lang}|{sk}|{bk}'.encode()).hexdigest()
        split=['train','train','train','train','validation','test'][int(h[:4],16)%6]
        srows.append([h,lang,s,sk,b,bk,SD,ST,1,split,'Bengali transliteration',1])
        ind.append([lang,s,b,e,'Bengali transliteration',SD,'native_speaker_corpus_not_independently_reaudited','https://github.com/AnzirRahman/MELD'])
        if len(sk.split())==1 and len(bk.split())==1:
            wrows.append([hashlib.sha256(f'w|{lang}|{sk}|{bk}'.encode()).hexdigest(),lang,sk,bk,'needs_native_review',SD,1])
    d=ROOT/'languages'/lang
    with open(d/'sentences.csv','w',encoding='utf-8',newline='') as f:
        w=csv.writer(f);w.writerow('pair_hash,language,source_text,source_key,standard_bangla,standard_key,source_dataset,review_status,eligible_exact,split,script,source_count'.split(','));w.writerows(srows)
    with open(d/'words.csv','w',encoding='utf-8',newline='') as f:
        w=csv.writer(f);w.writerow('hash,language,source_word,standard_word,meaning_status,source_dataset,evidence_count'.split(','));
        # words.csv column order in V6: language,source_word,standard_word,meaning_status,source_dataset,evidence_count,hash
    print(lang,len(srows),'sentences',len(wrows),'words')
    globals().setdefault('W',{})[lang]=wrows
# words files use V6 header order
for lang,wr in W.items():
    with open(ROOT/'languages'/lang/'words.csv','w',encoding='utf-8',newline='') as f:
        w=csv.writer(f);w.writerow(['language','source_word','standard_word','meaning_status','source_dataset','evidence_count','hash'])
        w.writerows([[r[1],r[2],r[3],r[4],r[5],r[6],r[0]] for r in wr])
with open(ROOT/'indigenous_pairs.csv','w',encoding='utf-8',newline='') as f:
    w=csv.writer(f);w.writerow(['language_id','source_text','standard_bangla','english','script','source_dataset','review_status','source_url']);w.writerows(ind)
# manifest counts
m=list(csv.reader(open(ROOT/'languages'/'manifest.csv',encoding='utf-8')))
for r in m[1:]:
    if r[0] in W:
        n=sum(1 for _ in open(ROOT/r[2],encoding='utf-8'))-1; r[3]=str(n)
csv.writer(open(ROOT/'languages'/'manifest.csv','w',encoding='utf-8',newline='')).writerows(m)
# NOTE: after this, run tools/meld_words.py to (re)build the Chakma/Marma/Garo word lists.
