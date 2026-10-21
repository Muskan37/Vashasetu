#!/usr/bin/env python3
"""Consistency check for admin districts and candidate language IDs."""
from pathlib import Path
import json,csv
root=Path(__file__).resolve().parents[1]
a=json.loads((root/'database/districts.json').read_text(encoding='utf-8'))
rows=a['districts']
assert len(rows)==64 and len({r['id'] for r in rows})==64
expected={'Dhaka':13,'Chattogram':11,'Rajshahi':8,'Khulna':10,'Barishal':6,'Sylhet':4,'Rangpur':8,'Mymensingh':4}
for k,v in expected.items(): assert len([r for r in rows if r['division']==k])==v
with (root/'database/language_registry.csv').open(encoding='utf-8-sig',newline='') as f: languages={r['language_id'] for r in csv.DictReader(f)}
for r in rows:
 assert r['mapping_status']=='geographic_candidates_unverified'
 assert r['suggested_languages'] and all(x in languages for x in r['suggested_languages'])
print('PASS: 64 districts, 8 divisions, all candidate languages registered; geographic associations unverified.')
