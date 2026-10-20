#!/usr/bin/env python3
"""Import actual MELD corpus rows into database/indigenous_pairs.csv. Internet optional.
Runs on user's machine: python tools/import_full_meld.py --download
Or: python tools/import_full_meld.py local_MELD.csv
Then visit setup.php?refresh_indigenous=1 (back up first): seed new pairs.
"""
import argparse, csv, io, pathlib, urllib.request, collections
URL='https://raw.githubusercontent.com/AnzirRahman/MELD/main/Language_Dataset_Chakma%2C_Garo%2C_Marma%2C_Standard_Bangla_%26_English.csv'
ROOT=pathlib.Path(__file__).resolve().parent.parent
OUT=ROOT/'database'/'indigenous_pairs.csv'
p=argparse.ArgumentParser();p.add_argument('input', nargs='?', help='MELD source CSV downloaded locally');p.add_argument('--download',action='store_true');p.add_argument('--out',default=str(OUT));args=p.parse_args()
if args.download:
    req=urllib.request.Request(URL,headers={'User-Agent':'VashaSetu/2.0 (research import)'})
    try:
        with urllib.request.urlopen(req,timeout=30) as response: data=response.read().decode('utf-8-sig')
    except Exception as exc:raise SystemExit('Could not download MELD. Download the CSV from https://github.com/AnzirRahman/MELD and pass its filename instead. Details: '+str(exc))
elif args.input:
    data=pathlib.Path(args.input).read_text(encoding='utf-8-sig')
else:raise SystemExit('Supply a downloaded CSV filename OR --download')
reader=csv.DictReader(io.StringIO(data));cols=set((reader.fieldnames or []))
expect={'standard bangla','garo','marma','chakma','English'}
if not expect.issubset(cols): raise SystemExit('Unexpected columns: '+', '.join(reader.fieldnames or [])+'. Check MELD repository version.')
# Only direct native-language -> Standard Bangla pairs. No word-level alignments inferred.
allrows=[];clash=collections.defaultdict(set)
for r in reader:
    b=r['standard bangla'].strip();eng=r['English'].strip()
    if not b:continue
    for lang in ('chakma','marma','garo'):
        s=r[lang].strip()
        if not s:continue
        k=(lang,s);clash[k].add(b)
        allrows.append((lang,s,b,eng))
seen=set();keep=[];conflicts=0
for lang,s,b,eng in allrows:
    k=(lang,s,b)
    if k in seen:continue
    seen.add(k)
    if len(clash[(lang,s)])>1:conflicts+=1;continue
    keep.append([lang,s,b,eng,'Bengali transliteration','MELD v4 (research repository)','native_speaker_corpus_not_independently_reaudited','https://github.com/AnzirRahman/MELD'])
if len(keep)<500:raise SystemExit('Too few usable pairs ('+str(len(keep))+'); verify you downloaded the complete MELD file. Output not overwritten.')
out=pathlib.Path(args.out);out.parent.mkdir(parents=True,exist_ok=True)
with out.open('w',encoding='utf-8',newline='') as f:
    w=csv.writer(f);w.writerow(['language_id','source_text','standard_bangla','english','script','source_dataset','review_status','source_url']);w.writerows(keep)
print('Imported',len(keep),'direct sentence pairs; omitted',conflicts,'ambiguous source rows. Output:',out)
print('Make a backup of MySQL, then open setup.php?refresh_indigenous=1 to reload only Indigenous pairs.')
