import csv,collections,hashlib,pathlib,re,unicodedata,json
root=pathlib.Path(__file__).resolve().parent.parent/'database'
PUNCT=re.compile(r'''[?!,.;:।"'()\[\]‘’“”—\-]+''')
def norm(s):
 s=unicodedata.normalize('NFC',str(s or '').replace('\u200d','').replace('\u200c','').strip()).replace('ড়','ড়').replace('ঢ়','ঢ়').replace('য়','য়')
 return ' '.join(PUNCT.sub(' ',s).split())
def rows(path):
 with open(root/path,encoding='utf-8-sig',newline='') as f:yield from csv.DictReader(f)
def write(path,cols,data):
 with open(root/path,'w',encoding='utf-8',newline='') as f:
  w=csv.DictWriter(f,fieldnames=cols);w.writeheader();w.writerows(data)
bykey=collections.defaultdict(list)
langmap={'barishal-bn':'Barishal','chattogram-bn':'Chattogram','mymensingh-bn':'Mymensingh','noakhali-bn':'Noakhali','rajshahi-bn':'Rajshahi','sylheti':'Sylheti'}
for part in ('train','validation','test'):
 for r in rows('training/regional_'+part+'.csv'):
  lg=langmap.get(r['language_id'])
  if not lg:continue
  source=norm(r['source_text']);target=norm(r['standard_bangla'])
  if not source or not target or len(source)>300 or len(target)>300:continue
  bykey[(lg,source)].append((target,r['source_dataset'],r['review_status'],part))
for r in rows('regional_exact_pairs.csv'):
 source=norm(r['source_text']);target=norm(r['standard_bangla']);lg=r['variety']
 if source and target and len(source)<=300 and len(target)<=300:
  bykey[(lg,source)].append((target,r['dataset'],'publication_reviewed_not_reaudited','train'))
for r in rows('indigenous_pairs.csv'):
 source=norm(r['source_text']);target=norm(r['standard_bangla']);lg=r['language_id']
 if source and target and len(source)<=300 and len(target)<=300:
  bykey[(lg,source)].append((target,r['source_dataset'],'research_corpus_not_reaudited','train'))
# Stable splits group by same source/standard pair (train cannot contain evaluation duplicates).
def group_split(var,src,std):
 h=int(hashlib.sha256((var+'|'+src+'|'+std).encode()).hexdigest()[:12],16)%100
 return 'train' if h<80 else 'validation' if h<90 else 'test'
result=[];conflict=[];counts=collections.Counter()
for (lg,src),vals in sorted(bykey.items()):
 targets={v[0] for v in vals};counts['groups']+=1
 if len(targets)>1:
  conflict.append({'language':lg,'source_text':src,'possible_meanings':' | '.join(sorted(targets)), 'sources':' | '.join(sorted({v[1] for v in vals})),'action':'EXCLUDED_for_conflicting_Standard_Bangla_review_required'})
  counts['conflict_groups']+=1;continue
 target=next(iter(targets));pub=[x for x in vals if 'publication_reviewed' in x[2] or x[1]=='BD-Dialect']
 indigenous=lg in ('chakma','garo','marma')
 best=(pub or vals)[0]
 status='research_corpus_not_reaudited' if indigenous else ('publication_reviewed_not_reaudited' if pub else 'needs_native_review')
 # Keep all source evidence for exact-phrase lookup; only publication-reviewed is used as candidate by default.
 for_translation=1 if (status=='publication_reviewed_not_reaudited' or indigenous) else 0
 source_ids=' | '.join(sorted({v[1] for v in vals}))[:200]
 key=hashlib.sha256((lg+'\x1f'+src+'\x1f'+target).encode()).hexdigest()
 result.append(dict(pair_hash=key,language=lg,source_text=src,source_key=src,standard_bangla=target,standard_key=target,source_dataset=source_ids,review_status=status,eligible_exact=for_translation,split=group_split(lg,src,target),script='Bengali transliteration' if indigenous else 'Bengali',source_count=len(vals)))
 counts['retained_'+status]+=1
print('Parallel evidence',len(result),'conflicts excluded',len(conflict),counts)
fields=list(result[0]);write('parallel_sentences.csv',fields,result)
write('training/sentence_conflicts_v3.csv',list(conflict[0]) if conflict else ['language','source_text','possible_meanings','sources','action'],conflict)
# Build independently attested one-token word meanings. NEVER align words by sentence position.
word_sources=collections.defaultdict(list)
for r in result:
 if len(r['source_text'].split())==1 and len(r['standard_bangla'].split())==1 and r['source_text']!=r['standard_bangla']:
  word_sources[(r['language'],r['source_text'])].append((r['standard_bangla'],r['source_dataset'],r['review_status']))
for r in rows('curated.csv'):
 word_sources[(r['dialect'],norm(r['dialect_word']))].append((norm(r['standard_word']),'Existing app curated.csv','existing_curated_needs_recheck'))
words=[];wc=[]
for (lg,w),vals in sorted(word_sources.items()):
 means={v[0] for v in vals}
 if len(means)!=1:
  wc.append(dict(language=lg,word=w,possible_meanings=' | '.join(sorted(means)),action='EXCLUDED_word_conflict'));continue
 meaning=next(iter(means));source=' | '.join(sorted({x[1] for x in vals}))[:200]
 if any(x[2]=='existing_curated_needs_recheck' for x in vals):status='existing_curated_needs_recheck'
 elif any(x[2]=='publication_reviewed_not_reaudited' for x in vals):status='publication_reviewed_not_reaudited'
 elif lg in ('chakma','marma','garo'):status='research_corpus_not_reaudited'
 else:status='needs_native_review'
 words.append(dict(language=lg,source_word=w,standard_word=meaning,meaning_status=status,source_dataset=source,evidence_count=len(vals),hash=hashlib.sha256((lg+'|'+w+'|'+meaning).encode()).hexdigest()))
print('Word evidence',len(words),'word conflicts',len(wc),collections.Counter(w['meaning_status'] for w in words))
write('word_meanings.csv',list(words[0]),words)
write('training/word_conflicts_v3.csv',list(wc[0]) if wc else ['language','word','possible_meanings','action'],wc)
(root/'quality_report_v3.json').write_text(json.dumps({'sentences_retained':len(result),'sentence_conflicts':len(conflict),'word_meanings_attested':len(words),'word_conflicts':len(wc),'status_sentences':dict(collections.Counter(r['review_status'] for r in result)),'status_words':dict(collections.Counter(r['meaning_status'] for r in words)),'note':'Publication-reviewed does not mean independently reaudited. Candidate sentences excluded from live exact translation but included for corpus/training. Native-language references are Bengali-script transliterations.'},indent=2,ensure_ascii=False))

# Split freshly regenerated master CSV files into language-isolated production files.
import subprocess,sys
subprocess.run([sys.executable,str(pathlib.Path(__file__).parent/'split_datasets.py'),str(root)],check=True)
(root/'parallel_sentences.csv').unlink()
(root/'word_meanings.csv').unlink()
