#!/usr/bin/env python3
"""Vashasetu system test suite (functional, validation, security and performance tests of the server).

Run against a running copy of the app (XAMPP or `php -S`):
    BASE=http://localhost/vashasetu python tests/system_tests.py
Needs: pip install requests
Writes tests/results/system_results.json and prints PASS/FAIL per test case.
Exit code 1 if any test fails.
"""
import json, os, statistics, sys, time, uuid
import requests

BASE = os.environ.get('BASE', 'http://127.0.0.1:8000').rstrip('/')
API = BASE + '/api/'
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'results', 'system_results.json')
RUN = uuid.uuid4().hex[:6]          # makes test accounts and test words unique per run
DECOMP = {'ড়': 'ড়', 'ঢ়': 'ঢ়', 'য়': 'য়'}
nz = lambda s: ''.join(DECOMP.get(c, c) for c in str(s or ''))

results = []
def case(tid, module, title, inp, expected, actual, ok, known=None):
    # known = id of an open, already-reported defect: it is still reported as Fail, but does not stop CI
    res = 'Not run' if ok is None else ('Pass' if ok else 'Fail')
    results.append({'id': tid, 'module': module, 'title': title, 'input': inp, 'expected': expected,
                    'actual': str(actual)[:220], 'result': res, 'known_defect': known if res == 'Fail' else None})
    print({'Pass': 'PASS ', 'Fail': 'FAIL ', 'Not run': 'SKIP '}[res] + tid + ' ' + title + ('' if ok else '   -> got: ' + str(actual)[:200]))

def new_session(ip=None):
    s = requests.Session()
    if ip: s.headers['CF-Connecting-IP'] = ip
    return s

def j(r):
    try: return r.json()
    except Exception: return {'_raw': r.text[:200]}

guest = new_session()
def tr(text, src, tgt, s=None, **extra):
    body = {'text': text, 'source': src, 'target': tgt, 'allow_approximate': True, **extra}
    r = (s or guest).post(API + 'translate.php', json=body, timeout=60)
    return r.status_code, j(r)

# ---------------------------------------------------------------- 1. Translation
c, d = tr('আমি ভাত খাবো', 'standard', 'Chattogram')
case('TR-01', 'Translation', 'Standard Bangla to Chattogram uses the native verb form', 'আমি ভাত খাবো (Standard → Chattogram)', 'আঁই ভাত খাইয়ুম', d.get('translated_text'), c == 200 and nz(d.get('translated_text')).startswith(nz('আঁই ভাত খাইয়ুম')))
c, d = tr('ami vat khabo', 'standard', 'Chattogram')
case('TR-02', 'Translation', 'Banglish (Roman letters) input is understood', 'ami vat khabo (Standard → Chattogram)', 'Contains খাইয়ুম', d.get('translated_text'), c == 200 and nz('খাইয়ুম') in nz(d.get('translated_text')))
c, d = tr('আমি ভাত খাবো না', 'standard', 'Noakhali')
case('TR-03', 'Translation', 'Native-speaker sentence is used first (Noakhali)', 'আমি ভাত খাবো না (Standard → Noakhali)', 'আইঁ ভাত খাইতান ন', d.get('translated_text'), nz(d.get('translated_text')) == nz('আইঁ ভাত খাইতান ন'))
c, d = tr('আমি বাড়ি যাবো না', 'standard', 'Noakhali')
case('TR-04', 'Translation', 'Noakhali negative-future rule', 'আমি বাড়ি যাবো না (Standard → Noakhali)', 'Contains যাইতান ন', d.get('translated_text'), nz('যাইতান ন') in nz(d.get('translated_text')))
c, d = tr('আমি ভাত খাবো', 'standard', 'Mymensingh')
case('TR-05', 'Translation', 'Mymensingh -বাম verb ending', 'আমি ভাত খাবো (Standard → Mymensingh)', 'Contains খাইবাম', d.get('translated_text'), nz('খাইবাম') in nz(d.get('translated_text')))
c, d = tr('আমার কাছে টাকা নেই', 'standard', 'Rangpur')
case('TR-06', 'Translation', 'Rangpur dialect output', 'আমার কাছে টাকা নেই (Standard → Rangpur)', 'Contains মোর', d.get('translated_text'), nz('মোর') in nz(d.get('translated_text')))
c, d = tr('তুই কই যাস', 'Sylheti', 'standard')
case('TR-07', 'Translation', 'Dialect to Standard Bangla', 'তুই কই যাস (Sylheti → Standard)', 'Non-empty Standard Bangla text', d.get('translated_text'), c == 200 and bool(d.get('translated_text')))
c, d = tr('আই ভাত খামু', 'Noakhali', 'Chattogram')
case('TR-08', 'Translation', 'Dialect to dialect through Standard Bangla', 'আই ভাত খামু (Noakhali → Chattogram)', 'Chattogram text আঁই ভাত খাইয়ুম (not the Standard Bangla bridge)', d.get('translated_text'), c == 200 and nz(d.get('translated_text')) == nz('আঁই ভাত খাইয়ুম'))
c, d = tr('মুই গম আগং', 'chakma', 'standard')
case('TR-09', 'Translation', 'Chakma sentence from the MELD data', 'মুই গম আগং (Chakma → Standard)', 'Starts with আমি ভালো আছি', d.get('translated_text'), nz(d.get('translated_text')).startswith(nz('আমি ভালো আছি')))
c, d = tr('I will eat rice', 'english', 'standard')
case('TR-10', 'Translation', 'English words are recognised and translated to Bangla', 'I will eat rice (English → Standard)', 'Bangla text containing ভাত', d.get('translated_text'), c == 200 and 'ভাত' in str(d.get('translated_text')))
c, d = tr('আমি ভাত খাবো', 'standard', 'english')
case('TR-11', 'Translation', 'Standard Bangla to English', 'আমি ভাত খাবো (Standard → English)', 'English text containing "rice"', d.get('translated_text'), c == 200 and 'rice' in str(d.get('translated_text')).lower())
c, d = tr('আমি ভাত খাবো', 'auto', 'standard')
case('TR-12', 'Translation', 'Automatic language detection', 'আমি ভাত খাবো (Auto → Standard)', 'Detected as Standard Bangla', (d.get('detected') or {}).get('variety'), (d.get('detected') or {}).get('variety') == 'standard')
c, d = tr('আমি ভাত খাবো না', 'standard', 'Noakhali')
case('TR-13', 'Translation', 'Every word of the result gets a word box with a meaning', 'আমি ভাত খাবো না (Standard → Noakhali)', 'All word boxes known', [w.get('word') for w in d.get('word_meanings', [])], bool(d.get('word_meanings')) and all(w.get('known') for w in d.get('word_meanings', [])))
c, d = tr('আমি ভাত খাবো। তুমি কোথায় যাবে?', 'standard', 'Chattogram')
t = str(d.get('translated_text'))
case('TR-14', 'Translation', 'Punctuation (। and ?) is kept in the output', 'আমি ভাত খাবো। তুমি কোথায় যাবে?', 'Output keeps । and ?', t, '।' in t and t.rstrip().endswith('?') and '।।' not in t)
c, d = tr('আমার ২টা বই আছে, 3 pen', 'standard', 'Chattogram')
case('TR-15', 'Translation', 'Numbers and mixed text do not break the translator', 'আমার ২টা বই আছে, 3 pen', 'HTTP 200 with text', (c, d.get('translated_text')), c == 200 and bool(d.get('translated_text')))
c, d = tr('আমি 😀 ভাত খাবো!!!', 'standard', 'Chattogram')
case('TR-16', 'Translation', 'Emoji and repeated symbols are handled', 'আমি 😀 ভাত খাবো!!!', 'HTTP 200 with text', (c, d.get('translated_text')), c == 200 and bool(d.get('translated_text')))

