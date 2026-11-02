#!/usr/bin/env python3
"""Vashasetu reliability and data-integrity tests.

What happens when MySQL stops, when many people edit at once, and whether edits survive setup.php?reset=1.
Run on the computer that runs the app (it stops and starts MySQL):
    BASE=http://localhost/vashasetu APP_DIR=C:/xampp/htdocs/vashasetu python tests/reliability_tests.py
On Windows/XAMPP set MYSQL_STOP / MYSQL_START to the commands that stop and start MySQL, or the DB tests are skipped.
Writes tests/results/reliability_results.json.
"""
import concurrent.futures as cf, csv, json, os, subprocess, sys, time, uuid
import requests

BASE = os.environ.get('BASE', 'http://127.0.0.1/vashasetu').rstrip('/')
API = BASE + '/api/'
APP = os.environ.get('APP_DIR', '/var/www/html/vashasetu')
STOP = os.environ.get('MYSQL_STOP', 'mysqladmin -uroot shutdown')
START = os.environ.get('MYSQL_START', 'setsid nohup mysqld_safe >/dev/null 2>&1 &')
HERE = os.path.dirname(os.path.abspath(__file__))
RUN = uuid.uuid4().hex[:6]
results = []
def case(tid, module, title, inp, expected, actual, ok):
    res = 'Not run' if ok is None else ('Pass' if ok else 'Fail')
    results.append({'id': tid, 'module': module, 'title': title, 'input': inp, 'expected': expected, 'actual': str(actual)[:240], 'result': res})
    print({'Pass': 'PASS ', 'Fail': 'FAIL ', 'Not run': 'SKIP '}[res] + tid + ' ' + title + ('' if ok else '   -> got: ' + str(actual)[:200]))
def tr(t='আমি ভাত খাবো'):
    try:
        r = requests.post(API + 'translate.php', json={'text': t, 'source': 'standard', 'target': 'Chattogram'}, timeout=20); return r.status_code, r.json()
    except Exception as e: return 0, {'error': str(e)}
def rows(path):
    try:
        with open(path, encoding='utf-8', newline='') as f: return list(csv.reader(f))
    except Exception: return None
