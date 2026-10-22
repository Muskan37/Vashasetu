#!/usr/bin/env python3
"""Check the word lists in database/reviewed/ against the dialect corpora, then load them.

Sources: chattogram_native_dataset.csv, bangladesh_languages_and_dialects.xlsx, mega_dataset_real_items.csv
For every entry the script looks for evidence in that language's own data (parallel sentences with the
same number of words, BanglaDial word counts, MELD for Chakma/Marma/Garo):
  Confirmed            the corpus pairs this Standard word with this dialect form
  Contradicted         the corpus uses a different form most of the time and never this one
  Not found            the dialect form does not occur in that dialect's data
Only confirmed words steer the translator (database/curated.csv). Phrases/sentences become exact
translations only when every dialect word occurs in that dialect's data. Everything else is kept in the
dictionary as "needs native review". A full report is written to database/reviewed/check_report.xlsx.
Run:  python tools/import_checked_lists.py   then open setup.php?reset=1"""
import csv, re, collections, hashlib, pathlib, openpyxl
from openpyxl.styles import PatternFill, Font
ROOT = pathlib.Path(__file__).resolve().parent.parent / 'database'
REV = ROOT / 'reviewed'
DECOMP = {'ড়': 'ড়', 'ঢ়': 'ঢ়', 'য়': 'য়', '‌': '', '‍': ''}
norm = lambda s: ''.join(DECOMP.get(c, c) for c in str(s))
key = lambda s: re.sub(r'\s+', ' ', re.sub(r'[?!।.,;:"“”\'()\-]', ' ', norm(s))).strip()   # = bn_normalize()
DIAL = ['Barishal', 'Chattogram', 'Mymensingh', 'Noakhali', 'Rajshahi', 'Sylheti']
NATIVE = ['chakma', 'marma', 'garo']
SRC = {'csv': 'Chittagong list (checked)', 'xlsx': 'Languages list (checked)', 'mega': 'Mega dataset real part (checked)'}
OLD = ('Native speaker dataset', 'User language list') + tuple(SRC.values())
# ---------------- evidence ----------------
align, dvoc = {}, collections.defaultdict(collections.Counter)
for lang in DIAL + NATIVE:
    a = collections.defaultdict(collections.Counter)
    for r in csv.DictReader(open(ROOT / 'languages' / lang.lower() / 'sentences.csv', encoding='utf-8')):
        if r['source_dataset'].startswith(OLD): continue
        s, t = key(r['standard_bangla']).split(), key(r['source_text']).split()
        dvoc[lang].update(t)
        if len(s) == len(t) and len(s) <= 14:
            for x, y in zip(s, t): a[x][y] += 1
    align[lang] = a
for r in csv.DictReader(open(ROOT / 'vocab.csv', encoding='utf-8')):
    if r['variety'] in DIAL: dvoc[r['variety']][norm(r['word'])] += int(r['count'])
def stdvar(w): return {w, w[:-1] if re.search('[বছ]ো$', w) else (w + 'ো' if re.search('[বছ]$', w) else w)}
SHARE = {}
def check_word(lang, std, dia):
    if lang not in align: return 'No data to check against', ''
    a = collections.Counter()
    for v in stdvar(std): a.update(align[lang].get(v, {}))
    n, al, f = sum(a.values()), a[dia], dvoc[lang][dia]
    top = a.most_common(1)[0] if a else ('', 0)
    SHARE[(lang, std, dia)] = (al / n) if n > 5 else (1.0 if al >= 2 else 0.0)   # one sighting is too little to change translations
    ev = f'"{std}" in {lang} data: {n}x, as "{dia}" {al}x' + (f', most often "{top[0]}" ({top[1]}x)' if n else '') + f'; "{dia}" occurs {f}x'
    if al >= 2 or (al >= 1 and n <= 5): return 'Confirmed', ev
    if dia == std and (n < 7 or a[std] / n >= 0.2): return 'Confirmed', ev
    if n >= 7 and al / n < 0.1 and top[1] / n >= 0.4: return 'Contradicted', ev
    if f >= 3: return 'Word exists, meaning not confirmed', ev
    others = [l for l in DIAL + NATIVE if l != lang and dvoc[l][dia] >= 3]
    return ('Not found (occurs in ' + ', '.join(others) + ' data)') if others else 'Not found', ev
