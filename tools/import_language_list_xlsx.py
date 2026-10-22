#!/usr/bin/env python3
"""Import database/reviewed/bangladesh_languages_and_dialects.xlsx (one sheet per dialect / language group).
Everything goes into the dictionary. A word only changes the translator when it does not contradict
the corpus or a native speaker's entry; phrases/sentences become exact matches only when no trusted
sentence with the same Standard Bangla already exists. Conflicts are written to tools/import_report.txt."""
import csv, hashlib, re, sys, pathlib, collections, openpyxl
ROOT = pathlib.Path(__file__).resolve().parent.parent / 'database'
SRC = ROOT / 'reviewed' / 'bangladesh_languages_and_dialects.xlsx'
SD = 'User language list (bangladesh_languages_and_dialects.xlsx)'
DIALECTS = ['Barishal', 'Chattogram', 'Mymensingh', 'Noakhali', 'Rajshahi', 'Sylheti']
TAGS = {'Dhaka': 'Dhaka', 'Dhaka Old': 'Dhaka', 'Khulna': 'Khulna', 'Rangpur': 'Rangpur', 'Chakma': 'chakma', 'Marma': 'marma', 'Garo': 'garo', 'Santhali': 'santali'}
DECOMP = {'ড়': 'ড়', 'ঢ়': 'ঢ়', 'য়': 'য়', '‌': '', '‍': ''}
norm = lambda s: ''.join(DECOMP.get(c, c) for c in s)
key = lambda s: re.sub(r'\s+', ' ', re.sub(r'[?!।.,;:"“”\'()\-]', ' ', norm(s))).strip()
def variants(cell):
    out = []
    for v in str(cell).split('/'):
        v = re.sub(r'\([^)]*\)', '', v).strip()
        if v and re.search(r'[ঀ-৿]', v): out.append(v)
    return out
def corpus(lang):
    m = collections.defaultdict(collections.Counter)
    f = ROOT / 'languages' / lang.lower() / 'sentences.csv'
    for r in csv.DictReader(open(f, encoding='utf-8')):
        if r['split'] != 'train' or r['review_status'] == 'native_speaker_reported': continue
        s, t = key(r['standard_bangla']).split(), key(r['source_text']).split()
        if len(s) == len(t) and len(s) <= 12:
            for a, b in zip(s, t): m[a][b] += 1
    return m
items = collections.defaultdict(lambda: {'words': [], 'sents': []})
wb = openpyxl.load_workbook(SRC, read_only=True)
for ws in wb.worksheets:
    for row in list(ws.iter_rows(values_only=True))[1:]:
        if not row or not row[1] or not row[2]: continue
        typ = str(row[0]); m = re.search(r'\(([^)]*)\)', typ)
        lang = TAGS.get(m.group(1).strip()) if m else (ws.title if ws.title in DIALECTS else None)
        if not lang: continue
        std = str(row[1]).strip(); vs = variants(row[2])
        if not vs: continue
        if typ.startswith('Word') and len(key(std).split()) == 1:
            for i, v in enumerate(vs):
                if len(key(v).split()) == 1: items[lang]['words'].append((key(v), key(std), i == 0))
        else: items[lang]['sents'].append((vs, std, ''))
# Real items extracted from bangladesh_languages_complete_mega_dataset.xlsx (words; phrases as reference only).
MEGA = ROOT / 'reviewed' / 'mega_dataset_real_items.csv'
if MEGA.exists():
    for r in csv.DictReader(open(MEGA, encoding='utf-8')):
        if r['type'] == 'Word': items[r['language']]['words'].append((key(r['dialect']), key(r['standard_bangla']), True))
        else: items[r['language']]['sents'].append(([r['dialect']], r['standard_bangla'], 'reference'))
