#!/usr/bin/env python3
"""Puts a native speaker's reviewed word list into the app.

    python tools/apply_native_review.py <reviewed.csv> <language folder> [--rows N] [--original original.csv]

reviewed.csv : the reviewed sheet downloaded as CSV (column 1 = dialect word, column 2 = Standard Bangla meaning).
--rows N     : only the first N word rows were reviewed (e.g. 300); the rest of the sheet is ignored.
--original   : the sheet as it was before the review. Its first rows are the ones the reviewer saw; any of them the
               reviewer changed or deleted is removed from the app, so the old (wrong) pair does not stay behind.
               Without it, the whole app list is replaced by the reviewed rows.

Rules
* "a/b" in a cell means two alternatives: one row each.
* The first time a Standard word appears it is the main form (used in translations); later forms of the same
  Standard word are kept as approved alternatives (shown in Words, recognised when typed).
* Reviewed rows get meaning_status native_speaker_reported / native_speaker_alternative and the source
  "Native speaker reviewed list (Nov 2026)".
"""
import argparse, csv, hashlib, os, re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SOURCE = 'Native speaker reviewed list (Nov 2026)'


def clean(s):
    s = (s or '').strip().replace('ো', 'ো').replace('ৌ', 'ৌ')
    return re.sub('([া-ৌৗৃ])\\1+', r'\1', s)


def pairs(rows):
    out = []
    for r in rows:
        if len(r) < 2 or not clean(r[0]) or not clean(r[1]):
            continue
        for a in [x for x in (clean(p) for p in r[0].split('/')) if x]:
            for b in [x for x in (clean(p) for p in r[1].split('/')) if x]:
                out.append((a, b))
    return out


ap = argparse.ArgumentParser()
ap.add_argument('reviewed'); ap.add_argument('language')
ap.add_argument('--rows', type=int); ap.add_argument('--original')
a = ap.parse_args()
rev = list(csv.reader(open(a.reviewed, encoding='utf-8-sig')))[1:]
if a.rows: rev = rev[:a.rows]
approved = pairs(rev)

path = os.path.join(ROOT, 'database', 'languages', a.language, 'words.csv')
with open(path, encoding='utf-8', newline='') as f:
    rows = list(csv.reader(f))
head = rows[0]; idx = {k: head.index(k) for k in head}
lang = rows[1][idx['language']] if len(rows) > 1 else a.language
old = {(clean(r[idx['source_word']]), clean(r[idx['standard_word']])): r for r in rows[1:]}

if a.original:
    orig = list(csv.reader(open(a.original, encoding='utf-8-sig')))[1:]
    # the reviewer saw the original rows that are still above the last reviewed one
    last = rev[-1][:2] if rev else None
    cut = next((i for i, r in enumerate(orig) if last and r[:2] == last), len(rev) - 1)
    seen = set(pairs(orig[:cut + 1]))
    keep = [r for r in rows[1:] if (clean(r[idx['source_word']]), clean(r[idx['standard_word']])) not in seen]
else:
    keep = []

ap_set = set(approved)
keep = [r for r in keep if (clean(r[idx['source_word']]), clean(r[idx['standard_word']])) not in ap_set]
main = set(); new = []; done = set()
for s, t in approved:
    if (s, t) in done: continue
    done.add((s, t))
    status = 'native_speaker_reported' if t not in main or s == t else 'native_speaker_alternative'
    main.add(t)
    r = [''] * len(head)
    r[idx['language']] = lang; r[idx['source_word']] = s; r[idx['standard_word']] = t
    r[idx['meaning_status']] = status; r[idx['source_dataset']] = SOURCE
    r[idx['evidence_count']] = old[(s, t)][idx['evidence_count']] if (s, t) in old else '1'
    r[idx['hash']] = hashlib.sha256(f'{lang}|{s}|{t}'.encode()).hexdigest()
    new.append(r)
with open(path, 'w', encoding='utf-8', newline='') as f:
    csv.writer(f, lineterminator='\n').writerows([head] + new + keep)
print(f'{os.path.relpath(path, ROOT)}: {len(new)} reviewed pairs '
      f'({sum(r[idx["meaning_status"]] == "native_speaker_alternative" for r in new)} alternatives), '
      f'{len(rows) - 1 - len(keep)} old rows replaced, {len(keep)} other rows kept')