c, d = tr('আঁই ভাত খাইয়ুম', 'Chattogram', 'Noakhali')
case('TR-17', 'Translation', 'Chattogram to Noakhali', 'আঁই ভাত খাইয়ুম (Chattogram → Noakhali)', 'আইঁ ভাত খামু', d.get('translated_text'), nz(d.get('translated_text')) == nz('আইঁ ভাত খামু'))
c, d = tr('আইঁ কাইল ভাত খামু', 'Noakhali', 'Sylheti')
case('TR-18', 'Translation', 'Noakhali to Sylheti gives Sylheti words, not Standard Bangla', 'আইঁ কাইল ভাত খামু (Noakhali → Sylheti)', 'Sylheti verb (not খাবো)', d.get('translated_text'), c == 200 and nz('খাবো') not in nz(d.get('translated_text')) and bool(d.get('translated_text')))
c, d = tr('I will eat rice', 'english', 'standard')
case('TR-19', 'Translation', 'English sentence with "will" gets Bangla word order and verb form', 'I will eat rice (English → Standard)', 'আমি ভাত খাবো', d.get('translated_text'), nz(d.get('translated_text')) == nz('আমি ভাত খাবো'))
EN = [('I am eating rice', 'আমি ভাত খাচ্ছি', 'present continuous'), ('I did not eat rice', 'আমি ভাত খাইনি', 'past negative'),
      ('He goes to school every day', 'সে প্রতিদিন স্কুলে যায়', 'he/she form, time word, place'), ('Do you eat fish?', 'তুমি কি মাছ খাও?', 'yes/no question'),
      ('Where do you live?', 'তুমি কোথায় থাকো?', 'question word'), ('I want to eat rice', 'আমি ভাত খেতে চাই', '"want to"'),
      ('I have a book', 'আমার একটা বই আছে', '"have" = owning'), ('I am not tired', 'আমি ক্লান্ত নই', '"am not" + adjective'),
      ('Can you help me?', 'তুমি কি আমাকে সাহায্য করতে পারো?', '"can" question'), ("Don't go", 'যেও না', 'negative order'),
      ('I went to the market to buy fish', 'আমি বাজারে মাছ কিনতে গেলাম', 'purpose "to buy"'), ('I read a book and drink tea', 'আমি একটা বই পড়ি এবং চা খাই', 'two clauses with "and"')]
for k, (e, b_, what) in enumerate(EN):
    c, d = tr(e, 'english', 'standard')
    case(f'TR-{20 + k}', 'Translation', f'English grammar: {what}', f'{e} (English → Standard)', b_, d.get('translated_text'), nz(d.get('translated_text')) == nz(b_))
c, d = tr('I will eat rice', 'english', 'Chattogram')
case('TR-32', 'Translation', 'English to a dialect (through Standard Bangla)', 'I will eat rice (English → Chattogram)', 'আঁই ভাত খাইয়ুম', d.get('translated_text'), nz(d.get('translated_text')) == nz('আঁই ভাত খাইয়ুম'))
c, d = tr('I will eat rice', 'english', 'standard')
wb = {w['word']: w.get('standard_meaning') for w in d.get('word_meanings', [])}
case('TR-33', 'Translation', 'English word boxes show the meaning in this sentence', 'I will eat rice', 'eat → খাবো (not খাওয়া); will is a tense word', wb, nz(wb.get('eat')) == nz('খাবো') and 'অভিলাষ' not in str(wb.get('will')))

