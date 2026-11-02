#!/usr/bin/env python3
"""Vashasetu load, stress, spike and soak tests ("does the server collapse?").

Run against the app on Apache (XAMPP):
    BASE=http://localhost/vashasetu python tests/load_tests.py            (about 6 minutes)
    SOAK_SECONDS=600 ... for a longer soak test.
Writes tests/results/load_results.json.
"""
import concurrent.futures as cf, json, os, random, statistics, subprocess, sys, threading, time
import requests

BASE = os.environ.get('BASE', 'http://127.0.0.1/vashasetu').rstrip('/')
API = BASE + '/api/'
SOAK = int(os.environ.get('SOAK_SECONDS', '180'))
HERE = os.path.dirname(os.path.abspath(__file__))
results, metrics = [], {}
def case(tid, module, title, inp, expected, actual, ok):
    results.append({'id': tid, 'module': module, 'title': title, 'input': inp, 'expected': expected, 'actual': str(actual)[:240], 'result': 'Pass' if ok else 'Fail'})
    print(('PASS ' if ok else 'FAIL ') + tid + ' ' + title + '   ' + str(actual)[:200])

SENT = ['আমি ভাত খাবো', 'তুমি কোথায় যাবে', 'আমার কাছে টাকা নেই', 'আজ বৃষ্টি হবে', 'সে বাজারে গেছে', 'আমরা কাল বাড়ি যাবো', 'তোমার নাম কী',
        'ছেলেটা স্কুলে যায়', 'মা রান্না করছে', 'I will eat rice', 'Where do you live?', 'ami vat khabo', 'আঁই ভাত খাইয়ুম']
DIA = ['Chattogram', 'Noakhali', 'Sylheti', 'Barishal', 'Mymensingh', 'Rajshahi', 'Rangpur']
tl = threading.local()
def sess():
    if not hasattr(tl, 's'): tl.s = requests.Session()
    return tl.s
def one_request(kind=None):
    k = kind or random.choices(['tr', 'dict', 'look', 'quiz'], [60, 20, 10, 10])[0]
    t0 = time.time()
    try:
        if k == 'tr':
            t = random.choice(SENT); src = 'english' if t[0].isascii() and t[0].isupper() else ('auto' if t.isascii() else 'standard')
            r = sess().post(API + 'translate.php', json={'text': t, 'source': src, 'target': random.choice(DIA + ['standard']) if src != 'standard' else random.choice(DIA), 'allow_approximate': True}, timeout=30)
        elif k == 'dict': r = sess().get(API + 'dictionary.php', params={'dialect': random.choice(DIA), 'page': random.randint(1, 40)}, timeout=30)
        elif k == 'look': r = sess().get(API + 'lookup.php', params={'language': 'Chattogram', 'word': random.choice(['খাইয়ুম', 'আঁই', 'ভাত', 'যাইয়ুম'])}, timeout=30)
        elif k == 'page': r = sess().get(BASE + random.choice(['/', '/assets/js/app.js', '/assets/css/app.css', '/manifest.json']), timeout=30)
        else: r = sess().get(API + 'quiz.php', params={'dialect': random.choice(DIA)}, timeout=30)
        code = r.status_code
    except Exception: code = 0
    return code, (time.time() - t0) * 1000
def run_for(users, seconds, kind=None):
    end = time.time() + seconds; out = []; lock = threading.Lock()
    def worker():
        while time.time() < end:
            c, t = one_request(kind)
            with lock: out.append((time.time(), c, t))
    th = [threading.Thread(target=worker) for _ in range(users)]
    [x.start() for x in th]; [x.join() for x in th]
    return out
