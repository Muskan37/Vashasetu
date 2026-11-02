#!/usr/bin/env python3
"""Small mutation test: do the PHPUnit tests really check the behaviour, or do they only run the code?

Each "mutant" plants one small, realistic bug in the source (e.g. `< 8` becomes `< 7`), runs the Unit and
Integration suites, and puts the original code back. A good suite FAILS on every mutant ("killed").
A mutant that survives shows a behaviour no test checks.
    python tests/mutation_check.py          (needs phpunit; the database for the Integration suite)
Writes tests/results/mutation_results.json.
"""
import json, os, subprocess, sys, time

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
MUTANTS = [
    ('M-01', 'api/lib/security.php', "if ($len < 8)", "if ($len < 7)", 'Password minimum 8 → 7'),
    ('M-02', 'api/lib/security.php', "if ($len > 200)", "if ($len >= 200)", 'Password maximum 200 → 199'),
    ('M-03', 'api/lib/security.php', "strtolower($pw) === strtolower($email)", "$pw === $email", 'Email check becomes case-sensitive'),
    ('M-04', 'api/lib/security.php', "$len = mb_strlen($pw, 'UTF-8');", "$len = strlen($pw);", 'Length counted in bytes again (defect D28)'),
    ('M-05', 'api/config.php', "if (in_array($ra, ['127.0.0.1', '::1'], true) && !empty($_SERVER['HTTP_CF_CONNECTING_IP']))", "if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']))", 'Tunnel header trusted from anyone (defect D11)'),
    ('M-06', 'api/config.php', "'Narsingdi', 'Tangail'", "'Narsingdi'", 'Tangail dropped from the dialect list'),
    ('M-07', 'api/matching_engine.php', "if (mb_strlen($w) > 2) {", "if (mb_strlen($w) >= 2) {", 'Verb-variant boundary 2 → 3 letters'),
    ('M-08', 'api/lib/text.php', '"\\u{09C7}\\u{09BE}" => "\\u{09CB}", ', '', 'Split ো no longer joined (defect D31)'),
    ('M-09', 'api/lib/text.php', "if ($x === 'o') return 0.12;", "if ($x === 'o') return 0.5;", 'Cost of the optional inherent vowel'),
    ('M-10', 'api/lib/text.php', "'K|KH'=>0.15", "'K|KH'=>0.3", 'Aspirate similarity K/KH'),
    ('M-11', 'api/lib/en_grammar.php', "return $b . 'তে';", "return $b . 'ে';", 'Place ending after ি/ু'),
    ('M-12', 'api/lib/en_grammar.php', "return $b . 'য়ের';", "return $b . 'ের';", 'Possessive after a vowel letter (defect D25)'),
    ('M-13', 'api/lib/en_grammar.php', "$forms['fut'] = [$s1 . 'বো',", "$forms['fut'] = [$s1 . 'ব',", 'Future ending for I'),
    ('M-14', 'api/lib/en_grammar.php', "substr($w, 0, -3) . 'y', substr($w, 0, -1)], 'past');", "substr($w, 0, -3) . 'y'], 'past');", '-ie verbs (died) not found (defect D27)'),
    ('M-15', 'api/word_evidence.php', "(?:mr|mrs|ms|dr|", "(?:zz|", 'Titles end sentences again (defect D30)'),
    ('M-16', 'api/word_evidence.php', "return preg_replace('/(?<![.\\d])\\.(?![.\\d])(?=\\s|$)/u', '।', $s);", "return $s;", 'Banglish full stop not converted (defect D32)'),
    ('M-17', 'api/word_evidence.php', "$tail = preg_match('/\\s+$/u', $chunk, $m)", "$tail = false && preg_match('/\\s+$/u', $chunk, $m)", 'Trailing space/line break of a sentence lost'),
    ('M-19', 'api/lib/security.php', "if ((int) $q->fetchColumn() >= $max) return false;", "if ((int) $q->fetchColumn() > $max) return false;", 'Rate limit lets one extra request through'),
    ('M-20', 'api/lib/text.php', "    unset($v['']);", "", 'Empty key allowed in fuzzy look-ups'),
    ('M-21', 'api/lib/en_grammar.php', "if (preg_match('/া$/u', $b)) return $b . 'য়';", "if (preg_match('/া$/u', $b)) return $b . 'তে';", 'Place ending after া (ঢাকায়)'),
    ('M-22', 'api/lib/text.php', "'J|Y'=>0.5", "'J|Y'=>1.2", 'Similarity of য/জ sounds'),
    ('M-23', 'api/word_evidence.php', "if (count($chunks) > 25) $chunks = [$text];", "if (count($chunks) > 2) $chunks = [$text];", 'Long texts no longer translated sentence by sentence'),
    ('M-24', 'api/lib/en_grammar.php', "if (isset($I[$w])) return isset($V[$I[$w][0]]) ? $I[$w] : null;", "if (isset($I[$w])) return $I[$w];", 'Irregular form of an unknown verb returned (defect D26)'),
    ('M-18', 'api/lib/security.php', "return null;\n}", "return 'x';\n}", 'Every password refused'),
]


def run_suite() -> bool:
    r = subprocess.run(['phpunit', '--testsuite', 'Unit,Integration', '--stop-on-failure'], cwd=ROOT, capture_output=True, text=True)
    return r.returncode == 0


results = []
assert run_suite(), 'the suite must pass before mutation testing'
for mid, rel, old, new, what in MUTANTS:
    path = os.path.join(ROOT, rel)
    src = open(path, encoding='utf-8').read()
    if src.count(old) != 1:
        results.append({'id': mid, 'file': rel, 'mutation': what, 'result': 'Not applied (pattern not found once)'}); print('SKIP', mid, what); continue
    try:
        open(path, 'w', encoding='utf-8').write(src.replace(old, new))
        t0 = time.time(); passed = run_suite()
    finally:
        open(path, 'w', encoding='utf-8').write(src)          # always put the original code back
    res = 'Survived' if passed else 'Killed'
    results.append({'id': mid, 'file': rel, 'mutation': what, 'result': res, 'seconds': round(time.time() - t0, 1)})
    print(('KILLED  ' if not passed else 'SURVIVED') + f' {mid} {what}')
assert run_suite(), 'original code restored'
k = sum(r['result'] == 'Killed' for r in results); n = sum(r['result'] in ('Killed', 'Survived') for r in results)
os.makedirs(os.path.join(ROOT, 'tests', 'results'), exist_ok=True)
json.dump({'time': time.strftime('%Y-%m-%d %H:%M'), 'score': f'{k}/{n}', 'results': results}, open(os.path.join(ROOT, 'tests', 'results', 'mutation_results.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
print(f'Mutation score: {k}/{n} mutants killed')
sys.exit(0 if k == n else 1)