def check_sent(lang, std, dia):
    if lang not in align: return 'No data to check against', ''
    st, dt = key(std).split(), key(dia).split()
    miss = [w for w in dt if dvoc[lang][w] < 2 and w not in st]
    return ('All words found', '') if not miss else (f'{len(miss)} of {len(dt)} words not found', 'not found: ' + ', '.join(miss))
# ---------------- read the lists ----------------
entries = []   # (source, lang, kind, std, dia, primary)
for r in csv.DictReader(open(REV / 'chattogram_native_dataset.csv', encoding='utf-8-sig')):
    std = re.sub(r'িে(?=[\s।?!]|$)', 'িতে', r['Standard Bangla'].strip())   # typo বাড়িে
    if r['Type'] == 'Generated Sentence' and re.match(r'^(তুমি|সে)\s', std):
        entries.append(('csv', 'Chattogram', 'Sentence', std, r['Chattogram Dialect'], True, 'Skipped: wrong grammar (তুমি/সে with যাবো, dialect uses the "I/we" form)')); continue
    entries.append(('csv', 'Chattogram', 'Word' if r['Type'] == 'Word' else 'Sentence', std, r['Chattogram Dialect'].strip(), True, None))
TAGS = {'Dhaka': 'Dhaka', 'Dhaka Old': 'Dhaka', 'Khulna': 'Khulna', 'Rangpur': 'Rangpur', 'Chakma': 'chakma', 'Marma': 'marma', 'Garo': 'garo', 'Santhali': 'santali'}
for ws in openpyxl.load_workbook(REV / 'bangladesh_languages_and_dialects.xlsx', read_only=True).worksheets:
    for r in list(ws.iter_rows(values_only=True))[1:]:
        if not r or not r[1] or not r[2]: continue
        typ = str(r[0]); m = re.search(r'\(([^)]*)\)', typ)
        lang = TAGS.get(m.group(1).strip()) if m else (ws.title if ws.title in DIAL else None)
        if not lang: continue
        forms = [v.strip() for v in re.sub(r'\([^)]*\)', '', str(r[2])).split('/') if v.strip()]
        for i, v in enumerate(forms):
            entries.append(('xlsx', lang, 'Word' if typ.startswith('Word') else 'Sentence', str(r[1]).strip(), v, i == 0, None))
for r in csv.DictReader(open(REV / 'mega_dataset_real_items.csv', encoding='utf-8')):
    entries.append(('mega', r['language'], 'Word' if r['type'] == 'Word' else 'Sentence', r['standard_bangla'], r['dialect'], True, None))
# ---------------- check ----------------
rows = []
for src, lang, kind, std, dia, primary, skip in entries:
    if skip: rows.append([src, lang, kind, std, dia, skip, '', 'No', primary]); continue
    if kind == 'Word' and len(key(std).split()) == 1 and len(key(dia).split()) == 1: v, ev = check_word(lang, key(std), key(dia))
    else: kind = 'Phrase/Sentence' if kind == 'Word' else kind; v, ev = check_sent(lang, std, dia)
    rows.append([src, lang, kind, std, dia, v, ev, '', primary])
# ---------------- apply ----------------
cur = [r for r in csv.reader(open(ROOT / 'curated.csv', encoding='utf-8'))]
langs = sorted({r[1] for r in rows})
files = {}
for lang in langs:
    d = ROOT / 'languages' / lang.lower()
    W = [r for r in csv.reader(open(d / 'words.csv', encoding='utf-8')) if len(r) > 4 and not r[4].startswith(OLD)]
    S = [r for r in csv.reader(open(d / 'sentences.csv', encoding='utf-8')) if len(r) > 6 and not r[6].startswith(OLD)]
    files[lang] = (d, W, S)