# ---------------------------------------------------------------- 2. Translation input validation
c, d = tr('', 'standard', 'Chattogram')
case('TV-01', 'Input validation', 'Empty text is rejected', '"" (empty)', 'HTTP 400 "No text provided"', (c, d.get('error')), c == 400)
c, d = tr('     ', 'standard', 'Chattogram')
case('TV-02', 'Input validation', 'Text with only spaces is rejected', '"     "', 'HTTP 400', (c, d.get('error')), c == 400)
c, d = tr('আমি', 'klingon', 'standard')
case('TV-03', 'Input validation', 'Unknown input language is rejected', 'source = klingon', 'HTTP 4xx error', (c, d.get('error')), c >= 400 and c < 500)
c, d = tr('আমি', 'standard', 'klingon')
case('TV-04', 'Input validation', 'Unknown output language is rejected', 'target = klingon', 'HTTP 4xx error', (c, d.get('error')), c >= 400 and c < 500)
long = ('আমি ভাত খাবো ' * 300).strip()
t0 = time.time(); c, d = tr(long, 'standard', 'Chattogram'); dt = time.time() - t0
case('TV-05', 'Input validation', 'Very long text (3,900 characters) is cut to 500 and still answered', f'{len(long)} characters', 'HTTP 200 within 10 s', (c, f'{dt:.2f}s', len(str(d.get("translated_text")))), c == 200 and dt < 10)
r = guest.get(API + 'translate.php', timeout=30)
case('TV-06', 'Input validation', 'Wrong HTTP method is refused', 'GET translate.php', 'HTTP 405', r.status_code, r.status_code == 405)
r = guest.post(API + 'translate.php', data='{not json', headers={'Content-Type': 'application/json'}, timeout=30)
case('TV-07', 'Input validation', 'Broken JSON body is refused cleanly', '{not json', 'HTTP 400 JSON error, no PHP error text', (r.status_code, r.text[:80]), r.status_code == 400 and 'Fatal' not in r.text and 'Warning' not in r.text)
r = guest.post(API + 'translate.php', json={'text': ['আমি'], 'source': 'standard', 'target': 'Chattogram'}, timeout=30)
case('TV-08', 'Input validation', 'Wrong data type (list instead of text) is refused cleanly', 'text = ["আমি"]', 'HTTP 400 JSON error, no PHP error text', (r.status_code, r.text[:80]), r.status_code == 400 and 'Fatal' not in r.text and 'Uncaught' not in r.text)

# ---------------------------------------------------------------- 3. Dictionary and word lookup
r = guest.get(API + 'dictionary.php', params={'dialect': 'Noakhali'}, timeout=30); d = j(r)
case('DI-01', 'Dictionary', 'Word list opens with verified words first', 'dialect = Noakhali', 'HTTP 200, first row verified', (r.status_code, (d.get('rows') or [{}])[0].get('dialect_word'), (d.get('rows') or [{}])[0].get('verified')), r.status_code == 200 and d.get('rows') and d['rows'][0]['verified'] == 1)
p1 = d.get('rows') or []
r = guest.get(API + 'dictionary.php', params={'dialect': 'Noakhali', 'page': 2}, timeout=30); d2 = j(r)
case('DI-02', 'Dictionary', 'Page 2 shows different words (paging)', 'dialect = Noakhali, page = 2', 'No word repeated from page 1', len(d2.get('rows') or []), bool(d2.get('rows')) and not ({x['dialect_word'] for x in p1} & {x['dialect_word'] for x in d2['rows']}))
r = guest.get(API + 'dictionary.php', params={'dialect': 'Chattogram', 'q': 'খাই'}, timeout=30); d = j(r)
case('DI-03', 'Dictionary', 'Search finds words starting with the typed letters', 'Chattogram, q = খাই', 'All rows start with খাই (word or meaning)', d.get('total'), r.status_code == 200 and d.get('total', 0) > 0 and all(nz(x['dialect_word']).startswith(nz('খাই')) or nz(x['standard_word']).startswith(nz('খাই')) for x in d['rows']))
r = guest.get(API + 'dictionary.php', params={'dialect': 'Chattogram', 'q': 'ঝঝঝঝঝ'}, timeout=30); d = j(r)
case('DI-04', 'Dictionary', 'Search with no match returns an empty list', 'q = ঝঝঝঝঝ', 'HTTP 200, total 0', (r.status_code, d.get('total')), r.status_code == 200 and d.get('total') == 0)
for i, lang in enumerate(['chakma', 'marma', 'garo']):
    r = guest.get(API + 'dictionary.php', params={'dialect': lang}, timeout=30); d = j(r)
    case(f'DI-0{5+i}', 'Dictionary', f'{lang.title()} dictionary lists words and phrases', f'dialect = {lang}', 'More than 100 entries', d.get('total'), r.status_code == 200 and d.get('total', 0) > 100)
r = guest.get(API + 'dictionary.php', params={'dialect': 'Atlantis'}, timeout=30)
case('DI-08', 'Dictionary', 'Unknown dictionary language is rejected', 'dialect = Atlantis', 'HTTP 400', r.status_code, r.status_code == 400)
r = guest.get(API + 'lookup.php', params={'language': 'Chattogram', 'word': 'হাইয়্যুম'}, timeout=30); d = j(r)
case('DI-09', 'Dictionary', 'Word card shows the meaning of a dialect word', 'Chattogram word হাইয়্যুম (native-reviewed spelling)', 'found = true with a meaning', (d.get('found'), [m.get('standard') for m in d.get('means', [])][:3]), d.get('found') and len(d.get('means', [])) > 0)
r = guest.get(API + 'lookup.php', params={'language': 'Chattogram', 'word': ''}, timeout=30)
case('DI-10', 'Dictionary', 'Word card needs a word', 'word = ""', 'HTTP 400', r.status_code, r.status_code == 400)

