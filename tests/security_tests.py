#!/usr/bin/env python3
"""Vashasetu security and brute-force tests.

Run against the app on Apache (XAMPP), because some checks (.htaccess, headers) need Apache:
    BASE=http://localhost/vashasetu python tests/security_tests.py
Optional: ALT_BASE = the same app through the computer's network address (e.g. http://192.168.0.5/vashasetu),
used to check that a visitor on the network cannot fake their internet address.
Needs: pip install requests playwright
Writes tests/results/security_results.json.
"""
import concurrent.futures as cf, http.server, json, os, random, re, socketserver, statistics, string, sys, threading, time, uuid
import requests

BASE = os.environ.get('BASE', 'http://127.0.0.1/vashasetu').rstrip('/')
ALT = os.environ.get('ALT_BASE', '').rstrip('/')
API = BASE + '/api/'
HERE = os.path.dirname(os.path.abspath(__file__))
RUN = uuid.uuid4().hex[:6]
results = []
def case(tid, module, title, inp, expected, actual, ok):
    res = 'Not run' if ok is None else ('Pass' if ok else 'Fail')
    results.append({'id': tid, 'module': module, 'title': title, 'input': inp, 'expected': expected, 'actual': str(actual)[:240], 'result': res})
    print({'Pass': 'PASS ', 'Fail': 'FAIL ', 'Not run': 'SKIP '}[res] + tid + ' ' + title + ('' if ok else '   -> got: ' + str(actual)[:220]))
def j(r):
    try: return r.json()
    except Exception: return {}
