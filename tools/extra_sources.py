"""Loads the extra open datasets (CC BY 4.0 / MIT) into one list of rows:
   {'src': name, 'standard': str, 'Barishal'|'Chattogram'|...: str, 'kind': 'sent'|'word'}"""
import csv, glob, os, re, sys
import openpyxl
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from build import norm
M = os.environ.get('MEND', '/tmp/claude-0/work/mend')
def g(p): 
    h = glob.glob(p, recursive=True); return h[0] if h else None
def clean(s):
    if s is None: return ''
    s = str(s).replace('\xa0', ' ')
    return norm(s)
def xl(p):
    wb = openpyxl.load_workbook(p, read_only=True)
    ws = wb.worksheets[0]
    rows = list(ws.iter_rows(values_only=True)); return rows[0], rows[1:]
def load_all():
    out = []
    def add(src, kind, std, **dl):
        std = clean(std)
        if not std: return
        r = {'src': src, 'kind': kind, 'standard': std}
        ok = False
        for k, v in dl.items():
            v = clean(v)
            if v: r[k] = v; ok = True
        if ok: out.append(r)
    # Dwadash: 12 variations, keep our 5
    p = g(M + '/ds3/**/Dwadash.xlsx')
    if p:
        h, rows = xl(p); ix = {n: i for i, n in enumerate(h)}
        cols = {'Sylheti': 'sylhet_bangla_speech', 'Barishal': 'barishal_bangla_speech', 'Chattogram': 'chittagong_bangla_speech', 'Mymensingh': 'mymensingh_bangla_speech', 'Noakhali': 'noakhali_bangla_speech'}
        for r in rows: add('Dwadash', 'sent', r[ix['bangla_speech']], **{k: r[ix[c]] for k, c in cols.items()})
    # combined 4-dialect corpus
    p = g(M + '/ds6/**/preprocessed_dataset_ready.csv')
    if p:
        for r in csv.DictReader(open(p, encoding='utf-8-sig')):
            add('Combined-CC', 'sent', r['standard_bangla'], Sylheti=r['sylhet'], Chattogram=r['chittagong'], Barishal=r['barishal'], Noakhali=r['noakhali'])
    # ONUBAD
    for nm, kind in (('Sentence', 'sent'), ('Word', 'word'), ('Clause', 'sent')):
        p = g(M + f'/ds1/**/{nm}.xlsx')
        if p:
            h, rows = xl(p)
            for r in rows: add('ONUBAD', kind, r[4], Chattogram=r[1], Sylheti=r[2], Barishal=r[3])
    # Kothon
    p = g(M + '/ds5/**/Chittagonian.xlsx')
    if p:
        h, rows = xl(p)
        for r in rows: add('Kothon', 'sent', r[0], Chattogram=r[2])
    p = g(M + '/ds5/**/Sylheti.xlsx')
    if p:
        h, rows = xl(p)
        for r in rows: add('Kothon', 'sent', r[0], Sylheti=r[2])
    # Chatgaiyya
    p = g(M + '/ds2/**/Dataset_Chittagong_2.0.csv')
    if p:
        for r in list(csv.reader(open(p, encoding='utf-8-sig')))[1:]:
            if len(r) >= 2: add('Chatgaiyya', 'sent', r[0], Chattogram=r[1])
    p = g(M + '/ds2/**/dictionary.csv')
    if p:
        for r in list(csv.reader(open(p, encoding='utf-8-sig')))[1:]:
            if len(r) >= 2: add('Chatgaiyya', 'word', r[0], Chattogram=r[1])
    # Sylheti (Mendeley 5rmskrvh6g)
    p = g(M + '/ds4/**/*v2*.csv')
    if p:
        for r in list(csv.reader(open(p, encoding='utf-8-sig')))[1:]:
            if len(r) >= 2: add('SylhetiDS', 'sent', r[1], Sylheti=r[0])
    p = g(M + '/ds4/**/*v3.csv')
    if p:
        for r in list(csv.reader(open(p, encoding='utf-8-sig')))[1:]:
            if len(r) >= 2: add('SylhetiDS', 'word', r[1], Sylheti=r[0])
    # BD-Dialect v2 (Rahman et al. 2026, CC BY 4.0): 950 words + 950 clauses, native-validated
    for nm, kind in (('Words', 'word'), ('Clauses', 'sent')):
        p = g(M + f'/bd/**/BD-Dialect_{nm}.csv')
        if p:
            for r in list(csv.reader(open(p, encoding='utf-8-sig')))[1:]:
                if len(r) >= 8: add('BD-Dialect', kind, r[1], Noakhali=r[3], Sylheti=r[4], Chattogram=r[5], Mymensingh=r[7], Rajshahi=r[6])
    return out
if __name__ == '__main__':
    import collections
    rows = load_all(); c = collections.Counter(); 
    for r in rows:
        for k in r:
            if k not in ('src', 'kind', 'standard'): c[(r['src'], r['kind'], k)] += 1
    for k, v in sorted(c.items()): print(k, v)
    print(len(rows))