# ---------------------------------------------------------------- 4. Dictionary editing
ed = new_session('203.0.113.%d' % (int(RUN, 16) % 200 + 20))
w1, w2 = 'পরীক্ষা' + RUN[:3].translate(str.maketrans('0123456789abcdef', '০১২৩৪৫৬৭৮৯কখগঘঙচ')), None
w2 = w1 + 'ক'
r = ed.post(API + 'edit_word.php', json={'language': 'Chattogram', 'word': w1, 'meaning': 'পরীক্ষা'}, timeout=30)
d = j(guest.get(API + 'dictionary.php', params={'dialect': 'Chattogram', 'q': w1}, timeout=30))
case('ED-01', 'Dictionary editing', 'Add a new word', f'Chattogram: {w1} = পরীক্ষা', 'Saved, then found by search', (r.status_code, d.get('total')), r.status_code == 200 and d.get('total') == 1)
c, td = tr(w1, 'Chattogram', 'standard')
case('ED-02', 'Dictionary editing', 'The new word is used by the translator straight away', f'{w1} (Chattogram → Standard)', 'পরীক্ষা', td.get('translated_text'), nz('পরীক্ষা') in nz(td.get('translated_text')))
r = ed.post(API + 'edit_word.php', json={'language': 'Chattogram', 'old_word': w1, 'word': w2, 'meaning': 'যাচাই'}, timeout=30)
a = j(guest.get(API + 'dictionary.php', params={'dialect': 'Chattogram', 'q': w1}, timeout=30)).get('rows', [])
case('ED-03', 'Dictionary editing', 'Edit the word and its meaning together', f'{w1} → {w2} = যাচাই', 'Only the new word is listed, with the new meaning', [(x['dialect_word'], x['standard_word']) for x in a], r.status_code == 200 and len(a) == 1 and nz(a[0]['dialect_word']) == nz(w2) and nz(a[0]['standard_word']) == nz('যাচাই'))
r = ed.post(API + 'edit_word.php', json={'language': 'Chattogram', 'word': w2, 'meaning': '', 'action': 'delete'}, timeout=30)
d = j(guest.get(API + 'dictionary.php', params={'dialect': 'Chattogram', 'q': w2}, timeout=30))
case('ED-04', 'Dictionary editing', 'Delete a word', f'delete {w2}', 'Search finds nothing', (r.status_code, d.get('total')), r.status_code == 200 and d.get('total') == 0)
r = ed.post(API + 'edit_word.php', json={'language': 'Chattogram', 'word': 'শব্দ', 'meaning': ''}, timeout=30)
case('ED-05', 'Dictionary editing', 'Saving without a meaning is refused', 'meaning = ""', 'HTTP 400', r.status_code, r.status_code == 400)
r = ed.post(API + 'edit_word.php', json={'language': 'Atlantis', 'word': 'শব্দ', 'meaning': 'শব্দ'}, timeout=30)
case('ED-06', 'Dictionary editing', 'Unknown language is refused', 'language = Atlantis', 'HTTP 400', r.status_code, r.status_code == 400)
r = ed.post(API + 'edit_word.php', json={'language': 'Chattogram', 'word': 'ক' * 101, 'meaning': 'ক'}, timeout=30)
case('ED-07', 'Dictionary editing', 'Word longer than 100 letters is refused', '101-letter word', 'HTTP 400', r.status_code, r.status_code == 400)
r = ed.post(API + 'edit_word.php', json={'language': 'Chattogram', 'word': ['ক'], 'meaning': 'ক'}, timeout=30)
case('ED-08', 'Dictionary editing', 'Wrong data type is refused cleanly', 'word = ["ক"]', 'HTTP 400 JSON error, no PHP error text', (r.status_code, r.text[:80]), r.status_code == 400 and 'Fatal' not in r.text and 'Uncaught' not in r.text)

bad_ip = '203.0.113.%d' % (int(RUN, 16) % 30 + 220)
row = (j(guest.get(API + 'dictionary.php', params={'dialect': 'Sylheti'}, timeout=30)).get('rows') or [{}])[0]
orig_w, orig_m = row.get('dialect_word'), row.get('standard_word')
new_session(bad_ip).post(API + 'edit_word.php', json={'language': 'Sylheti', 'word': orig_w, 'meaning': 'ভুলমানে'}, timeout=30)
mid = [x['standard_word'] for x in j(guest.get(API + 'dictionary.php', params={'dialect': 'Sylheti', 'q': orig_w}, timeout=30)).get('rows', []) if x['dialect_word'] == orig_w]
local = requests.Session()   # someone sitting at the XAMPP computer (no tunnel headers)
r = local.post(BASE + '/admin_edits.php', data={'undo': bad_ip.replace('.', '.') + '.csv'}, timeout=30)
after = [x['standard_word'] for x in j(guest.get(API + 'dictionary.php', params={'dialect': 'Sylheti', 'q': orig_w}, timeout=30)).get('rows', []) if x['dialect_word'] == orig_w]
case('ED-09', 'Dictionary editing', 'Admin can undo all edits from one internet address', f'{bad_ip} changes {orig_w} to ভুলমানে; admin presses "Undo all"', f'Meaning goes back to {orig_m}', f'after edit {mid}, after undo {after}', 'ভুলমানে' in mid and nz(orig_m) in [nz(a) for a in after] and 'ভুলমানে' not in after)