_h = int(RUN, 16)
def ip(n): return '100.%d.%d.%d' % (64 + _h % 64, (_h // 64) % 256, n % 250 + 1)   # a fresh made-up address range for every run (as if through the tunnel)
def leak(text):                                 # PHP error text or a file path in a reply
    return bool(re.search(r'(Warning|Notice|Fatal error|Deprecated|Uncaught|Stack trace)\b.*?(on line|in /)|/var/www|C:\\\\xampp|htdocs|PDOException|SQLSTATE', text or '', re.I | re.S))
octet = int(RUN, 16) % 150 + 50

# ====================================================================== 1. Brute force on login
s = requests.Session()
victim = f'victim_{RUN}@example.com'
s.post(API + 'auth.php?action=register', json={'name': 'Victim', 'email': victim, 'password': 'Correct#Horse9'}, headers={'CF-Connecting-IP': ip(octet)}, timeout=30)
codes, times = [], []
for k in range(50):
    t0 = time.time()
    r = requests.post(API + 'auth.php?action=login', json={'email': victim, 'password': 'guess%d' % k}, headers={'CF-Connecting-IP': ip(octet + 1)}, timeout=30)
    codes.append(r.status_code); times.append((time.time() - t0) * 1000)
case('BF-01', 'Brute force', '50 password guesses from one address: blocked after 8', '50 wrong logins, one address', '8 × 401, then 429 only', f'{codes.count(401)} × 401, {codes.count(429)} × 429, avg {statistics.mean(times):.0f} ms', codes[:8] == [401] * 8 and set(codes[8:]) == {429})
r = requests.post(API + 'auth.php?action=login', json={'email': victim, 'password': 'Correct#Horse9'}, headers={'CF-Connecting-IP': ip(octet + 1)}, timeout=30)
case('BF-02', 'Brute force', 'The right password is also refused while the attacker is blocked (no "unlock by luck")', 'correct password from the blocked address', 'HTTP 429', r.status_code, r.status_code == 429)
v2 = f'victim2_{RUN}@example.com'
s.post(API + 'auth.php?action=register', json={'name': 'Victim2', 'email': v2, 'password': 'Correct#Horse9'}, headers={'CF-Connecting-IP': ip(octet + 2)}, timeout=30)
codes = [requests.post(API + 'auth.php?action=login', json={'email': v2, 'password': 'x%d' % k}, headers={'CF-Connecting-IP': '172.%d.%d.%d' % (16 + _h % 16, (_h // 16) % 256, k + 1)}, timeout=30).status_code for k in range(30)]
case('BF-03', 'Brute force', 'Guesses spread over 30 different addresses on one account are still limited', '30 wrong logins, 30 addresses', 'Blocked (429) after the account limit', f'{codes.count(401)} × 401, {codes.count(429)} × 429', codes.count(401) <= 20 and codes[-1] == 429)
codes = [requests.post(API + 'auth.php?action=login', json={'email': f'stuff{k}_{RUN}@example.com', 'password': 'x'}, headers={'CF-Connecting-IP': ip(octet + 3)}, timeout=30).status_code for k in range(20)]
case('BF-04', 'Brute force', 'Credential stuffing (one address, 20 different accounts) is blocked', '20 accounts from one address', '429 after 8', f'{codes.count(401)} × 401, {codes.count(429)} × 429', codes.count(401) == 8 and codes[-1] == 429)
# fast parallel attack: does the server collapse?
def attack(k):
    t0 = time.time()
    try: r = requests.post(API + 'auth.php?action=login', json={'email': f'flood_{RUN}@example.com', 'password': str(k)}, headers={'CF-Connecting-IP': ip(octet + 4)}, timeout=30); c = r.status_code
    except Exception: c = 0
    return c, (time.time() - t0) * 1000
probe = []
def prober(stop):
    while not stop.is_set():
        t0 = time.time()
        try: c = requests.post(API + 'translate.php', json={'text': 'আমি ভাত খাবো', 'source': 'standard', 'target': 'Chattogram'}, timeout=30).status_code
        except Exception: c = 0
        probe.append((c, (time.time() - t0) * 1000)); time.sleep(0.1)
stop = threading.Event(); th = threading.Thread(target=prober, args=(stop,)); th.start()
t0 = time.time()
with cf.ThreadPoolExecutor(40) as ex: res = list(ex.map(attack, range(2000)))
dur = time.time() - t0; stop.set(); th.join()
cs = [c for c, _ in res]
okp = [t for c, t in probe if c == 200]
case('BF-05', 'Brute force', '2,000 login attempts from 40 attackers at once: server keeps working', '2,000 parallel logins', 'No crash (no 5xx/timeouts); attack blocked with 429', f'{dur:.1f} s, {len(cs)/dur:.0f} req/s, codes {{401: {cs.count(401)}, 429: {cs.count(429)}, 5xx/fail: {sum(1 for c in cs if c >= 500 or c == 0)}}}', sum(1 for c in cs if c >= 500 or c == 0) == 0 and cs.count(429) >= 1990)
case('BF-06', 'Brute force', 'Normal users can still translate during the attack', f'{len(probe)} translations during BF-05', 'All succeed, under 2 s each', f'{len(okp)}/{len(probe)} OK, median {statistics.median(okp):.0f} ms, slowest {max(okp):.0f} ms' if okp else 'none', len(okp) == len(probe) and max(okp) < 2000)
# address spoofing: a visitor on the local network must not be able to choose their own "address"
if ALT:
    try:
        codes = [requests.post(ALT + '/api/auth.php?action=login', json={'email': f'spoof{k}_{RUN}@example.com', 'password': 'x'}, headers={'CF-Connecting-IP': '10.9.%d.%d' % (octet, k + 1)}, timeout=30).status_code for k in range(12)]
        case('BF-07', 'Brute force', 'A visitor on the local network cannot avoid the limit by faking the CF-Connecting-IP header', '12 logins (12 accounts) via the network address, a new fake address each time', 'Never more than 8 guesses accepted (fake header ignored)', codes, codes.count(401) <= 8 and codes[-1] == 429)
    except Exception as e:
        case('BF-07', 'Brute force', 'Faked CF-Connecting-IP header is ignored on the local network', 'network address', 'blocked after 8', 'network address not reachable: ' + str(e)[:60], None)
else:
    case('BF-07', 'Brute force', 'Faked CF-Connecting-IP header is ignored on the local network', 'needs ALT_BASE', 'blocked after 8', 'ALT_BASE not set', None)

# ====================================================================== 2. Flooding (spam) protection
def flood(path, body_fn, n, addr):
    return [requests.post(API + path, json=body_fn(k), headers={'CF-Connecting-IP': addr}, timeout=30).status_code for k in range(n)]
codes = flood('edit_word.php', lambda k: {'language': 'Sylheti', 'word': f'স্প্যাম{RUN}{k}', 'meaning': 'স্প্যাম'}, 70, ip(octet + 5))
case('FL-01', 'Flooding', 'One address cannot flood the dictionary with edits', '70 edits in a row from one address', 'Blocked (429) after 60 edits in 10 minutes', f'{codes.count(200)} saved, {codes.count(429)} blocked', codes.count(200) <= 60 and codes[-1] == 429)
for k in range(70): requests.post(API + 'edit_word.php', json={'language': 'Sylheti', 'word': f'স্প্যাম{RUN}{k}', 'meaning': '', 'action': 'delete'}, headers={'CF-Connecting-IP': '10.77.%d.%d' % (octet, k + 1)}, timeout=30)
codes = flood('auth.php?action=register', lambda k: {'name': 'Bot', 'email': f'bot{k}_{RUN}@example.com', 'password': 'Passw0rd!xyz'}, 15, ip(octet + 6))
case('FL-02', 'Flooding', 'One address cannot create unlimited accounts', '15 sign-ups from one address', 'Blocked (429) after 10 per hour', f'{codes.count(200)} created, {codes.count(429)} blocked', codes.count(200) <= 10 and codes[-1] == 429)
codes = flood('contribute.php', lambda k: {'dialect_word': f'শব্দ{k}', 'meaning': 'x', 'dialect_type': 'Sylheti'}, 35, ip(octet + 7))
case('FL-03', 'Flooding', 'One address cannot flood word contributions', '35 contributions from one address', 'Blocked (429) after 30 in 10 minutes', f'{codes.count(200)} accepted, {codes.count(429)} blocked', codes.count(200) <= 30 and codes[-1] == 429)
codes = [requests.get(API + 'tts.php', params={'q': f'পরীক্ষা {RUN} {k}'}, headers={'CF-Connecting-IP': ip(octet + 8)}, timeout=40).status_code for k in range(125)]
case('FL-04', 'Flooding', 'The voice service cannot be abused to make unlimited new sound clips', '125 new sentences from one address', 'Blocked (429) after 120 in 10 minutes', f'{sum(1 for c in codes if c != 429)} served/tried, {codes.count(429)} blocked', codes.count(429) >= 5 and codes[-1] == 429)

# ====================================================================== 3. Injection
SQLI = ["' OR '1'='1", "\" OR \"1\"=\"1", "1' AND SLEEP(3)-- ", "1) OR 1=1-- ", "'; DROP TABLE users; -- ", "' UNION SELECT password_hash,email,1,1,1 FROM users-- ", "%' AND 1=0 UNION SELECT 1,2,3,4,5-- ", "\\", "' OR SLEEP(3)#"]
TARGETS = [('GET', 'dictionary.php', {'dialect': 'Chattogram'}, 'q'), ('GET', 'dictionary.php', {}, 'dialect'), ('GET', 'lookup.php', {'language': 'Chattogram'}, 'word'),
           ('GET', 'lookup.php', {'word': 'আমি'}, 'language'), ('GET', 'quiz.php', {}, 'dialect'), ('GET', 'quiz.php', {'dialect': 'Sylheti'}, 'exclude'),
           ('GET', 'word.php', {}, 'dialect'), ('GET', 'learn.php', {'action': 'course_units', 'target': 'standard'}, 'source'),
           ('POST', 'translate.php', {'source': 'standard', 'target': 'Chattogram'}, 'text'), ('POST', 'translate.php', {'text': 'আমি', 'target': 'standard'}, 'source'),
           ('POST', 'auth.php?action=login', {'password': 'x'}, 'email'), ('POST', 'contribute.php', {'meaning': 'x', 'dialect_type': 'Sylheti'}, 'dialect_word'),
           ('POST', 'edit_word.php', {'language': 'Sylheti', 'meaning': 'x'}, 'word')]
bad, slow, n = [], [], 0
for meth, path, base, field in TARGETS:
    for pl in SQLI:
        n += 1; body = dict(base); body[field] = pl
        h = {'CF-Connecting-IP': ip(octet + 9 + n % 40)}
        t0 = time.time()
        r = requests.get(API + path, params=body, headers=h, timeout=30) if meth == 'GET' else requests.post(API + path, json=body, headers=h, timeout=30)
        dt = time.time() - t0
        if r.status_code >= 500 or leak(r.text) or '$2y$' in r.text: bad.append(f'{path}:{field}={pl[:14]} -> {r.status_code}')
        if dt > 2.5: slow.append(f'{path}:{field} {dt:.1f}s')
        if path == 'edit_word.php' and r.status_code == 200:
            requests.post(API + path, json={'language': 'Sylheti', 'word': pl, 'meaning': '', 'action': 'delete'}, headers={'CF-Connecting-IP': '10.78.%d.%d' % (octet, n % 250 + 1)}, timeout=30)
case('IN-01', 'Injection', f'SQL injection: {n} attack strings in 13 input fields of 8 endpoints', '9 SQL payloads × 13 fields', 'No database error, no data leak, no 500', bad[:4] or 'none', not bad)
case('IN-02', 'Injection', 'Time-based (blind) SQL injection does not delay the server', 'SLEEP(3) payloads', 'No reply slower than 2.5 s', slow[:4] or 'none', not slow)
users_ok = requests.post(API + 'auth.php?action=login', json={'email': victim.replace('victim', 'nobody'), 'password': 'x'}, headers={'CF-Connecting-IP': ip(octet + 60)}, timeout=30).status_code
case('IN-03', 'Injection', 'Tables still exist after "DROP TABLE" payloads', 'login after the attack', 'Users table answers (401)', users_ok, users_ok == 401)
r = requests.get(API + 'dictionary.php', params={'dialect': 'Chattogram', 'q': '%'}, timeout=30); d1 = j(r)
r2 = requests.get(API + 'dictionary.php', params={'dialect': 'Chattogram', 'q': '_'}, timeout=30); d2 = j(r2)
case('IN-04', 'Injection', 'LIKE wildcards (% and _) are searched as letters, not as "match everything"', 'q = % and q = _', '0 results each', (d1.get('total'), d2.get('total')), d1.get('total') == 0 and d2.get('total') == 0)
xs = '<script>alert(1)</script>'
r = requests.get(BASE + '/learn.php', params={'source': xs}, timeout=30, allow_redirects=False)
r2 = requests.get(BASE + '/index.php', params={'q': xs, 'x': '"><svg onload=alert(1)>'}, timeout=30)
case('IN-05', 'Injection', 'Reflected XSS: script in the address bar is not copied into pages', 'learn.php?source=<script>, index.php?q=…', 'Not echoed back', 'echoed' if (xs in r.text or xs in r2.text or 'onload=alert' in r2.text) else 'not echoed', xs not in r.text and xs not in r2.text and 'onload=alert' not in r2.text)
ct = [requests.get(API + p, params=q, timeout=30).headers.get('Content-Type', '') for p, q in [('dictionary.php', {'dialect': 'Chattogram', 'q': xs}), ('lookup.php', {'language': 'Chattogram', 'word': xs})]]
case('IN-06', 'Injection', 'API replies are JSON (browsers never run them as a page)', 'API with <script> input', 'Content-Type application/json + nosniff', ct, all(c.startswith('application/json') for c in ct))
r = requests.post(API + 'auth.php?action=register', json={'name': 'A', 'email': f'mass_{RUN}@example.com', 'password': 'Passw0rd!xyz', 'id': 1, 'is_admin': 1, 'password_hash': 'x'}, headers={'CF-Connecting-IP': ip(octet + 61)}, timeout=30)
case('IN-07', 'Injection', 'Extra fields (id, is_admin, password_hash) in sign-up are ignored', 'register with extra fields', 'New account with its own id; no admin', (r.status_code, (j(r).get('user') or {}).get('id')), r.status_code == 200 and (j(r).get('user') or {}).get('id') not in (1, None))

# ====================================================================== 4. Sessions and passwords
sx = requests.Session(); sx.headers['CF-Connecting-IP'] = ip(octet + 62)
em = f'sess_{RUN}@example.com'
sx.post(API + 'auth.php?action=register', json={'name': 'S', 'email': em, 'password': 'Passw0rd!xyz'}, timeout=30)
old = sx.cookies.get('PHPSESSID')
sx.post(API + 'auth.php?action=logout', timeout=30)
stolen = requests.get(API + 'auth.php?action=me', cookies={'PHPSESSID': old}, timeout=30)
case('SS-01', 'Sessions & passwords', 'After logout the old session cookie no longer works', 'reuse the cookie from before logout', 'user = null', j(stolen).get('user'), j(stolen).get('user') is None)
fake = 'attackerchosen' + RUN
rr = requests.get(API + 'auth.php?action=me', cookies={'PHPSESSID': fake}, timeout=30)
got = rr.cookies.get('PHPSESSID')
case('SS-02', 'Sessions & passwords', 'The server does not accept a session ID made up by an attacker (strict mode)', f'PHPSESSID={fake}', 'Server issues its own new ID', got if got else 'kept the attacker ID', bool(got) and got != fake)
import subprocess
try:
    hv = subprocess.run(['mysql', '-uroot', '-N', '-e', f"SELECT password_hash FROM vashasetu.users WHERE email='{em}'"], capture_output=True, text=True, timeout=20).stdout.strip()
except Exception as e: hv = 'n/a ' + str(e)[:40]
case('SS-03', 'Sessions & passwords', 'Passwords are stored only as bcrypt hashes', 'users table', 'Starts with $2y$, not the password', hv[:7] + '…' if hv else 'not readable', hv.startswith('$2y$') and 'Passw0rd' not in hv)
weak = []
for pw in ['123456', 'password', '12345678', 'qwerty123', 'abc']:
    r = requests.post(API + 'auth.php?action=register', json={'name': 'W', 'email': f'w{len(weak)}_{pw}_{RUN}@example.com', 'password': pw}, headers={'CF-Connecting-IP': ip(octet + 63)}, timeout=30)
    if r.status_code == 200: weak.append(pw)
case('SS-04', 'Sessions & passwords', 'Very weak passwords are refused', '123456, password, 12345678, qwerty123, abc', 'All refused (at least 8 characters, not a common password)', weak or 'all refused', not weak)
a = requests.post(API + 'auth.php?action=login', json={'email': em, 'password': 'Wrong#Pass1'}, headers={'CF-Connecting-IP': ip(octet + 64)}, timeout=30)
b_ = requests.post(API + 'auth.php?action=login', json={'email': 'nobody_' + em, 'password': 'Wrong#Pass1'}, headers={'CF-Connecting-IP': ip(octet + 67)}, timeout=30)
case('SS-05', 'Sessions & passwords', 'Login error does not tell whether an email has an account', 'wrong password vs unknown email', 'Same status and message for both', (a.status_code, j(a).get('error'), b_.status_code, j(b_).get('error')), a.status_code == b_.status_code and j(a).get('error') == j(b_).get('error'))

# ====================================================================== 5. Access control and files
checks = {'/setup.php': 403, '/admin_edits.php': 403}
got = {p: requests.get(BASE + p, headers={'CF-Connecting-IP': '1.2.3.4'}, timeout=30).status_code for p in checks}
case('AC-01', 'Access control', 'Admin pages cannot be opened through the public link', 'setup.php, admin_edits.php via tunnel', 'HTTP 403', got, all(got[p] == 403 for p in checks))
got = {p: requests.get(BASE + p, headers={'X-Forwarded-For': '1.2.3.4'}, timeout=30).status_code for p in checks}
case('AC-02', 'Access control', 'Admin pages also refuse requests that came through any other proxy', 'X-Forwarded-For header', 'HTTP 403', got, all(got[p] == 403 for p in checks))
r = requests.post(BASE + '/admin_edits.php', data={'undo': '../../api/config.php'}, timeout=30)
cfg = os.path.exists('/var/www/html/vashasetu/api/config.php')
case('AC-03', 'Access control', 'Admin "undo" cannot be pointed at another file (path traversal)', 'undo = ../../api/config.php', 'No file outside edits_by_ip is touched', 'config.php intact' if cfg else 'config.php missing', cfg and r.status_code in (200, 403))
paths = ['/database/user_edits.csv', '/database/curated.csv', '/database/schema.sql', '/tools/build.py', '/docs/CHANGELOG_V6.md', '/README.md', '/DATA_SOURCES.txt',
         '/tests/system_tests.py', '/tests/results/system_results.json', '/.github/workflows/ci.yml', '/.git/config', '/.htaccess', '/database/edits_by_ip/', '/api/lib/', '/database/']
got = {p: requests.get(BASE + p, timeout=30).status_code for p in paths}
open_ = {p: c for p, c in got.items() if c == 200}
case('AC-04', 'Access control', 'Data, code, test results, Git and CI files cannot be downloaded', f'{len(paths)} private paths', '403 or 404 for every one', open_ or 'all blocked', not open_)
got = {p: requests.get(BASE + p, timeout=30).text[:200] for p in ['/api/lib/en_grammar.php', '/api/matching_engine.php', '/api/edit_lib.php', '/api/curated_edit.php', '/api/district_registry.php', '/api/word_evidence.php']}
case('AC-05', 'Access control', 'Opening the code library files directly shows nothing (no code, no errors)', 'api/lib/*.php and helper files', 'Empty or 403/404, no PHP error', {k: v[:30] for k, v in got.items() if v.strip()} or 'all empty', not any(leak(v) or '<?php' in v for v in got.values()))
r = requests.request('TRACE', BASE + '/', timeout=30)
case('AC-06', 'Access control', 'HTTP TRACE is switched off', 'TRACE /', '403/405', r.status_code, r.status_code in (403, 405, 501))

# ====================================================================== 6. Headers and information leaks
h = requests.get(BASE + '/', timeout=30).headers
need = {'X-Frame-Options': 'SAMEORIGIN', 'X-Content-Type-Options': 'nosniff', 'Referrer-Policy': 'same-origin'}
miss = {k: h.get(k) for k, v in need.items() if (h.get(k) or '').lower() != v.lower()}
csp = h.get('Content-Security-Policy', '')
case('HD-01', 'Headers & leaks', 'App pages send anti-clickjacking and no-sniff headers', 'GET / (index.php)', 'X-Frame-Options, nosniff, Referrer-Policy, CSP frame-ancestors', miss or ('ok; CSP: ' + csp[:60]), not miss and "frame-ancestors 'self'" in csp)
pp = h.get('Permissions-Policy', '')
case('HD-02', 'Headers & leaks', 'Only this app may use the microphone and camera (Permissions-Policy)', 'GET /', 'microphone=(self), camera=(self)', pp or 'missing', 'microphone=(self)' in pp and 'camera=(self)' in pp)
hh = requests.get(API + 'translate.php', timeout=30).headers
case('HD-03', 'Headers & leaks', 'The PHP version is not announced', 'X-Powered-By header', 'No X-Powered-By', hh.get('X-Powered-By') or 'none', not hh.get('X-Powered-By') and not h.get('X-Powered-By'))
errs = []
probes = [('GET', 'auth.php', {}), ('POST', 'auth.php?action=update_dialect', {'preferred_dialect': ['x']}), ('GET', 'learn.php', {'action': 'course_items'}), ('POST', 'learn.php', {'action': 'course_answer', 'item_id': 'x'}),
          ('GET', 'dictionary.php', {'page': '-5'}), ('GET', 'dictionary.php', {'dialect': 'Chattogram', 'page': '99999999999999999999'}), ('GET', 'word.php', {'n': 'abc', 'exclude_id': '1e99'}),
          ('GET', 'quiz.php', {'exclude': ',,,,a,b'}), ('GET', 'tts.php', {'q': 'ক' * 5000}), ('POST', 'history.php', {}), ('GET', 'lookup.php', {'language': 'Chattogram', 'word': '   '}),
          ('POST', 'translate.php', {'text': 'আমি', 'source': {'a': 1}}), ('POST', 'edit_word.php', {'language': 'Sylheti', 'word': 'ক', 'meaning': 'খ', 'old_word': ['x']})]
for meth, p, q in probes:
    r = requests.get(API + p, params=q, timeout=40) if meth == 'GET' else requests.post(API + p, json=q, timeout=40, headers={'CF-Connecting-IP': ip(octet + 65)})
    if leak(r.text) or r.status_code >= 500 and p != 'tts.php': errs.append(f'{p} {q} -> {r.status_code} {r.text[:60]}')
case('HD-04', 'Headers & leaks', 'Odd inputs never show PHP errors or file paths (with display_errors = On, as in XAMPP)', f'{len(probes)} unusual requests', 'Clean JSON errors only', errs[:3] or 'clean', not errs)

# ====================================================================== 7. Fuzzing (random input)
random.seed(RUN)
alph = string.printable + 'অআইঈউকখগঘচছজঝটঠডঢণতথদধনপফবভমযরলশষসহড়ঢ়য়ৎংঃঁািীুূৃেৈোৌ্।?!😀\u0000\u200d\ufeff'
fails, n = [], 0
for k in range(300):
    txt = ''.join(random.choice(alph) for _ in range(random.randint(1, 120)))
    src = random.choice(['auto', 'standard', 'english', 'Chattogram', 'Sylheti', 'chakma', 'banglish'])
    tgt = random.choice(['standard', 'english', 'Noakhali', 'Rangpur', 'marma'])
    r = requests.post(API + 'translate.php', json={'text': txt, 'source': src, 'target': tgt, 'allow_approximate': True}, timeout=30); n += 1
    if r.status_code >= 500 or leak(r.text) or not r.headers.get('Content-Type', '').startswith('application/json'): fails.append((src, tgt, repr(txt[:20]), r.status_code))
for k in range(100):
    q = ''.join(random.choice(alph) for _ in range(random.randint(0, 40)))
    r = requests.get(API + 'dictionary.php', params={'dialect': random.choice(['Chattogram', 'chakma', 'Tangail']), 'q': q, 'page': random.randint(-3, 500)}, timeout=30); n += 1
    if r.status_code >= 500 or leak(r.text): fails.append(('dict', repr(q[:20]), r.status_code))
case('FZ-01', 'Fuzzing', '400 random inputs (letters, emoji, control characters) to translate and dictionary', '300 translations + 100 searches', 'No 500 errors, always JSON', fails[:3] or f'{n} requests clean', not fails)
bad = []
for body in [b'\xff\xfe\x00bad', b'{"text": "\xed\xa0\x80"}', b'[' * 5000 + b']' * 5000, b'{"text":' + b'"' + 'ক'.encode() * 200000 + b'"}', b'']:
    r = requests.post(API + 'translate.php', data=body, headers={'Content-Type': 'application/json'}, timeout=60)
    if r.status_code >= 500 or leak(r.text): bad.append((body[:12], r.status_code))
case('FZ-02', 'Fuzzing', 'Broken bytes, 5,000-level nested JSON and a 600 KB text are handled', '5 malformed bodies', 'Clean 400/200 replies, no crash', bad or 'clean', not bad)
t0 = time.time(); r = requests.post(API + 'translate.php', data=b'{"text":"' + b'a' * (9 * 1024 * 1024) + b'"}', headers={'Content-Type': 'application/json'}, timeout=60); dt = time.time() - t0
case('FZ-03', 'Fuzzing', 'A 9 MB request (larger than the upload limit) is refused quickly', '9 MB body', 'Refused, no crash, under 5 s', f'{r.status_code} in {dt:.1f} s', r.status_code < 500 and dt < 5 and not leak(r.text))

# ====================================================================== 8. Browser attacks: CSRF, CORS, clickjacking
from playwright.sync_api import sync_playwright
ATT = 8899
target = BASE.replace('localhost', '127.0.0.1')
evil = f'''<!doctype html><html><body>
<form id="f" method="POST" action="{target}/api/auth.php?action=update_dialect" enctype="text/plain"><input name='{{"preferred_dialect":"Tangail","x":"' value='"}}'></form>
<iframe id="fr" src="{target}/" style="width:300px;height:300px"></iframe>
<script>
window.corsResult = 'pending';
fetch('{target}/api/auth.php?action=me', {{credentials: 'include'}}).then(r => r.json()).then(d => window.corsResult = JSON.stringify(d)).catch(e => window.corsResult = 'blocked');
fetch('{target}/api/auth.php?action=update_dialect', {{method: 'POST', credentials: 'include', headers: {{'Content-Type': 'text/plain'}}, body: '{{"preferred_dialect":"Narail"}}'}}).catch(() => {{}});
setTimeout(() => {{ window.corsFinal = window.corsResult; document.getElementById('f').submit(); }}, 2500);
</script></body></html>'''
class H(http.server.SimpleHTTPRequestHandler):
    def do_GET(self):
        self.send_response(200); self.send_header('Content-Type', 'text/html'); self.end_headers(); self.wfile.write(evil.encode())
    def log_message(self, *a): pass
srv = socketserver.TCPServer(('127.0.0.1', ATT), H); threading.Thread(target=srv.serve_forever, daemon=True).start()
with sync_playwright() as pw:
    b = pw.chromium.launch(); ctx = b.new_context(); pg = ctx.new_page()
    pg.goto(target + '/'); pg.wait_for_timeout(500)
    em2 = f'csrf_{RUN}@example.com'
    pg.evaluate(f"fetch('api/auth.php?action=register',{{method:'POST',headers:{{'Content-Type':'application/json','CF-Connecting-IP':'{ip(octet + 66)}'}},body:JSON.stringify({{name:'C',email:'{em2}',password:'Passw0rd!xyz',preferred_dialect:'Sylheti'}})}})")
    pg.wait_for_timeout(800)
    before = pg.evaluate("fetch('api/auth.php?action=me').then(r=>r.json()).then(d=>d.user&&d.user.preferred_dialect)")
    ev = ctx.new_page(); ev.goto(f'http://localhost:{ATT}/'); ev.wait_for_timeout(2000)
    cors = str(ev.evaluate('window.corsResult'))
    framed = False
    for fr in ev.frames:
        if fr != ev.main_frame:
            try: framed = framed or fr.evaluate("!!document.querySelector('#nav')")
            except Exception: pass
    ev.wait_for_timeout(2500)
    after = pg.evaluate("fetch('api/auth.php?action=me').then(r=>r.json()).then(d=>d.user&&d.user.preferred_dialect)")
    b.close()
srv.shutdown()
case('BR-01', 'Browser attacks', 'CSRF: another website cannot change a logged-in user\'s settings', 'evil site auto-submits a form and a fetch to update_dialect', 'Preferred dialect unchanged', f'{before} → {after}', before == after == 'Sylheti')
case('BR-02', 'Browser attacks', 'CORS: another website cannot read the logged-in user\'s data', "evil site fetches auth.php?action=me with credentials", 'Blocked or no user data', cors[:80], cors in ('blocked', 'pending') or '"user":null' in cors)
case('BR-03', 'Browser attacks', 'Clickjacking: another website cannot show the app inside a frame', 'evil site puts the app in an iframe', 'Frame refused (X-Frame-Options / CSP)', 'app shown in frame' if framed else 'frame refused', not framed)

os.makedirs(os.path.join(HERE, 'results'), exist_ok=True)
json.dump({'base': BASE, 'run': RUN, 'time': time.strftime('%Y-%m-%d %H:%M'), 'results': results}, open(os.path.join(HERE, 'results', 'security_results.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
n = sum(r['result'] == 'Pass' for r in results); sk = sum(r['result'] == 'Not run' for r in results)
print(f'{n}/{len(results) - sk} security tests passed' + (f' ({sk} not run)' if sk else ''))
sys.exit(0 if n + sk == len(results) else 1)