report = []
cur = list(csv.reader(open(ROOT / 'curated.csv', encoding='utf-8')))
for lang, it in items.items():
    folder = ROOT / 'languages' / lang.lower()
    W = [r for r in csv.reader(open(folder / 'words.csv', encoding='utf-8')) if len(r) > 4 and r[4] != SD]
    trustedW = {(r[1], r[2]) for r in W[1:] if r[3] in ('native_speaker_reported', 'community_verified')}
    trustedStd = {r[2] for r in W[1:] if r[3] in ('native_speaker_reported', 'community_verified')}
    cp = corpus(lang) if lang in DIALECTS else {}
    for dw, std, primary in dict.fromkeys(it['words']):
        if not dw or not std: continue
        if (dw, std) not in trustedW and not any(r[1] == dw and r[2] == std for r in W[1:]):
            W.append([lang, dw, std, 'needs_native_review', SD, '1', hashlib.sha256(f'ul|{lang}|{dw}|{std}'.encode()).hexdigest()])
        if lang not in DIALECTS or dw == std or not primary: continue   # only the list's main form may steer translation
        c = cp.get(std, collections.Counter()); n = sum(c.values()); share = c[dw] / n if n else None
        if std in trustedStd and (dw, std) not in trustedW:
            report.append(f'{lang}: {std} = {dw} kept in dictionary only (a native speaker entry already says {"/".join(sorted(x for x, y in trustedW if y == std))})')
        elif n >= 7 and share < 0.10:
            report.append(f'{lang}: {std} = {dw} kept in dictionary only (corpus uses {c.most_common(1)[0][0]} in {c.most_common(1)[0][1]} of {n} sentences)')
        elif not any(r[0] == lang and r[2] == std for r in cur[1:]):
            cur.append([lang, dw, std])
    csv.writer(open(folder / 'words.csv', 'w', encoding='utf-8', newline='')).writerows(W)
    S = [r for r in csv.reader(open(folder / 'sentences.csv', encoding='utf-8')) if len(r) > 6 and r[6] != SD]
    trusted = {norm(r[5]): r[2] for r in S[1:] if r[8] == '1' and r[7] in ('native_speaker_reported', 'community_verified', 'research_corpus_not_reaudited')}
    for vs, std, kind in it['sents']:
        bk = key(std)
        clash = [(a, b) for a, b in zip(key(std).split(), key(vs[0]).split()) if a in trustedStd and (b, a) not in trustedW and len(key(std).split()) == len(key(vs[0]).split())]
        if bk in trusted:
            report.append(f'{lang}: "{std}" already has "{trusted[bk]}"; the list\'s "{vs[0]}" added as a reference only'); first = False
        elif clash:
            report.append(f'{lang}: "{std}" = "{vs[0]}" added as a reference only (a native speaker entry gives a different form for {", ".join(a for a, b in clash)})'); first = False
        else: first = kind != 'reference'
        for i, v in enumerate(vs):
            sk = key(v)
            S.append([hashlib.sha256(f'ul|{lang}|{sk}|{bk}'.encode()).hexdigest(), lang, v, sk, std, bk, SD, 'needs_native_review', '1' if (first and i == 0) else '0', 'train', 'Bengali transliteration' if lang.islower() else 'Bengali', '1'])
    csv.writer(open(folder / 'sentences.csv', 'w', encoding='utf-8', newline='')).writerows(S)
    print(f'{lang}: {len(set(it["words"]))} words, {len(it["sents"])} phrases/sentences')
csv.writer(open(ROOT / 'curated.csv', 'w', encoding='utf-8', newline='')).writerows(cur)
M = list(csv.reader(open(ROOT / 'languages' / 'manifest.csv', encoding='utf-8')))
for r in M[1:]: r[3] = str(sum(1 for _ in open(ROOT / r[2], encoding='utf-8')) - 1)
csv.writer(open(ROOT / 'languages' / 'manifest.csv', 'w', encoding='utf-8', newline='')).writerows(M)
open(pathlib.Path(__file__).with_name('import_report.txt'), 'w', encoding='utf-8').write('\n'.join(report) + '\n')
print('\n'.join(report))