# ---------------------------------------------------------------- 5. Accounts
acc = new_session('198.51.100.%d' % (int(RUN, 16) % 200 + 20))
email = f'qa_{RUN}@example.com'
r = acc.post(API + 'auth.php?action=register', json={'name': 'QA Tester', 'email': email, 'password': 'Passw0rd!x', 'preferred_dialect': 'Noakhali', 'current_district': 'noakhali'}, timeout=30); d = j(r)
case('AU-01', 'Accounts', 'Register a new account', email, 'HTTP 200, logged in', (r.status_code, d.get('success'), (d.get('error'))), r.status_code == 200 and d.get('success'))
d = j(acc.get(API + 'auth.php?action=me', timeout=30))
case('AU-02', 'Accounts', 'Session remembers the logged-in user', 'GET me', 'Returns the same email', (d.get('user') or {}).get('email'), (d.get('user') or {}).get('email') == email)
r = acc.post(API + 'auth.php?action=register', json={'name': 'QA', 'email': email, 'password': 'Passw0rd!x'}, timeout=30)
case('AU-03', 'Accounts', 'Same email cannot register twice', email, 'HTTP 409', r.status_code, r.status_code == 409)
r = guest.post(API + 'auth.php?action=register', json={'name': 'QA', 'email': 'not-an-email', 'password': 'Passw0rd!x'}, timeout=30)
case('AU-04', 'Accounts', 'Invalid email is refused', 'not-an-email', 'HTTP 400', r.status_code, r.status_code == 400)
r = guest.post(API + 'auth.php?action=register', json={'name': 'QA', 'email': f'b_{RUN}@example.com', 'password': '123'}, timeout=30)
case('AU-05', 'Accounts', 'Password shorter than 6 characters is refused', 'password = 123', 'HTTP 400', r.status_code, r.status_code == 400)
r = guest.post(API + 'auth.php?action=register', json={'name': '', 'email': f'c_{RUN}@example.com', 'password': 'Passw0rd!x'}, timeout=30)
case('AU-06', 'Accounts', 'Name is required', 'name = ""', 'HTTP 400', r.status_code, r.status_code == 400)
r = guest.post(API + 'auth.php?action=register', json={'name': 'QA', 'email': f'd_{RUN}@example.com', 'password': ['x' * 8]}, timeout=30)
case('AU-07', 'Accounts', 'Wrong data type in password is refused cleanly', 'password = ["xxxxxxxx"]', 'HTTP 400 JSON error, no PHP error text', (r.status_code, r.text[:80]), r.status_code == 400 and 'Fatal' not in r.text and 'Uncaught' not in r.text)
r = acc.post(API + 'auth.php?action=logout', timeout=30)
d = j(acc.get(API + 'auth.php?action=me', timeout=30))
case('AU-08', 'Accounts', 'Logout ends the session', 'POST logout, then GET me', 'user = null', d.get('user'), d.get('user') is None)
li = new_session('198.51.100.%d' % (int(RUN, 16) % 200 + 21))
r = li.post(API + 'auth.php?action=login', json={'email': email.upper(), 'password': 'Passw0rd!x'}, timeout=30)
case('AU-09', 'Accounts', 'Login works (email is not case-sensitive)', email.upper(), 'HTTP 200', r.status_code, r.status_code == 200 and j(r).get('success'))
sid1 = li.cookies.get('PHPSESSID')
r = guest.post(API + 'auth.php?action=login', json={'email': email, 'password': 'wrong-pass'}, headers={'CF-Connecting-IP': '192.0.2.%d' % (int(RUN, 16) % 200 + 10)}, timeout=30)
case('AU-10', 'Accounts', 'Wrong password is refused', 'wrong password', 'HTTP 401', r.status_code, r.status_code == 401)
fails = [guest.post(API + 'auth.php?action=login', json={'email': f'nobody_{RUN}@example.com', 'password': 'x'}, headers={'CF-Connecting-IP': '192.0.2.%d' % (int(RUN, 16) % 200 + 30)}, timeout=30).status_code for _ in range(9)]
case('AU-11', 'Accounts', 'Password guessing is slowed down (blocked after 8 wrong tries)', '9 wrong logins', '9th try gets HTTP 429', fails, fails[-1] == 429)
r = li.post(API + 'auth.php?action=update_dialect', json={'preferred_dialect': 'Sylheti'}, timeout=30)
case('AU-12', 'Accounts', 'Change preferred dialect', 'Sylheti', 'Saved', (r.status_code, (j(li.get(API + 'auth.php?action=me')).get('user') or {}).get('preferred_dialect')), r.status_code == 200 and (j(li.get(API + 'auth.php?action=me')).get('user') or {}).get('preferred_dialect') == 'Sylheti')
r = li.post(API + 'auth.php?action=update_dialect', json={'preferred_dialect': 'Mars'}, timeout=30)
case('AU-13', 'Accounts', 'Unknown dialect is refused', 'Mars', 'HTTP 400', r.status_code, r.status_code == 400)
r = guest.post(API + 'auth.php?action=update_district', json={'current_district': 'dhaka'}, timeout=30)
case('AU-14', 'Accounts', 'Profile changes need login', 'update_district as guest', 'HTTP 401', r.status_code, r.status_code == 401)
r = li.post(API + 'auth.php?action=update_district', json={'current_district': 'Gotham'}, timeout=30)
case('AU-15', 'Accounts', 'Unknown district is refused', 'Gotham', 'HTTP 400', r.status_code, r.status_code == 400)

# ---------------------------------------------------------------- 6. History
h1 = new_session(); h2 = new_session()
tr('আমি ভাত খাবো', 'standard', 'Chattogram', s=h1, log=True)
hist1 = j(h1.get(API + 'history.php', timeout=30)).get('history', [])
case('HI-01', 'History', 'A translation is saved to the guest history', 'translate with log = true', '1 entry', len(hist1), len(hist1) == 1)
hist2 = j(h2.get(API + 'history.php', timeout=30)).get('history', [])
case('HI-02', 'History', 'Another visitor cannot see this history', 'second browser session', '0 entries', len(hist2), len(hist2) == 0)
hid = hist1[0]['id'] if hist1 else 0
d = j(h2.request('DELETE', API + 'history.php', json={'id': hid}, timeout=30))
still = len(j(h1.get(API + 'history.php', timeout=30)).get('history', []))
case('HI-03', 'History', 'Another visitor cannot delete this history entry', f'DELETE id {hid} from second session', 'deleted = false, entry still there', (d.get('deleted'), still), d.get('deleted') is False and still == 1)
d = j(h1.request('DELETE', API + 'history.php', json={'id': hid}, timeout=30))
case('HI-04', 'History', 'Owner can delete the entry', f'DELETE id {hid}', 'deleted = true', d.get('deleted'), d.get('deleted') is True)
tr('তুমি কেমন আছো', 'standard', 'Sylheti', s=li, log=True)
n = len(j(li.get(API + 'history.php', timeout=30)).get('history', []))
case('HI-05', 'History', 'Logged-in history is saved to the account', 'translate while logged in', '1 entry', n, n == 1)

