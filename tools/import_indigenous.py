#!/usr/bin/env python3
"""Normalize real Chakma/Marma/Garo->Standard Bangla parallel data for VashaSetu.

Never manufacture a Bangla translation through English. Inspect the downloaded dataset
and choose the *actual* text and Standard Bangla columns.

Examples:
  python import_indigenous.py MELD.csv --inspect
  python import_indigenous.py MELD.csv --mode long \
      --language-col Language --native-col Sentence --bangla-col 'Standard Bengali'
  python import_indigenous.py MELD.xlsx --mode wide \
      --bangla-col 'Standard Bangla' --pair Chakma=Chakma \
      --pair Marma=Marma --pair Garo=Garo
"""
import argparse
import csv
import hashlib
import re
import unicodedata
from collections import defaultdict
from pathlib import Path

import pandas as pd

LANG_ALIASES = {
 'chakma':'chakma','চাকমা':'chakma','marma':'marma','মারমা':'marma',
 'garo':'garo','গারো':'garo','achik':'garo','a.chik':'garo',
 'tripura':'tripura','kokborok':'tripura','ত্রিপুরা':'tripura',
 'sadri':'sadri','সাদরি':'sadri',
 'santali':'santali','khasi':'khasi','mro':'mro','tanchangya':'tanchangya',
 'bawm':'bawm','khumi':'khumi','meitei':'meitei','rakhine':'rakhine'
}

def clean(s):
 if pd.isna(s): return ''
 return re.sub(r'\s+',' ',unicodedata.normalize('NFC',str(s))).strip()

def field_match(cols, candidate):
 if candidate in cols: return candidate
 m={re.sub(r'[^a-z0-9]+','',c.lower()):c for c in cols}
 return m.get(re.sub(r'[^a-z0-9]+','',candidate.lower()))

def split(standard):
 b=int(hashlib.sha256(standard.encode('utf8')).hexdigest()[:12],16)%100
 return 'train' if b < 80 else 'validation' if b < 90 else 'test'

def main():
 p=argparse.ArgumentParser(description=__doc__,formatter_class=argparse.RawDescriptionHelpFormatter)
 p.add_argument('file',type=Path,help='Local CSV or XLSX downloaded from an authorized source')
 p.add_argument('--inspect',action='store_true',help='Only show column headings and first two example rows')
 p.add_argument('--mode',choices=('long','wide'),default='long')
 p.add_argument('--language-col',help='Long format: language label column')
 p.add_argument('--native-col',help='Long format: native sentence column')
 p.add_argument('--bangla-col',help='Name of the actual Standard Bangla translation column')
 p.add_argument('--pair',action='append',default=[],help='Wide format mapping language=column, e.g. Chakma=Chakma')
 p.add_argument('--english-col',default='',help='Optional ENGLISH translation column; not a replacement for Bangla')
 p.add_argument('--dataset-name',default='MELD',help='Origin to cite in each CSV row')
 p.add_argument('--dataset-url',default='https://data.mendeley.com/datasets/dy5dyfygbp/4')
 p.add_argument('--script',default='Bengali transliteration',help='Explicit source orthography')
 p.add_argument('--out',type=Path,default=Path('indigenous_parallel_imported.csv'))
 args=p.parse_args()
 if args.file.suffix.lower() in ('.xlsx','.xls'):
  df=pd.read_excel(args.file,dtype=str).fillna('')
 else:
  df=pd.read_csv(args.file,dtype=str,encoding='utf-8-sig').fillna('')
 cols=[str(x) for x in df.columns]; df.columns=cols
 if args.inspect:
  print('Columns:',repr(cols));print(df.head(2).to_string(index=False));return
 if not args.bangla_col:
  p.error('Supply --bangla-col naming a genuine Standard Bangla translation column. English-only datasets are not equivalent.')
 bc=field_match(cols,args.bangla_col)
 if not bc: p.error('Standard Bangla column not found; run --inspect to check exact names')
 ec=field_match(cols,args.english_col) if args.english_col else None
 records=[]
 if args.mode=='long':
  if not args.language_col or not args.native_col:p.error('Long format requires --language-col and --native-col')
  lc=field_match(cols,args.language_col);nc=field_match(cols,args.native_col)
  if not lc or not nc:p.error('Language or native sentence column missing; use --inspect')
  for _,x in df.iterrows():
   lang=LANG_ALIASES.get(clean(x[lc]).lower())
   if not lang:continue
   records.append((lang,clean(x[nc]),clean(x[bc]),clean(x[ec]) if ec else ''))
 else:
  if not args.pair:p.error('Wide format requires --pair language=column, repeat as needed')
  pairs=[]
  for spec in args.pair:
   if '=' not in spec:p.error('Each --pair must be language=column')
   lang,col=spec.split('=',1);lang=LANG_ALIASES.get(lang.strip().lower());col=field_match(cols,col.strip())
   if not lang or not col:p.error('Bad language name or column in --pair '+spec)
   pairs.append((lang,col))
  for _,x in df.iterrows():
   for lang,col in pairs:
    records.append((lang,clean(x[col]),clean(x[bc]),clean(x[ec]) if ec else ''))

 # Filter blank/same-script-only rows and exact repeated pairs, flag one-source-many-Bangla conflicts.
 per_source=defaultdict(set)
 en_lookup={}
 for lang,native,bangla,english in records:
  if not native or not bangla:continue
  per_source[(lang,native)].add(bangla)
  en_lookup[(lang,native,bangla)]=english
 bad={k for k,values in per_source.items() if len(values)>1}
 out=[]
 for (lang,native),targets in sorted(per_source.items()):
  if (lang,native) in bad:continue
  bn=next(iter(targets))
  out.append({
   'source_language_id':lang,'native_text':native,'native_script':args.script,
   'romanization_native':'','standard_bangla':bn,'romanization_bangla':'',
   'english_gloss':en_lookup[(lang,native,bn)],'topic':'','lesson_level':'',
   'source_dataset':args.dataset_name,'source_url':args.dataset_url,
   'permission_license':'check dataset license','reviewer_language':'',
   'reviewed_by_native_speaker':'not_reaudited_by_vashasetu',
   'validation_status':'source_dataset_import','split':split(bn)
  })
 outcols=['source_language_id','native_text','native_script','romanization_native','standard_bangla','romanization_bangla','english_gloss','topic','lesson_level','source_dataset','source_url','permission_license','reviewer_language','reviewed_by_native_speaker','validation_status','split']
 args.out.parent.mkdir(parents=True,exist_ok=True)
 with args.out.open('w',newline='',encoding='utf-8-sig') as f:
  writer=csv.DictWriter(f,fieldnames=outcols);writer.writeheader();writer.writerows(out)
 conflictfile=args.out.with_name(args.out.stem+'_CONFLICTS.csv')
 with conflictfile.open('w',newline='',encoding='utf-8-sig') as f:
  writer=csv.writer(f);writer.writerow(['language_id','native_text','possible_standard_bangla','action'])
  for k in sorted(bad):writer.writerow([k[0],k[1],' | '.join(sorted(per_source[k])),'native_speaker_review_required'])
 print(f'Imported {len(out)} direct Indigenous->Standard Bangla sentence pairs. Conflicts excluded: {len(bad)}')
 print('This prepares data only; it does not train a neural model or update the PHP/MySQL app automatically.')
 print('Output:',args.out)

if __name__=='__main__':main()
