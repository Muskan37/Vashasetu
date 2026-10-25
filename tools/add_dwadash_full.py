#!/usr/bin/env python3
"""Use the whole Dwadash dataset (data.mendeley.com/datasets/v9cf66fk2t).
The first build took only Chittagong, Sylhet, Barishal, Mymensingh and Noakhali. This adds the other columns:
Rajshahi (to the existing dialect) and Rangpur, Narail, Kishoreganj, Narsingdi, Tangail (new dialects).
For each of them it
  * adds the sentence pairs to database/languages/<dialect>/sentences.csv (source "Dwadash"),
  * learns a word list (IBM Model 1, both directions, same thresholds as tools/gen.py) into database/lexicon.csv,
  * adds word counts to database/vocab.csv (used to recognise the dialect).
Usage: python tools/add_dwadash_full.py path/to/Dwadash.xlsx
Then run tools/enable_exact_matches.py and open setup.php?reset=1."""
import csv, re, sys, hashlib, collections, pathlib, openpyxl
ROOT = pathlib.Path(__file__).resolve().parent.parent / 'database'
DECOMP = {'ড়': 'ড়', 'ঢ়': 'ঢ়', 'য়': 'য়', '‌': '', '‍': ''}
key = lambda s: re.sub(r'\s+', ' ', re.sub(r'[?!,.;:।"\'()\[\]‘’“”—\-]+', ' ', ''.join(DECOMP.get(c, c) for c in str(s or '')))).strip()
COLS = {'Rajshahi': 'rajshahi_bangla_speech', 'Rangpur': 'rangpur_bangla_speech', 'Narail': 'narail_bangla_speech',
        'Kishoreganj': 'kishorgonj_bangla_speech', 'Narsingdi': 'narsingdi_bangla_speech', 'Tangail': 'tangail_bangla_speech'}
def lev(a, b):
    p = list(range(len(b) + 1))
    for i, x in enumerate(a, 1):
        c = [i]
        for j, y in enumerate(b, 1): c.append(min(p[j] + 1, c[j - 1] + 1, p[j - 1] + (x != y)))
        p = c
    return p[-1]
def sim(a, b):
    m = max(len(a), len(b)); return 1 - lev(a, b) / m if m else 1
def model1(pairs, iters=10):
    t = collections.defaultdict(dict)
    for s, g in pairs:
        for a in s + ['<N>']:
            for b in g:
                if b not in t[a]: t[a][b] = (1 + 6 * sim(a, b) ** 2) if a != '<N>' else 0.5
    for a in t:
        z = sum(t[a].values())
        for b in t[a]: t[a][b] /= z
    for _ in range(iters):
        cnt = collections.defaultdict(lambda: collections.defaultdict(float)); tot = collections.defaultdict(float)
        for s, g in pairs:
            ss = s + ['<N>']
            for b in g:
                z = sum(t[a][b] for a in ss)
                for a in ss:
                    c = t[a][b] / z; cnt[a][b] += c; tot[a] += c
        for a in cnt:
            for b in cnt[a]: t[a][b] = cnt[a][b] / tot[a]
    return t