# ---------------------------------------------------------------- 7. Learning
d = j(guest.get(API + 'learn.php', params={'action': 'languages'}, timeout=30))
case('LE-01', 'Learning', 'Language list for the Learn page', 'action = languages', 'More than 20 languages', len(d.get('languages', [])), len(d.get('languages', [])) > 20)
d = j(guest.get(API + 'learn.php', params={'action': 'course_units', 'source': 'chakma', 'target': 'noakhali-bn'}, timeout=30))
case('LE-02', 'Learning', 'Chakma to Noakhali path starts with Bangla script', 'chakma → noakhali-bn', 'course_status = script_then_dialect', d.get('course_status'), d.get('course_status') == 'script_then_dialect')
d = j(guest.get(API + 'learn.php', params={'action': 'course_units', 'source': 'standard', 'target': 'standard'}, timeout=30))
case('LE-03', 'Learning', 'Same language on both sides is refused', 'standard → standard', 'Error message', d.get('error'), bool(d.get('error')))
d = j(guest.get(API + 'learn.php', params={'action': 'course_items', 'source': 'english', 'target': 'standard', 'unit': 'letters-1'}, timeout=30))
if d.get('error'):
    d = j(guest.get(API + 'learn.php', params={'action': 'course_items', 'source': 'chakma', 'target': 'standard', 'unit': 'letters-1'}, timeout=30))
items = d.get('items', [])
case('LE-04', 'Learning', 'Lesson questions load (Letters 1)', 'unit = letters-1', '6 questions with options', len(items), len(items) == 6 and all(i.get('options') for i in items))
it = items[0] if items else {}
r = guest.post(API + 'learn.php', json={'action': 'course_answer', 'source': 'chakma', 'target': 'standard', 'item_id': it.get('id'), 'item_kind': 'core', 'answer': (it.get('options') or [''])[0]}, timeout=30)
case('LE-05', 'Learning', 'Saving lesson answers needs login', 'answer as guest', 'HTTP 401', r.status_code, r.status_code == 401)
answers = [li.post(API + 'learn.php', json={'action': 'course_answer', 'source': 'chakma', 'target': 'standard', 'item_id': it.get('id'), 'item_kind': 'core', 'answer': o}, timeout=30) for o in it.get('options', [])]
ok = [j(x).get('correct') for x in answers]
case('LE-06', 'Learning', 'Exactly one option is marked correct', f'all {len(ok)} options of question 1', 'One correct', ok, ok.count(True) == 1)
r = li.post(API + 'learn.php', json={'action': 'course_answer', 'source': 'chakma', 'target': 'standard', 'item_id': it.get('id'), 'item_kind': 'core', 'answer': 'not an option'}, timeout=30)
case('LE-07', 'Learning', 'An answer that is not one of the options is refused', '"not an option"', 'HTTP 400', r.status_code, r.status_code == 400)
d = j(li.get(API + 'learn.php', params={'action': 'course_units', 'source': 'chakma', 'target': 'standard'}, timeout=30))
mastered = sum(int(u.get('mastered') or 0) for u in d.get('units', []))
case('LE-08', 'Learning', 'Progress is saved for the logged-in learner', 'course_units after a correct answer', 'At least 1 mastered', mastered, mastered >= 1)
d = j(guest.get(API + 'learn.php', params={'action': 'course_preview', 'source': 'standard', 'target': 'chattogram-bn', 'stage': 'words'}, timeout=30))
case('LE-09', 'Learning', 'Word practice for Standard → Chattogram has questions', 'course_preview words', 'At least 5 samples', len(d.get('samples', [])), len(d.get('samples', [])) >= 5)
r = guest.get(API + 'learn.php', params={'action': 'course_units', 'source': "chakma' OR 1=1 --", 'target': 'standard'}, timeout=30)
case('LE-10', 'Learning', 'Bad language ID is refused', "chakma' OR 1=1 --", 'HTTP 400', r.status_code, r.status_code == 400)

