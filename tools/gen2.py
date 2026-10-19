from build import norm
import csv,re,collections
W='/tmp/wl/BengaliDictionary/'
OUT='/home/claude/vashasetu_app/database/'
freq=collections.Counter()
for r in csv.DictReader(open(OUT+'vocab.csv',encoding='utf-8')):
    freq[r['word']]+=int(r['count'])
words={}
for line in open(W+'BengaliWordList_112.txt',encoding='utf-8-sig'):
    w=norm(line.strip())
    if not w or ' ' in w or len(w)<2 or len(w)>30: continue
    if not re.fullmatch(r'[ঀ-৿]+',w): continue
    words[w]=freq.get(w,0)
for w,c in freq.items():
    if re.fullmatch(r'[ঀ-৿]+',w): words[w]=c
with open(OUT+'bn_words.csv','w',encoding='utf-8',newline='') as f:
    wr=csv.writer(f); wr.writerow(['word','freq'])
    for w,c in words.items(): wr.writerow([w,c])
print('bn_words',len(words))
en={}
for r in csv.reader(open(W+'BengaliDictionary_93.csv',encoding='utf-8-sig')):
    if len(r)<2: continue
    e=r[0].strip().lower(); b=r[1].strip()
    if not e or not b or len(e)>60 or len(b)>120: continue
    if not re.fullmatch(r"[a-z][a-z' \-]*",e): continue
    if e not in en: en[e]=b
with open(OUT+'en_bn.csv','w',encoding='utf-8',newline='') as f:
    wr=csv.writer(f); wr.writerow(['english','bangla'])
    for e,b in en.items(): wr.writerow([e,b])
print('en_bn',len(en))
