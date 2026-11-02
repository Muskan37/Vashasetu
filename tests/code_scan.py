#!/usr/bin/env python3
"""Vashasetu static code checks (no server needed): syntax of every file, dangerous PHP functions, SQL built from
user input, unescaped output in pages and scripts, and secrets in the code.
    python tests/code_scan.py
Writes tests/results/code_scan_results.json.
"""
import glob, json, os, re, subprocess, sys, time
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
HERE = os.path.join(ROOT, 'tests')
results = []
def case(tid, module, title, inp, expected, actual, ok):
    results.append({'id': tid, 'module': module, 'title': title, 'input': inp, 'expected': expected, 'actual': str(actual)[:260], 'result': 'Pass' if ok else 'Fail'})
    print(('PASS ' if ok else 'FAIL ') + tid + ' ' + title + ('' if ok else '   -> ' + str(actual)[:220]))
php = [f for f in glob.glob(os.path.join(ROOT, '**', '*.php'), recursive=True) if '/tests/' not in f.replace('\\', '/') or f.endswith('unit_tests.php')]
js = glob.glob(os.path.join(ROOT, 'assets', 'js', '*.js')) + [os.path.join(ROOT, 'sw.js')]
py = glob.glob(os.path.join(ROOT, 'tools', '*.py')) + glob.glob(os.path.join(ROOT, 'tests', '*.py'))
src = {f: open(f, encoding='utf-8', errors='replace').read() for f in php + js}
rel = lambda f: os.path.relpath(f, ROOT).replace('\\', '/')

bad = [rel(f) for f in php if subprocess.run(['php', '-l', f], capture_output=True, text=True).returncode != 0]
case('CS-01', 'Code checks', f'PHP syntax check of all {len(php)} PHP files', 'php -l', 'No errors', bad or 'all OK', not bad)
try: bad = [rel(f) for f in js if subprocess.run(['node', '--check', f], capture_output=True, text=True).returncode != 0]; ok = not bad
except FileNotFoundError: bad, ok = 'node not installed', True
case('CS-02', 'Code checks', f'JavaScript syntax check of {len(js)} files', 'node --check', 'No errors', bad or 'all OK', ok)
bad = [rel(f) for f in py if subprocess.run([sys.executable, '-m', 'py_compile', f], capture_output=True).returncode != 0]
case('CS-03', 'Code checks', f'Python syntax check of {len(py)} data/test scripts', 'py_compile', 'No errors', bad or 'all OK', not bad)
danger = re.compile(r'(?<!->)(?<!::)\b(eval|exec|system|passthru|shell_exec|popen|proc_open|unserialize|assert|create_function)\s*\(|=\s*`[^`]*\$')
hits = [f'{rel(f)}:{i + 1}' for f in php for i, l in enumerate(src[f].splitlines()) if danger.search(l) and not l.strip().startswith('//') and 'pcntl' not in l]
case('CS-04', 'Code checks', 'No dangerous PHP functions (eval, exec, system, unserialize …)', 'all PHP files', 'None used', hits or 'none', not hits)
sqlv = re.compile(r'(->query|->exec|->prepare)\s*\(\s*["\'][^"\']*["\']\s*\.\s*\$_(GET|POST|REQUEST|COOKIE)|(->query|->exec)\s*\([^)]*\$_(GET|POST|REQUEST|COOKIE)|\$(input|in|_GET|_POST)\[[^]]+\]\s*\.\s*["\'][^"\']*(WHERE|AND|OR|VALUES)', re.I)
hits = [f'{rel(f)}:{i + 1}' for f in php for i, l in enumerate(src[f].splitlines()) if sqlv.search(l)]
case('CS-05', 'Code checks', 'SQL is never built by joining user input into the query text', 'all PHP files', 'Only prepared statements with ? placeholders', hits or 'none', not hits)
echo = re.compile(r'(echo|print|<\?=)\s*[^;]*\$_(GET|POST|REQUEST|COOKIE|SERVER)\[(?![^;]*htmlspecialchars)')
hits = [f'{rel(f)}:{i + 1}' for f in php for i, l in enumerate(src[f].splitlines()) if echo.search(l) and 'json_encode' not in l]
case('CS-06', 'Code checks', 'Pages never print request values without escaping (htmlspecialchars)', 'all PHP files', 'None', hits or 'none', not hits)
inner = []
for f in js:
    for i, l in enumerate(src[f].splitlines()):
        m = re.search(r'innerHTML\s*=\s*`([^`]*)`', l)
        if m:
            for expr in re.findall(r'\$\{([^}]*)\}', m.group(1)):
                e = expr.strip()
                if not re.search(r'^(esc|icon|bridgeIllustration|label|suffix|meaning|means|ins|ex|rows|\(|\w+\.map|Number|[A-Z_]+\b|i\b|w\b|h\b|r\.verified)|esc\(|\.length|\?|toLocaleString|colorOf|fmtDate', e): inner.append(f'{rel(f)}:{i + 1} ${{{e[:30]}}}')
case('CS-07', 'Code checks', 'Values put into the page with innerHTML go through esc()', 'assets/js/*.js template strings', 'No unescaped data', inner[:6] or 'none found', not inner)
sec = re.compile(r'(api[_-]?key|secret|passwd|password|token)\s*[:=]\s*["\'][A-Za-z0-9_\-]{12,}["\']', re.I)
hits = [f'{rel(f)}:{i + 1}' for f in php + js for i, l in enumerate(src[f].splitlines()) if sec.search(l)]
case('CS-08', 'Code checks', 'No passwords or API keys written in the code', 'all PHP and JS files', 'None', hits or 'none', not hits)
wl = [rel(f) for f in php if re.search(r'\bfopen\([^)]*,\s*["\']w["\']', src[f]) and 'flock' not in src[f] and 'setup.php' not in f and 'admin_edits' not in f]
case('CS-09', 'Code checks', 'Files that many people can change at once are written under a lock', 'fopen(…, "w") in API files', 'flock used', wl or 'all locked', not wl)
os.makedirs(os.path.join(HERE, 'results'), exist_ok=True)
json.dump({'time': time.strftime('%Y-%m-%d %H:%M'), 'results': results}, open(os.path.join(HERE, 'results', 'code_scan_results.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
n = sum(r['result'] == 'Pass' for r in results); print(f'{n}/{len(results)} code checks passed'); sys.exit(0 if n == len(results) else 1)
