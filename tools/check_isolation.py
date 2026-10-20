#!/usr/bin/env python3
"""Validate all partitioned datasets and prevent language mismatches before import."""
import csv,collections,pathlib,sys,hashlib
root=pathlib.Path(__file__).resolve().parent.parent/'database'
manifest=root/'languages/manifest.csv'
with manifest.open(encoding='utf-8',newline='') as f: rows=list(csv.DictReader(f))
assert rows,'No dataset manifest entries'
seen=set();total=collections.Counter();lang_totals=collections.defaultdict(lambda:collections.Counter())
for r in rows:
    lg=r['language'];name=r['dataset'];path=root/r['path']
    assert str(path.resolve()).startswith(str((root/'languages').resolve())+'/'),path
    assert path.is_file(),path
    with path.open(encoding='utf-8',newline='') as f:
        file_rows=list(csv.DictReader(f))
    assert len(file_rows)==int(r['rows']), (lg,name,len(file_rows),r['rows'])
    assert all(x['language']==lg for x in file_rows),(lg,name,'foreign language label detected')
    assert (lg,name) not in seen;seen.add((lg,name))
    if name=='parallel_sentences':
        for p in file_rows:
            assert p['source_text'] and p['standard_bangla'] and p['source_dataset']
            assert p['review_status'] and p['split'] in ('train','validation','test')
            assert p['eligible_exact'] in ('0','1')
            assert len(p['pair_hash'])==64
    if name=='word_meanings':
        for w in file_rows:
            assert w['source_word'] and w['standard_word'] and w['source_dataset'] and w['meaning_status']
            assert len(w['hash'])==64
    total[name]+=len(file_rows);lang_totals[lg][name]=len(file_rows)
assert total['parallel_sentences']==67605,total
assert total['word_meanings']==7607,total
print('PASS: checked',len(seen),'language/dataset partitions across',len(lang_totals),'languages')
print('Total',dict(total))
for k,v in sorted(lang_totals.items()):print(k,v['parallel_sentences'],'sentences;',v['word_meanings'],'word senses')