for row in rows:
    src, lang, kind, std, dia, verdict, ev, _, primary = row
    if verdict.startswith('Skipped'): continue
    d, W, S = files[lang]; SD = SRC[src]
    if kind == 'Word':
        ok = verdict == 'Confirmed'
        k_dia, k_std = key(dia), key(std)
        if not any(r[1] == k_dia and r[2] == k_std for r in W[1:]):
            W.append([lang, k_dia, k_std, 'corpus_confirmed' if ok else 'needs_native_review', SD, '1', hashlib.sha256(f'chk|{lang}|{k_dia}|{k_std}'.encode()).hexdigest()])
        major = SHARE.get((lang, k_std, k_dia), 0) >= 0.25          # a rare form must not replace the usual one
        use = ok and major and lang in DIAL and k_dia != k_std and not any(r[0] == lang and r[2] == k_std for r in cur[1:])
        if use: cur.append([lang, k_dia, k_std])
        row[7] = 'Yes' if use else ('Dictionary (confirmed, but a less common form)' if ok and not major else 'Dictionary (confirmed)' if ok else 'Dictionary only')
    else:
        sk, bk = key(dia), key(std)
        clash = any(r[8] == '1' and norm(r[5]) == bk and norm(r[3]) != sk for r in S[1:])
        dup = any(norm(r[3]) == sk and norm(r[5]) == bk for r in S[1:])
        # a native speaker's own word (e.g. Mymensingh খাইবাম) must not be overruled by a list sentence
        st_, dt_ = bk.split(), sk.split()
        native = {(r[2], r[1]) for r in W[1:] if r[3] == 'native_speaker_reported'}
        nstd = {a for a, b in native}
        against = any(a in nstd and not any((a, b) in native for b in dt_) for a in st_)
        ok = verdict == 'All words found' and not clash and not against and primary
        if not dup:
            S.append([hashlib.sha256(f'chk|{lang}|{sk}|{bk}'.encode()).hexdigest(), lang, dia, sk, std, bk, SD, 'needs_native_review', '1' if ok else '0', 'train', 'Bengali transliteration' if lang.islower() else 'Bengali', '1'])
            row[7] = 'Yes' if ok else ('Reference only (dataset already has a different translation)' if clash else 'Reference only (a native speaker gave a different word)' if against else 'Reference only (alternative form)' if not primary else 'Reference only')
        else: row[7] = 'Already in dataset'
for lang, (d, W, S) in files.items():
    csv.writer(open(d / 'words.csv', 'w', encoding='utf-8', newline='')).writerows(W)
    csv.writer(open(d / 'sentences.csv', 'w', encoding='utf-8', newline='')).writerows(S)
csv.writer(open(ROOT / 'curated.csv', 'w', encoding='utf-8', newline='')).writerows(cur)
M = list(csv.reader(open(ROOT / 'languages' / 'manifest.csv', encoding='utf-8')))
for r in M[1:]: r[3] = str(sum(1 for _ in open(ROOT / r[2], encoding='utf-8')) - 1)
csv.writer(open(ROOT / 'languages' / 'manifest.csv', 'w', encoding='utf-8', newline='')).writerows(M)
# ---------------- report ----------------
wb = openpyxl.Workbook(); ws = wb.active; ws.title = 'Check report'
ws.append(['Source file', 'Language', 'Type', 'Standard Bangla', 'Given form', 'Result', 'Evidence', 'Used for translation'])
for c in ws[1]: c.font = Font(bold=True)
fill = {'Confirmed': 'C6EFCE', 'All words found': 'C6EFCE', 'Contradicted': 'FFC7CE', 'Skipped': 'FFC7CE', 'No data': 'EDEDED'}
for r in rows:
    r = r[:8]
    ws.append([{'csv': 'chattogram_native_dataset.csv', 'xlsx': 'bangladesh_languages_and_dialects.xlsx', 'mega': 'mega dataset (real part)'}[r[0]]] + r[1:])
    col = next((v for k, v in fill.items() if r[5].startswith(k)), 'FFEB9C')
    ws.cell(ws.max_row, 6).fill = PatternFill('solid', fgColor=col)
for col, w in zip('ABCDEFGH', (30, 12, 16, 26, 26, 34, 70, 26)): ws.column_dimensions[col].width = w
ws.freeze_panes = 'A2'; ws.auto_filter.ref = ws.dimensions
wb.save(REV / 'check_report.xlsx')
c = collections.Counter(re.sub(r'^\d+ of \d+ ', 'some ', re.sub(r' \(occurs.*', '', r[5])) for r in rows)
print(len(rows), 'entries:', dict(c.most_common()))
print('used for translation:', sum(r[7] == 'Yes' for r in rows))
