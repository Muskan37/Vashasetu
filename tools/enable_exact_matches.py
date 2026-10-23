#!/usr/bin/env python3
"""Let the research sentence corpora (Kothon, Dwadash, Vashantor, ONUBAD, SylhetiDS, ChatgaiyyaAlap, ...)
give exact sentence translations, but only where that is safe:
  * the dialect sentence has exactly one Standard Bangla translation, and the Standard sentence has exactly
    one dialect translation, across every dataset (pairs where datasets disagree are skipped);
  * it does not clash with a sentence that is already used for exact matches;
  * 2-25 words, Bangla script, no list numbering;
  * it does not overrule a word a native speaker confirmed (e.g. Noakhali খাবো = খামু).
Run after tools/import_checked_lists.py, then open setup.php?reset=1."""
import csv, re, glob, collections, pathlib
ROOT = pathlib.Path(__file__).resolve().parent.parent / 'database'
DECOMP = {'ড়': 'ড়', 'ঢ়': 'ঢ়', 'য়': 'য়', '‌': '', '‍': ''}
key = lambda s: re.sub(r'\s+', ' ', re.sub(r'[?!।.,;:"“”\'()\[\]‘’—\-]+', ' ', ''.join(DECOMP.get(c, c) for c in s))).strip()
RESEARCH = ('Kothon', 'Dwadash', 'Vashantor', 'ONUBAD', 'SylhetiDS', 'Chatgaiyya', 'Safin-2025', 'Combined-CC')
DIAL = ['barishal', 'chattogram', 'mymensingh', 'noakhali', 'rajshahi', 'sylheti']
total = collections.Counter()
# word pairs the learned lexicon knows, per dialect: used to check that a sentence pair really lines up
LEX = collections.defaultdict(set)
for r in csv.DictReader(open(ROOT / 'lexicon.csv', encoding='utf-8')):
    LEX[r['dialect'].lower()].add((key(r['dialect_word']), key(r['standard_word'])))
def lines_up(lang, dt, st):
    if abs(len(dt) - len(st)) > max(1, len(st) // 4): return False          # very different length: likely not a translation
    if len(dt) == len(st):
        good = sum(1 for a, b in zip(dt, st) if a == b or (a, b) in LEX[lang])
    else:
        good = sum(1 for b in st if b in dt or any((a, b) in LEX[lang] for a in dt))
    return good >= 0.6 * len(st)
for lang in DIAL:
    d = ROOT / 'languages' / lang
    S = list(csv.reader(open(d / 'sentences.csv', encoding='utf-8')))
    H, R = S[0], S[1:]
    W = list(csv.DictReader(open(d / 'words.csv', encoding='utf-8')))
    native = collections.defaultdict(set)
    for w in W:
        if w['meaning_status'] == 'native_speaker_reported': native[key(w['standard_word'])].add(key(w['source_word']))
    s2t, t2s = collections.defaultdict(set), collections.defaultdict(set)
    for r in R:
        s2t[key(r[3])].add(key(r[5])); t2s[key(r[5])].add(key(r[3]))
    seen = set()
    for r in R:
        if r[8] == '1' and r[6] in RESEARCH: r[8] = '0'   # re-run: decide again
    fixed_s = {key(r[3]) for r in R if r[8] == '1'}; fixed_t = {key(r[5]) for r in R if r[8] == '1'}
    fixed_t |= {key(r[5]) for r in R if r[7] == 'native_speaker_reported'}; fixed_s |= {key(r[3]) for r in R if r[7] == 'native_speaker_reported'}
    for r in R:
        if r[8] == '1': continue
        sk, tk = key(r[3]), key(r[5])
        why = None
        if len(s2t[sk]) != 1 or len(t2s[tk]) != 1: why = 'datasets disagree'
        elif sk in fixed_s or tk in fixed_t: why = 'already covered'
        elif not (2 <= len(sk.split()) <= 25 and 2 <= len(tk.split()) <= 25): why = 'length'
        elif re.search(r'[0-9০-৯A-Za-z]', sk + tk): why = 'numbers or Latin letters'
        elif re.search(r'[^\u0980-\u09FF\s]', sk + tk): why = 'other scripts mixed in'
        elif (sk, tk) in seen: why = 'duplicate'
        else:
            st, dt = tk.split(), sk.split()
            if any(a in native and not (native[a] & set(dt)) for a in st): why = 'overrules a native speaker word'
            elif not lines_up(lang, dt, st): why = 'words do not line up'
        total[why or 'enabled'] += 1
        if why is None: r[8] = '1'; seen.add((sk, tk))
    csv.writer(open(d / 'sentences.csv', 'w', encoding='utf-8', newline='')).writerows([H] + R)
    print(lang, 'enabled', sum(1 for r in R if r[8] == '1'))
print(dict(total))
