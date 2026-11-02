#!/usr/bin/env python3
"""Cleans spelling errors in the word lists that are certain to be typing mistakes (found by the data-quality test).

* The same vowel sign typed twice (হাতুড়িি, শাান্তি, গৃৃৃহিণী): Bangla never repeats a vowel sign, so one is removed.
* A vowel sign typed in two parts (ে + া): joined into the single letter ো (Unicode NFC).
* Spaces at the start or end of a word, and rows that appear twice.
* Two words of the native-speaker-approved Noakhali list, corrected with the user on 2 Nov 2026:
  কওা -> কওয়া, ফড়ওালা -> ফড়ওয়ালা.

Other unusual spellings are NOT changed (they need a native speaker); the data-quality test lists them.
    python tools/clean_word_lists.py
"""
import csv, glob, hashlib, os, re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DOUBLE = re.compile('([া-ৌৗৃ])\\1+')
FIXED = {('Noakhali', 'কওা'): 'কওয়া', ('Noakhali', 'ফড়ওালা'): 'ফড়ওয়ালা'}


def clean(s: str) -> str:
    s = s.strip().replace('ো', 'ো').replace('ৌ', 'ৌ')
    return DOUBLE.sub(r'\1', s)


changed = 0
for path in sorted(glob.glob(os.path.join(ROOT, 'database', 'languages', '*', 'words.csv'))):
    with open(path, encoding='utf-8', newline='') as f:
        rows = list(csv.reader(f))
    if len(rows) < 2:
        continue
    head = rows[0]; i_l, i_s, i_t, i_h = (head.index(k) for k in ('language', 'source_word', 'standard_word', 'hash'))
    n = 0
    seen, keep = set(), [head]
    for r in rows[1:]:
        k = (r[i_l], clean(r[i_s]), clean(r[i_t]))
        if k in seen:
            n += 1          # exact duplicate row: drop it
            continue
        seen.add(k); keep.append(r)
    rows = keep
    for r in rows[1:]:
        src = FIXED.get((r[i_l], r[i_s].strip()), clean(r[i_s]))
        std = clean(r[i_t])
        if (src, std) != (r[i_s], r[i_t]):
            r[i_s], r[i_t] = src, std
            r[i_h] = hashlib.sha256(f'{r[i_l]}|{src}|{std}'.encode()).hexdigest()
            n += 1
    if n:
        with open(path, 'w', encoding='utf-8', newline='') as f:
            csv.writer(f, lineterminator='\n').writerows(rows)
        print(f'{os.path.relpath(path, ROOT)}: {n} rows corrected')
        changed += n
print(f'{changed} rows corrected in total')