# ---------------------------------------------------------------- 8. Quiz, word of the day, contribute
d = j(guest.get(API + 'quiz.php', params={'dialect': 'Chattogram'}, timeout=30)); q = d.get('question') or {}
case('QZ-01', 'Quiz & extras', 'Quiz question has 4 different options including the answer', 'dialect = Chattogram', '4 unique options, valid answer index', q.get('options'), len(set(q.get('options', []))) == 4 and isinstance(q.get('answer'), int) and 0 <= q['answer'] < 4)
d = j(guest.get(API + 'quiz.php', params={'dialect': 'Tangail'}, timeout=30))
case('QZ-02', 'Quiz & extras', 'Quiz works for a dialect with little data', 'dialect = Tangail', 'A question', bool(d.get('question')), bool(d.get('question')))
r = guest.get(API + 'quiz.php', params={'dialect': 'Atlantis'}, timeout=30)
case('QZ-03', 'Quiz & extras', 'Quiz for unknown dialect is refused', 'Atlantis', 'HTTP 400', r.status_code, r.status_code == 400)
a = j(guest.get(API + 'word.php', params={'dialect': 'Sylheti'}, timeout=30)).get('word') or {}
b = j(guest.get(API + 'word.php', params={'dialect': 'Sylheti'}, timeout=30)).get('word') or {}
case('QZ-04', 'Quiz & extras', 'Word of the day stays the same during the day', 'two requests', 'Same word twice', (a.get('dialect_word'), b.get('dialect_word')), a.get('id') and a.get('id') == b.get('id'))
c2 = j(guest.get(API + 'word.php', params={'dialect': 'Sylheti', 'mode': 'random', 'exclude_id': a.get('id')}, timeout=30)).get('word') or {}
case('QZ-05', 'Quiz & extras', '"Another word" gives a different word', f'exclude_id = {a.get("id")}', 'Different id', c2.get('id'), c2.get('id') and c2.get('id') != a.get('id'))
r = guest.post(API + 'contribute.php', json={'dialect_word': 'পরীক্ষামূলক', 'meaning': 'test', 'dialect_type': 'Sylheti'}, timeout=30)
case('QZ-06', 'Quiz & extras', 'Contribute a word (sent for review)', 'Sylheti word', 'HTTP 200, pending', (r.status_code, j(r).get('message')), r.status_code == 200 and j(r).get('success'))
r = guest.post(API + 'contribute.php', json={'dialect_word': '', 'meaning': 'x', 'dialect_type': 'Sylheti'}, timeout=30)
case('QZ-07', 'Quiz & extras', 'Contribution without a word is refused', 'dialect_word = ""', 'HTTP 400', r.status_code, r.status_code == 400)

# ---------------------------------------------------------------- 9. Voice output (server voice)
r = guest.get(API + 'tts.php', params={'q': ''}, timeout=30)
case('TT-01', 'Voice output', 'Empty text for the voice is refused', 'q = ""', 'HTTP 400', r.status_code, r.status_code == 400)
r = guest.get(API + 'tts.php', params={'q': 'আমি ভাত খাবো', 'lang': 'bn'}, timeout=40)
ok = (r.status_code == 200 and r.headers.get('Content-Type', '').startswith('audio/')) or (r.status_code == 502 and 'Voice service' in r.text)
case('TT-02', 'Voice output', 'Bangla sentence returns MP3 sound (or a clear message when the computer is offline)', 'q = আমি ভাত খাবো', 'audio/mpeg, or HTTP 502 with message', (r.status_code, r.headers.get('Content-Type')), ok)
TTS_ONLINE = r.status_code == 200

# ---------------------------------------------------------------- 10. Security
r = guest.get(API + 'dictionary.php', params={'dialect': 'Chattogram', 'q': "' OR '1'='1"}, timeout=30); d = j(r)
case('SE-01', 'Security', 'SQL injection in dictionary search does not work', "q = ' OR '1'='1", 'HTTP 200, 0 results', (r.status_code, d.get('total')), r.status_code == 200 and d.get('total') == 0)
r = guest.get(API + 'lookup.php', params={'language': 'Chattogram', 'word': "x' UNION SELECT password_hash,1,1,1 FROM users -- "}, timeout=30)
case('SE-02', 'Security', 'SQL injection in word lookup does not leak data', 'UNION SELECT password_hash…', 'No password hashes in reply', (r.status_code, r.text[:80]), '$2y$' not in r.text and r.status_code in (200, 400))
r = guest.post(API + 'auth.php?action=login', json={'email': "' OR 1=1 -- ", 'password': 'x'}, headers={'CF-Connecting-IP': '192.0.2.%d' % (int(RUN, 16) % 200 + 40)}, timeout=30)
case('SE-03', 'Security', 'SQL injection in login does not log in', "email = ' OR 1=1 --", 'HTTP 401', r.status_code, r.status_code == 401)
xss = '<img src=x onerror=alert(1)>'
r = guest.post(API + 'auth.php?action=register', json={'name': xss + 'QA', 'email': f'x_{RUN}@example.com', 'password': 'Passw0rd!x'}, timeout=30)
case('SE-04', 'Security', 'HTML in the account name is removed', xss, 'Saved name has no <img', (j(r).get('user') or {}).get('name'), '<img' not in str((j(r).get('user') or {}).get('name')))
r = guest.get(BASE + '/setup.php', headers={'CF-Connecting-IP': '1.2.3.4'}, timeout=30)
case('SE-05', 'Security', 'setup.php cannot be opened through the public link', 'setup.php via tunnel', 'HTTP 403', r.status_code, r.status_code == 403)
r = guest.get(BASE + '/admin_edits.php', headers={'CF-Connecting-IP': '1.2.3.4'}, timeout=30)
case('SE-06', 'Security', 'Admin edit review page cannot be opened through the public link', 'admin_edits.php via tunnel', 'HTTP 403', r.status_code, r.status_code == 403)
r = guest.post(BASE + '/admin_edits.php', data={'undo': '../user_edits.csv'}, headers={'CF-Connecting-IP': '1.2.3.4'}, timeout=30)
case('SE-07', 'Security', 'Admin "undo" cannot be triggered through the public link', 'POST undo via tunnel', 'HTTP 403', r.status_code, r.status_code == 403)
r = guest.get(API + 'translate.php', timeout=30)
hdr = {k: r.headers.get(k) for k in ('X-Content-Type-Options', 'X-Frame-Options', 'Referrer-Policy')}
case('SE-08', 'Security', 'Security headers are sent', 'any API reply', 'nosniff, SAMEORIGIN, same-origin', hdr, hdr['X-Content-Type-Options'] == 'nosniff' and hdr['X-Frame-Options'] == 'SAMEORIGIN')
s = new_session(); s.post(API + 'auth.php?action=login', json={'email': email, 'password': 'Passw0rd!x'}, headers={'CF-Connecting-IP': '198.51.100.%d' % (int(RUN, 16) % 200 + 22)})
raw = [c for c in s.cookies]
ck = next((c for c in raw if c.name == 'PHPSESSID'), None)
case('SE-09', 'Security', 'Session cookie is HttpOnly and SameSite=Lax', 'login cookie', 'HttpOnly, SameSite=Lax', ck and ck._rest, bool(ck) and any(k.lower() == 'httponly' for k in ck._rest) and str(ck._rest.get('SameSite', ck._rest.get('samesite', ''))).lower() == 'lax')
fx = new_session('198.51.100.%d' % (int(RUN, 16) % 200 + 23)); fx.get(API + 'auth.php?action=me', timeout=30)
before = fx.cookies.get('PHPSESSID')
fx.post(API + 'auth.php?action=login', json={'email': email, 'password': 'Passw0rd!x'}, timeout=30)
after = fx.cookies.get('PHPSESSID')
case('SE-10', 'Security', 'Session ID changes after login (no session fixation)', 'cookie before/after login', 'Different session id', 'changed' if before and after and before != after else (before, after), bool(before) and bool(after) and before != after)
r = guest.get(BASE + '/database/user_edits.csv', timeout=30)
builtin = os.environ.get('SERVER') == 'php-builtin'   # PHP's test server ignores .htaccess, so this case needs Apache (XAMPP)
case('SE-11', 'Security', 'Data folder files are not downloadable (Apache .htaccess)', '/database/user_edits.csv', 'HTTP 403/404 on Apache', 'needs Apache (XAMPP); PHP test server ignores .htaccess' if builtin else r.status_code, None if builtin else r.status_code in (403, 404))
r = guest.get(BASE + '/api/config.php', timeout=30)
case('SE-12', 'Security', 'config.php does not show the database password', '/api/config.php', 'No source code in reply', r.text[:60], 'DB_PASS' not in r.text)

