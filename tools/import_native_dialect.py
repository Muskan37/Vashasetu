#!/usr/bin/env python3
"""Load a native-speaker dialect file (Type,Standard Bangla,<Dialect> Dialect,English) into the app's data.
Usage: python tools/import_native_dialect.py database/reviewed/chattogram_native_dataset.csv Chattogram
Words go to the dictionary and the trusted list; phrases and sentences become exact sentence matches.
'Generated Sentence' rows whose person does not agree (তুমি/সে + যাবো) are skipped and reported."""
import csv, hashlib, re, sys, pathlib
ROOT = pathlib.Path(__file__).resolve().parent.parent / 'database'
src, dialect = sys.argv[1], sys.argv[2]
SD = 'Native speaker dataset (' + pathlib.Path(src).name + ')'
DECOMP = {'\u09dc': '\u09a1\u09bc', '\u09dd': '\u09a2\u09bc', '\u09df': '\u09af\u09bc', '\u200c': '', '\u200d': ''}
norm = lambda s: ''.join(DECOMP.get(c, c) for c in s)
key = lambda s: re.sub(r'\s+', ' ', re.sub(r'[?!।.,;:"“”\'()\-]', ' ', norm(s))).strip()   # same as bn_normalize() in the app
fix = lambda s: re.sub(r'\u09bf\u09c7(?=[\s।?!]|$)', '\u09bfতে', s)   # typo বাড়িে -> বাড়িতে
rows = list(csv.DictReader(open(src, encoding='utf-8-sig')))
dcol = [c for c in rows[0] if c not in ('Type', 'Standard Bangla', 'English')][0]
words, sents, skipped = [], [], []
for r in rows:
    t, std, dia = r['Type'].strip(), fix(r['Standard Bangla'].strip()), r[dcol].strip()
    if not std or not dia: continue
    if t == 'Generated Sentence' and re.match(r'^(তুমি|সে)\s', std) and std.rstrip('।').endswith('যাবো'):
        skipped.append(std + ' = ' + dia); continue
    if t == 'Word' and len(std.split()) == 1 and len(dia.split()) == 1: words.append((dia, std))
    else: sents.append((dia, std))
    # same-length generated sentences also teach their place words, e.g. বাজারে = বজারত
    if t == 'Generated Sentence':
        a, b = key(dia).split(), key(std).split()
        if len(a) == len(b):
            for x, y in zip(a, b):
                if x != y: words.append((x, y))
lang = dialect.lower(); d = ROOT / 'languages' / lang
# dictionary words
wf = d / 'words.csv'; W = list(csv.reader(open(wf, encoding='utf-8'))); W = [r for r in W if r[4] != SD]; have = {(r[1], r[2]) for r in W[1:]}
for dia, std in dict.fromkeys(words):
    W = [r for r in W if not (r[0] == dialect and r[1] == dia and r[2] == std)]
    W.append([dialect, dia, std, 'native_speaker_reported', SD, '1', hashlib.sha256(f'nsr|{dialect}|{dia}|{std}'.encode()).hexdigest()])
csv.writer(open(wf, 'w', encoding='utf-8', newline='')).writerows(W)
# trusted list used by the translator
cf = ROOT / 'curated.csv'; C = list(csv.reader(open(cf, encoding='utf-8')))
for dia, std in dict.fromkeys(words):
    C = [r for r in C if not (r[0] == dialect and (r[1] == dia or r[2] == std))]
    C.append([dialect, dia, std])
csv.writer(open(cf, 'w', encoding='utf-8', newline='')).writerows(C)
# sentences / phrases
sf = d / 'sentences.csv'; S = list(csv.reader(open(sf, encoding='utf-8'))); S = [r for r in S if r[6] != SD]   # re-import replaces the previous copy
for dia, std in dict.fromkeys(sents):
    sk, bk = key(dia), key(std)
    S = [r for r in S if norm(r[3]) != sk and norm(r[5]) != bk]   # this file's pair replaces any other with the same text
    S.append([hashlib.sha256(f'nsr|{lang}|{sk}|{bk}'.encode()).hexdigest(), dialect, dia, sk, std, bk, SD, 'native_speaker_reported', '1', 'train', 'Bengali', '1'])
csv.writer(open(sf, 'w', encoding='utf-8', newline='')).writerows(S)
# manifest counts
mf = ROOT / 'languages' / 'manifest.csv'; M = list(csv.reader(open(mf, encoding='utf-8')))
for r in M[1:]:
    if r[0] == dialect: r[3] = str(sum(1 for _ in open(ROOT / r[2], encoding='utf-8')) - 1)
csv.writer(open(mf, 'w', encoding='utf-8', newline='')).writerows(M)
print(f'{dialect}: {len(set(words))} words, {len(set(sents))} phrases/sentences imported; skipped {len(skipped)}:')
for s in skipped: print('  ', s)