def summary(out, seconds):
    ok = sorted(t for _, c, t in out if c == 200); n = len(out)
    err = sum(1 for _, c, _ in out if c != 200)
    pct = lambda p: ok[min(len(ok) - 1, int(len(ok) * p))] if ok else 0
    return {'requests': n, 'per_second': round(n / seconds, 1), 'errors': err, 'error_rate': round(100 * err / max(n, 1), 2),
            'p50_ms': round(pct(.5)), 'p95_ms': round(pct(.95)), 'p99_ms': round(pct(.99)), 'max_ms': round(ok[-1]) if ok else 0,
            'codes': {str(c): sum(1 for _, x, _ in out if x == c) for c in sorted(set(x for _, x, _ in out))}}
def rss(name):
    try: return round(sum(int(x) for x in subprocess.run(['ps', '-C', name, '-o', 'rss='], capture_output=True, text=True).stdout.split()) / 1024)
    except Exception: return None
def mysql_status(var):
    try: return int(subprocess.run(['mysql', '-uroot', '-N', '-e', f"SHOW GLOBAL STATUS LIKE '{var}'"], capture_output=True, text=True).stdout.split()[1])
    except Exception: return None
def mysql_var(var):
    try: return int(subprocess.run(['mysql', '-uroot', '-N', '-e', f"SHOW VARIABLES LIKE '{var}'"], capture_output=True, text=True).stdout.split()[1])
    except Exception: return None

try: subprocess.run(['mysql', '-uroot', '-e', 'FLUSH STATUS'], capture_output=True, timeout=20)   # start counting MySQL connections from zero
except Exception: pass
# warm up
for _ in range(20): one_request('tr')
# LT-01 baseline
base = [one_request('tr')[1] for _ in range(50)]
metrics['baseline_median_ms'] = round(statistics.median(base))
case('LT-01', 'Load', 'Baseline: one user, 50 translations one after another', '1 user × 50', 'Median under 200 ms', f"median {metrics['baseline_median_ms']} ms", metrics['baseline_median_ms'] < 200)
mem0 = {'apache_mb': rss('apache2'), 'mysql_mb': rss('mariadbd') or rss('mysqld')}
# LT-02 normal load
out = run_for(20, 30); m = summary(out, 30); metrics['load_20'] = m
case('LT-02', 'Load', 'Normal load: 20 users for 30 s (translate, Words, word card, quiz)', '20 users × 30 s', 'No errors; 95% of replies under 1 s', f"{m['requests']} requests, {m['per_second']}/s, errors {m['errors']}, p95 {m['p95_ms']} ms", m['errors'] == 0 and m['p95_ms'] < 1000)
# LT-03 stress ramp
ramp = {}
for users in (50, 100, 200, 300):
    out = run_for(users, 15); ramp[users] = summary(out, 15)
    print('  stress', users, ramp[users])
metrics['stress'] = ramp
case('LT-03', 'Stress', 'Stress: 50 and 100 users sending requests non-stop (15 s each)', '50 / 100 users', 'No errors; 95% of replies under 3 s', '; '.join(f"{u} users: {ramp[u]['per_second']}/s, err {ramp[u]['error_rate']}%, p95 {ramp[u]['p95_ms']} ms" for u in (50, 100)),
     all(ramp[u]['error_rate'] == 0 and ramp[u]['p95_ms'] < 3000 for u in (50, 100)))
case('LT-04', 'Stress', 'Breaking point: 200 and 300 users at once (more than Apache\'s 150 workers)', '200 / 300 users × 15 s', 'Slower, but no crash (errors under 1%)', '; '.join(f"{u} users: {ramp[u]['per_second']}/s, err {ramp[u]['error_rate']}%, p95 {ramp[u]['p95_ms']} ms, max {ramp[u]['max_ms']} ms" for u in (200, 300)), all(ramp[u]['error_rate'] < 1 for u in (200, 300)))
# LT-05 spike
with cf.ThreadPoolExecutor(500) as ex:
    t0 = time.time(); res = list(ex.map(lambda _: one_request('tr'), range(500))); dt = time.time() - t0