wb = openpyxl.load_workbook(sys.argv[1], read_only=True); rows = list(wb.active.iter_rows(values_only=True))
H = {n: i for i, n in enumerate(rows[0])}
lex = list(csv.reader(open(ROOT / 'lexicon.csv', encoding='utf-8')))
voc = list(csv.reader(open(ROOT / 'vocab.csv', encoding='utf-8')))
for dialect, col in COLS.items():
    folder = ROOT / 'languages' / dialect.lower(); folder.mkdir(parents=True, exist_ok=True)
    sf = folder / 'sentences.csv'; wf = folder / 'words.csv'
    head = ['pair_hash', 'language', 'source_text', 'source_key', 'standard_bangla', 'standard_key', 'source_dataset', 'review_status', 'eligible_exact', 'split', 'script', 'source_count']
    S = list(csv.reader(open(sf, encoding='utf-8'))) if sf.exists() else [head]
    S = [S[0]] + [r for r in S[1:] if r[6] != 'Dwadash']           # re-run safe
    if not wf.exists():
        csv.writer(open(wf, 'w', encoding='utf-8', newline='')).writerow(['language', 'source_word', 'standard_word', 'meaning_status', 'source_dataset', 'evidence_count', 'hash'])
    have = {(r[3], r[5]) for r in S[1:]}; added = 0
    for r in rows[1:]:
        std, dia = r[H['bangla_speech']], r[H[col]]
        if not std or not dia: continue
        sk, bk = key(dia), key(std)
        if not sk or not bk or (sk, bk) in have: continue
        have.add((sk, bk)); h = hashlib.sha256(f'dw|{dialect}|{sk}|{bk}'.encode()).hexdigest()
        split = ['train'] * 8 + ['validation', 'test']; split = split[int(h[:4], 16) % 10]
        S.append([h, dialect, str(dia).strip(), sk, str(std).strip(), bk, 'Dwadash', 'needs_native_review', '0', split, 'Bengali', '1']); added += 1
    csv.writer(open(sf, 'w', encoding='utf-8', newline='')).writerows(S)
    # learn the word list from every pair this dialect has (all sources), like tools/gen.py
    pairs = [(r[3].split(), r[5].split()) for r in S[1:] if r[3] and r[5] and 'unreliable' not in r[6]]
    f = model1(pairs); g = model1([(b, a) for a, b in pairs])
    cnt = collections.Counter(w for a, b in pairs for w in a)
    P = {}
    for a, c in f.items():
        if a == '<N>': continue
        for rank, (b, p) in enumerate(sorted(c.items(), key=lambda x: -x[1])[:2]):
            if p >= 0.25 and (rank == 0 or p >= 0.3): P[(a, b)] = [p, 0]
    for s_, c in g.items():
        if s_ == '<N>': continue
        for a, p in sorted(c.items(), key=lambda x: -x[1])[:2]:
            if p >= 0.25: P.setdefault((a, s_), [0, 0])[1] = p
    verified = {(r[1], r[2]): r for r in lex[1:] if r[0] == dialect and r[6] == '1'}
    lex = [lex[0]] + [r for r in lex[1:] if r[0] != dialect or r[6] == '1']
    for (a, b), (p, q) in P.items():
        if (a, b) in verified: continue
        lex.append([dialect, a, b, round(p, 3), round(q, 3), cnt[a], 0])
    # dictionary: the clearest learned pairs (different spelling, seen at least 3 times, strong both ways), marked unverified
    W = list(csv.reader(open(wf, encoding='utf-8')))
    W = [W[0]] + [r for r in W[1:] if r[4] != 'Learned from Dwadash (unverified)']
    known = {(r[1], r[2]) for r in W[1:]}
    for (a, b), (p, q) in P.items():
        if a != b and p >= 0.6 and q >= 0.4 and cnt[a] >= 3 and (a, b) not in known:
            W.append([dialect, a, b, 'needs_native_review', 'Learned from Dwadash (unverified)', str(cnt[a]), hashlib.sha256(f'dwl|{dialect}|{a}|{b}'.encode()).hexdigest()])
    csv.writer(open(wf, 'w', encoding='utf-8', newline='')).writerows(W)
    voc = [voc[0]] + [r for r in voc[1:] if r[0] != dialect]
    vc = collections.Counter(w for a, b in pairs for w in a)
    voc += [[dialect, w, n] for w, n in vc.items()]
    print(f'{dialect}: +{added} Dwadash pairs, {len(S) - 1} sentence pairs in total, {len(P)} learned word pairs')
csv.writer(open(ROOT / 'lexicon.csv', 'w', encoding='utf-8', newline='')).writerows(lex)
csv.writer(open(ROOT / 'vocab.csv', 'w', encoding='utf-8', newline='')).writerows(voc)
# manifest: one sentences + one words entry per dialect
M = list(csv.reader(open(ROOT / 'languages' / 'manifest.csv', encoding='utf-8')))
present = {(r[0], r[1]) for r in M[1:]}
for dialect in COLS:
    for ds, fn in (('parallel_sentences', 'sentences'), ('word_meanings', 'words')):
        if (dialect, ds) not in present: M.append([dialect, ds, f'languages/{dialect.lower()}/{fn}.csv', '0', 'research candidates; validation status in each row'])
for r in M[1:]: r[3] = str(sum(1 for _ in open(ROOT / r[2], encoding='utf-8')) - 1)
csv.writer(open(ROOT / 'languages' / 'manifest.csv', 'w', encoding='utf-8', newline='')).writerows(M)