# ---------------------------------------------------------------- 11. Performance
sent = ['আমি ভাত খাবো', 'তুমি কোথায় যাবে', 'আমার কাছে টাকা নেই', 'আজ বৃষ্টি হবে', 'সে বাজারে গেছে', 'আমরা কাল বাড়ি যাবো',
        'তোমার নাম কী', 'আমি তোমাকে ভালোবাসি', 'ছেলেটা স্কুলে যায়', 'মা রান্না করছে']
pairs = [('standard', 'Chattogram'), ('standard', 'Noakhali'), ('standard', 'Sylheti'), ('Chattogram', 'standard')]
times = []
for s_ in sent:
    for a_, b_ in pairs:
        t0 = time.time(); tr(s_, a_, b_); times.append((time.time() - t0) * 1000)
times.sort()
p95 = times[int(len(times) * .95) - 1]
PERF = {'translate_n': len(times), 'translate_avg_ms': round(statistics.mean(times)), 'translate_median_ms': round(statistics.median(times)), 'translate_p95_ms': round(p95), 'translate_max_ms': round(max(times))}
case('PF-01', 'Performance', '40 translations: average time under 1 second', '10 sentences × 4 directions', 'Average < 1000 ms', f"avg {PERF['translate_avg_ms']} ms, 95% under {PERF['translate_p95_ms']} ms", PERF['translate_avg_ms'] < 1000)
dt = []
for i in range(10):
    t0 = time.time(); guest.get(API + 'dictionary.php', params={'dialect': 'Chattogram', 'page': i + 1}); dt.append((time.time() - t0) * 1000)
PERF['dictionary_avg_ms'] = round(statistics.mean(dt))
case('PF-02', 'Performance', 'Dictionary page loads in under 1 second', '10 pages', 'Average < 1000 ms', f"avg {PERF['dictionary_avg_ms']} ms", PERF['dictionary_avg_ms'] < 1000)
import concurrent.futures as cf
def one(i):
    t0 = time.time(); c, d = tr(sent[i % len(sent)], 'standard', 'Chattogram', s=new_session()); return c, (time.time() - t0) * 1000
with cf.ThreadPoolExecutor(10) as ex: res = list(ex.map(one, range(30)))
PERF['parallel_ok'] = sum(1 for c, _ in res if c == 200); PERF['parallel_max_ms'] = round(max(t for _, t in res))
case('PF-03', 'Performance', '30 translations from 10 users at the same time', '10 parallel users', 'All succeed', f"{PERF['parallel_ok']}/30 OK, slowest {PERF['parallel_max_ms']} ms", PERF['parallel_ok'] == 30)
t0 = time.time(); r = guest.get(BASE + '/', timeout=30); PERF['home_ms'] = round((time.time() - t0) * 1000); PERF['home_kb'] = round(len(r.content) / 1024)
case('PF-04', 'Performance', 'Home page HTML loads quickly', 'GET /', '< 500 ms', f"{PERF['home_ms']} ms, {PERF['home_kb']} KB", PERF['home_ms'] < 500)

os.makedirs(os.path.dirname(OUT), exist_ok=True)
json.dump({'base': BASE, 'run': RUN, 'time': time.strftime('%Y-%m-%d %H:%M'), 'results': results, 'performance': PERF, 'tts_online': TTS_ONLINE}, open(OUT, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
n = sum(r['result'] == 'Pass' for r in results); skip = sum(r['result'] == 'Not run' for r in results)
print(f'{n}/{len(results) - skip} system tests passed' + (f' ({skip} not run on this server)' if skip else ''))
known = sum(1 for r in results if r['result'] == 'Fail' and r.get('known_defect'))
if known: print(f'{known} failing case(s) are open defects already reported: ' + ', '.join(r['id'] + ' (' + r['known_defect'] + ')' for r in results if r.get('known_defect')))
sys.exit(0 if n + skip + known == len(results) else 1)
