#!/usr/bin/env python3
"""Partition per-language datasets. Usage: python tools/split_datasets.py corpus_master_dir
Corpus master dir must contain parallel_sentences.csv and word_meanings.csv from tools/build_corpus_v3.py.
"""
import sys,csv,pathlib,re,collections
app=pathlib.Path(__file__).resolve().parent.parent
dest=app/'database/languages'
src=pathlib.Path(sys.argv[1]) if len(sys.argv)>1 else app/'database'
registry={}
with (app/'database/language_registry.csv').open(encoding='utf-8-sig',newline='') as f:
 for row in csv.DictReader(f):
  k=row['existing_app_key'] or row['language_id']
  if k!='standard':registry[k]=1
maps={'parallel_sentences':('sentences.csv','language'),'word_meanings':('words.csv','language')}
records=[]
for table,(short,langfield) in maps.items():
 with (src/(table+'.csv')).open(encoding='utf-8-sig',newline='') as f:
  reader=csv.DictReader(f);rows=list(reader);cols=reader.fieldnames
 groups=collections.defaultdict(list)
 for r in rows:
  if r[langfield] not in registry:raise ValueError('Unregistered language: '+r[langfield])
  groups[r[langfield]].append(r)
 for lang in sorted(registry):
  path=dest/lang.lower().replace(' ','_')/short;path.parent.mkdir(parents=True,exist_ok=True)
  with path.open('w',encoding='utf-8',newline='') as f:
   writer=csv.DictWriter(f,fieldnames=cols);writer.writeheader();writer.writerows(groups[lang])
  records.append([lang,table,path.relative_to(app/'database').as_posix(),len(groups[lang]),'research candidate: source-level status applies'])
with (dest/'manifest.csv').open('w',encoding='utf-8',newline='') as f:
 w=csv.writer(f);w.writerow(['language','dataset','path','rows','status']);w.writerows(records)
print('Partitioned',sum(r[3] for r in records),'rows across',len(registry),'language folders')
