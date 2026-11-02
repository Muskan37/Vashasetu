#!/usr/bin/env python3
"""Automated API tests for Vashasetu. Run against a running app:
    BASE=http://localhost/vashasetu python tests/api_tests.py
Each test prints PASS/FAIL; the script exits with code 1 if any test fails (used by CI)."""
import json, os, sys, time, urllib.request, urllib.parse, http.cookiejar
BASE = os.environ.get('BASE', 'http://127.0.0.1:8000').rstrip('/') + '/api/'
jar = http.cookiejar.CookieJar(); op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
def call(path, body=None, headers=None, **q):
    url = BASE + path + ('?' + urllib.parse.urlencode(q) if q else '')
    h = {'Content-Type': 'application/json', **(headers or {})}
    rq = urllib.request.Request(url, json.dumps(body).encode() if body is not None else None, h)
    try:
        r = op.open(rq, timeout=60); return r.status, json.loads(r.read() or b'{}')
    except urllib.error.HTTPError as e:
        try: return e.code, json.loads(e.read() or b'{}')
        except Exception: return e.code, {}
results = []
CI_EMAIL = 'ci_%d@example.com' % int(time.time())   # new account each run, so earlier runs cannot block the login test
# the app stores য়/ড়/ঢ় as two code points; compare texts in that same form
DECOMP = {'\u09dc': '\u09a1\u09bc', '\u09dd': '\u09a2\u09bc', '\u09df': '\u09af\u09bc'}
nz = lambda s: ''.join(DECOMP.get(c, c) for c in str(s or ''))
def test(tid, name, cond):
    results.append((tid, name, bool(cond))); print(('PASS ' if cond else 'FAIL ') + tid + ' ' + name)
tr = lambda t, s, g: call('translate.php', {'text': t, 'source': s, 'target': g, 'allow_approximate': True})
c, d = tr('আমি ভাত খাবো', 'standard', 'Chattogram'); test('T01', 'Standard -> Chattogram uses the native form', c == 200 and nz(d.get('translated_text')).startswith(nz('আঁই ভাত খাইয়ুম')))
c, d = tr('ami vat khabo', 'standard', 'Chattogram'); test('T02', 'Banglish input is transliterated', c == 200 and nz('খাইয়ুম') in nz(d.get('translated_text')))
c, d = tr('আমি ভাত খাবো না', 'standard', 'Noakhali'); test('T03', 'Native-speaker sentence wins (Noakhali)', nz(d.get('translated_text')) == nz('আইঁ ভাত খাইতান ন'))
c, d = tr('আমি বাড়ি যাবো না', 'standard', 'Noakhali'); test('T04', 'Noakhali negative-future rule', nz('যাইতান ন') in nz(d.get('translated_text')))
c, d = tr('আমি ভাত খাবো', 'standard', 'Mymensingh'); test('T05', 'Mymensingh -বাম verb form', nz('খাইবাম') in nz(d.get('translated_text')))
c, d = tr('আই ভাত খামু', 'Noakhali', 'Chattogram'); test('T06', 'Dialect -> dialect via Standard', c == 200 and d.get('translated_text', '') != '')
c, d = tr('তুই কই যাস', 'Sylheti', 'standard'); test('T07', 'Dialect -> Standard returns text', c == 200 and d.get('translated_text', '') != '')
c, d = tr('মুই গম আগং', 'chakma', 'standard'); test('T08', 'Chakma exact sentence (MELD)', nz(d.get('translated_text')).startswith(nz('আমি ভালো আছি')))
c, d = tr('আমি ভাত খাবো', 'auto', 'standard'); test('T09', 'Auto-detect keeps Standard as Standard', d.get('detected', {}).get('variety') == 'standard')
c, d = tr('আমি', 'klingon', 'standard'); test('T10', 'Unsupported language is rejected', c >= 400)
c, d = tr('আমার কাছে টাকা নেই', 'standard', 'Rangpur'); test('T11', 'New Dwadash dialect (Rangpur) translates', nz('মোর') in nz(d.get('translated_text')))
c, d = tr('আমি ভাত খাবো না', 'standard', 'Noakhali'); test('T12', 'Word boxes give a meaning for every word', all(w.get('known') for w in d.get('word_meanings', [])))
c, d = call('dictionary.php', dialect='Noakhali'); test('T13', 'Dictionary lists verified words first', c == 200 and d['rows'] and d['rows'][0]['verified'] == 1)
c, d = call('dictionary.php', dialect='chakma'); test('T14', 'Indigenous dictionary includes phrases', c == 200 and d.get('total', 0) > 500)
c, d = call('quiz.php', dialect='Tangail'); test('T15', 'Quiz works for a low-data dialect', c == 200 and d.get('question'))
c, d = call('edit_word.php', {'language': 'Chattogram', 'word': 'পরীক্ষাশব্দ', 'meaning': 'পরীক্ষা'}, headers={'CF-Connecting-IP': '203.0.113.7'})
c2, d2 = call('dictionary.php', dialect='Chattogram', q='পরীক্ষাশব্দ')
test('T16', 'Dictionary edit is saved and listed', c == 200 and d2.get('total') == 1)
call('edit_word.php', {'language': 'Chattogram', 'word': 'পরীক্ষাশব্দ', 'meaning': '', 'action': 'delete'}, headers={'CF-Connecting-IP': '203.0.113.7'})
c, d = call('auth.php?action=register', {'name': 'CI User', 'email': CI_EMAIL, 'password': 'Passw0rd!x'})
c, d = call('auth.php?action=login', {'email': CI_EMAIL, 'password': 'Passw0rd!x'}); test('T17', 'Register + login', c == 200 and d.get('success'))
fails = [call('auth.php?action=login', {'email': CI_EMAIL, 'password': 'wrong'}, headers={'CF-Connecting-IP': '198.51.100.9'})[0] for _ in range(9)]
test('T18', 'Password guessing is throttled (429 after 8 tries)', fails[-1] == 429)
c, d = call('learn.php?action=course_units&source=chakma&target=noakhali-bn'); test('T19', 'Learn path for Chakma -> Noakhali starts with Bangla script', d.get('course_status') == 'script_then_dialect')
try:
    urllib.request.urlopen(urllib.request.Request(BASE.replace('/api/', '/setup.php'), headers={'CF-Connecting-IP': '1.2.3.4'}), timeout=30); blocked = False
except urllib.error.HTTPError as e: blocked = e.code == 403
test('T20', 'setup.php is blocked through the public tunnel', blocked)
n = sum(ok for *_, ok in results); print(f'{n}/{len(results)} tests passed'); sys.exit(0 if n == len(results) else 1)