sp = {'requests': 500, 'seconds': round(dt, 1), 'errors': sum(1 for c, _ in res if c != 200), 'max_ms': round(max(t for _, t in res))}
metrics['spike'] = sp
case('LT-05', 'Spike', 'Spike: 500 translations sent in the same second', '500 at once', 'All answered (no errors)', f"{sp['requests']} in {sp['seconds']} s, errors {sp['errors']}, slowest {sp['max_ms']} ms", sp['errors'] == 0)
# LT-06 static files under load
out = run_for(100, 15, 'page'); m = summary(out, 15); metrics['pages_100'] = m
case('LT-06', 'Load', 'App page and its files for 100 users at once', '100 users × 15 s (/, app.js, app.css, manifest)', 'No errors', f"{m['requests']} files, {m['per_second']}/s, errors {m['errors']}, p95 {m['p95_ms']} ms", m['errors'] == 0)
# LT-07 soak
out = run_for(30, SOAK); m = summary(out, SOAK); metrics['soak'] = m
t_start = out[0][0] if out else 0
first = [t for ts, c, t in out if c == 200 and ts < t_start + 60]; last = [t for ts, c, t in out if c == 200 and ts > t_start + SOAK - 60]
drift = (statistics.median(last) / max(1, statistics.median(first))) if first and last else 0
mem1 = {'apache_mb': rss('apache2'), 'mysql_mb': rss('mariadbd') or rss('mysqld')}
metrics['soak_drift'] = round(drift, 2); metrics['memory_before'] = mem0; metrics['memory_after'] = mem1
case('LT-07', 'Soak', f'Soak: 30 users for {SOAK // 60} minutes without a break', f'30 users × {SOAK} s', 'No errors; no slow-down over time (last minute ≤ 2 × first minute)', f"{m['requests']} requests, errors {m['errors']}, median first min {statistics.median(first):.0f} ms → last min {statistics.median(last):.0f} ms" if first and last else 'n/a', m['errors'] == 0 and drift <= 2)
case('LT-08', 'Soak', 'Memory does not keep growing', 'Apache + MySQL memory before and after all load tests', 'No runaway growth (Apache < 1.5 GB)', f'before {mem0}, after {mem1}', (mem1['apache_mb'] or 0) < 1500)
# LT-09 recovery
time.sleep(5)
rec = [one_request('tr')[1] for _ in range(30)]
metrics['recovery_median_ms'] = round(statistics.median(rec))
case('LT-09', 'Recovery', 'After all the stress, the server is back to normal speed', '30 translations after the tests', 'Median within 3 × baseline', f"median {metrics['recovery_median_ms']} ms (baseline {metrics['baseline_median_ms']} ms)", metrics['recovery_median_ms'] <= max(30, 3 * metrics['baseline_median_ms']))
mu, mc, refused = mysql_status('Max_used_connections'), mysql_var('max_connections'), mysql_status('Connection_errors_max_connections')
metrics['mysql_connections'] = {'max_used': mu, 'limit': mc, 'refused_attempts': refused}
user_errors = sum(int(c) for r in ramp.values() for k, c in r['codes'].items() if k.startswith('5'))
case('LT-10', 'Recovery', 'When all MySQL connections are in use, requests wait and retry instead of failing', 'MySQL Max_used_connections / max_connections, refused attempts, and 5xx replies in LT-03/04',
     'No visitor gets a database error (no 5xx)', f'{mu} of {mc} connections used at the peak, {refused} connection attempts had to wait, {user_errors} visitor errors', user_errors == 0)

os.makedirs(os.path.join(HERE, 'results'), exist_ok=True)
json.dump({'base': BASE, 'time': time.strftime('%Y-%m-%d %H:%M'), 'results': results, 'metrics': metrics}, open(os.path.join(HERE, 'results', 'load_results.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
n = sum(r['result'] == 'Pass' for r in results)
print(f'{n}/{len(results)} load tests passed')
sys.exit(0 if n == len(results) else 1)