_h = int(RUN, 16)
def ip(n): return '100.%d.%d.%d' % (128 + _h % 64, (_h // 64) % 256, n % 250 + 1)

# ---------------------------------------------------------------- 1. concurrent dictionary edits
cur = os.path.join(APP, 'database', 'curated.csv'); log = os.path.join(APP, 'database', 'user_edits.csv')
before_cur = rows(cur); before_log = rows(log) or []
words = [f'একসাথে{RUN}{k}' for k in range(40)]
means = [f'পরীক্ষা{RUN}{k}' for k in range(40)]   # different meanings: the trusted list keeps one dialect word per meaning
def edit(k):
    return requests.post(API + 'edit_word.php', json={'language': 'Sylheti', 'word': words[k], 'meaning': means[k]}, headers={'CF-Connecting-IP': ip(k)}, timeout=30).status_code
with cf.ThreadPoolExecutor(20) as ex: codes = list(ex.map(edit, range(40)))
after_cur = rows(cur); after_log = rows(log) or []
in_cur = sum(1 for r in (after_cur or []) if len(r) >= 2 and r[1] in words)
lost = (len(before_cur) - 1 + 40) - (len(after_cur) - 1) if before_cur and after_cur else None
case('RL-01', 'Data integrity', '40 people add a word at the same moment: no edit is lost from the trusted list', '40 parallel edits (20 at once)', 'All 40 in curated.csv; nothing else removed', f'{codes.count(200)} saved, {in_cur}/40 in curated.csv, rows before {len(before_cur or []) - 1} → after {len(after_cur or []) - 1}', codes.count(200) == 40 and in_cur == 40 and lost == 0)
new_lines = after_log[len(before_log):]
good = sum(1 for r in new_lines if len(r) == 9 and r[1] in words)
case('RL-02', 'Data integrity', 'The edit log stays readable when 40 edits arrive together (no mixed lines)', 'user_edits.csv after RL-01', '40 new complete lines (9 columns each)', f'{good} complete of {len(new_lines)} new lines', good == 40 and len(new_lines) == 40)
d = requests.get(API + 'dictionary.php', params={'dialect': 'Sylheti', 'q': 'একসাথে' + RUN}, timeout=30).json()
case('RL-03', 'Data integrity', 'Database and files agree after the parallel edits', 'dictionary search', '40 words found', d.get('total'), d.get('total') == 40)
# ---------------------------------------------------------------- 2. edits survive a reset
r = requests.get(BASE + '/setup.php', params={'reset': 1}, timeout=300)
d = requests.get(API + 'dictionary.php', params={'dialect': 'Sylheti', 'q': 'একসাথে' + RUN}, timeout=30).json()
case('RL-04', 'Data integrity', 'Dictionary edits survive setup.php?reset=1 (they are re-applied from user_edits.csv)', 'reset after RL-01', '40 words still there', f"setup {r.status_code} ({'Done' if 'Done' in r.text else 'no Done'}), {d.get('total')} words", r.status_code == 200 and 'Done' in r.text and d.get('total') == 40)
for k in range(40): requests.post(API + 'edit_word.php', json={'language': 'Sylheti', 'word': words[k], 'meaning': '', 'action': 'delete'}, headers={'CF-Connecting-IP': ip(100 + k)}, timeout=30)
d = requests.get(API + 'dictionary.php', params={'dialect': 'Sylheti', 'q': 'একসাথে' + RUN}, timeout=30).json()
case('RL-05', 'Data integrity', 'Deleting the 40 test words removes them everywhere', '40 deletes', '0 words left, curated.csv back to its size', f"{d.get('total')} left, curated rows {len(rows(cur) or []) - 1} (before {len(before_cur or []) - 1})", d.get('total') == 0 and len(rows(cur) or []) == len(before_cur or []))
# ---------------------------------------------------------------- 3. MySQL stops
can = os.environ.get('SKIP_DB_STOP') != '1'
if can:
    subprocess.run(STOP, shell=True, capture_output=True, timeout=60); time.sleep(3)
    t0 = time.time(); c, dj = tr(); dt = time.time() - t0
    case('RL-06', 'Database outage', 'MySQL stopped: the API answers at once with a clear message', 'translate while MySQL is off', 'HTTP 500 JSON "Database not ready…" within 3 s, no PHP error text', f"{c} {str(dj.get('error'))[:60]} in {dt:.1f} s", c == 500 and 'Database not ready' in str(dj.get('error')) and dt < 3)
    h = requests.get(BASE + '/', timeout=20)
    case('RL-07', 'Database outage', 'MySQL stopped: the app page itself still opens', 'GET /', 'HTTP 200 with the app', h.status_code, h.status_code == 200 and 'screen-translate' in h.text)
    subprocess.run(START, shell=True, timeout=30)
    for _ in range(60):
        if subprocess.run('mysqladmin -uroot ping', shell=True, capture_output=True).returncode == 0: break
        time.sleep(1)
    time.sleep(2)
    first = [tr()[0] for _ in range(20)]
    case('RL-08', 'Database outage', 'After MySQL starts again the app works at once, without restarting Apache', '20 translations right after MySQL is back', 'All 20 succeed (no "server has gone away")', first, first.count(200) == 20)
else:
    for tid, t in [('RL-06', 'MySQL stopped: API answers with a clear message'), ('RL-07', 'MySQL stopped: app page still opens'), ('RL-08', 'App recovers when MySQL is back')]:
        case(tid, 'Database outage', t, 'SKIP_DB_STOP=1', '-', 'skipped', None)
# ---------------------------------------------------------------- 4. sessions survive
s = requests.Session(); s.headers['CF-Connecting-IP'] = ip(200)
s.post(API + 'auth.php?action=register', json={'name': 'R', 'email': f'rel_{RUN}@example.com', 'password': 'Passw0rd!xyz'}, timeout=30)
subprocess.run('apache2ctl graceful', shell=True, capture_output=True); time.sleep(3)
me = s.get(API + 'auth.php?action=me', timeout=30).json().get('user') or {}
case('RL-09', 'Restart', 'A logged-in user stays logged in after the web server restarts', 'login, restart Apache, GET me', 'Same user', me.get('email'), me.get('email') == f'rel_{RUN}@example.com')

os.makedirs(os.path.join(HERE, 'results'), exist_ok=True)
json.dump({'base': BASE, 'time': time.strftime('%Y-%m-%d %H:%M'), 'results': results}, open(os.path.join(HERE, 'results', 'reliability_results.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
n = sum(r['result'] == 'Pass' for r in results); sk = sum(r['result'] == 'Not run' for r in results)
print(f'{n}/{len(results) - sk} reliability tests passed' + (f' ({sk} not run)' if sk else ''))
sys.exit(0 if n + sk == len(results) else 1)
