<?php
// GET api/dictionary.php?dialect=Chattogram&q=&page=1   — browse / search the learned dictionary
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/matching_engine.php';

$dialect = trim($_GET['dialect'] ?? 'Chattogram');
if (!in_array($dialect, DIALECTS, true)) $dialect = 'Chattogram';
$q = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 30;
$pdo = get_db();
try {
    $where = 'dialect = ? AND dialect_word <> standard_word AND p_std >= 0.5';
    $args = [$dialect];
    if ($q !== '') {
        $where = 'dialect = ? AND p_std >= 0.4';   // searching: also show words that are the same as Standard
        $qq = $q;
        if (script_stats($q)['bangla'] === 0) {            // Banglish or English search -> Bangla
            $b = banglish_word($pdo, strtolower($q));
            $en = english_to_bangla($pdo, [strtolower($q)])[0];
            $okB = $b['best'] !== null && empty($b['guess']) && $b['rel'] <= 0.2 && mb_strlen($q) <= 3 * mb_strlen($b['best']) + 2;
            $strongB = $okB && $b['rel'] <= 0.1 && $b['freq'] > 5;   // same rule as the word card: a very close Bangla spelling beats an odd English dictionary hit (ami, bhat, khai)
            $qq = ($en['matched'] && !$strongB) ? preg_split('/\s+/u', bn_normalize($en['std']))[0] : ($okB ? $b['best'] : $q);
        }
        $qq = bn_normalize($qq);
        $esc = str_replace(['|', '%', '_'], ['||', '|%', '|_'], $qq);
        $where .= " AND (dialect_word LIKE ? ESCAPE '|' OR standard_word LIKE ? ESCAPE '|')";
        $args[] = $esc . '%'; $args[] = $esc . '%';
    }
    $st = $pdo->prepare("SELECT COUNT(*) FROM lexicon WHERE $where");
    $st->execute($args);
    $total = (int) $st->fetchColumn();
    $st = $pdo->prepare("SELECT dialect_word, standard_word, p_std, cnt, verified FROM lexicon WHERE $where ORDER BY verified DESC, cnt DESC, p_std DESC LIMIT $per OFFSET " . (($page - 1) * $per));
    $st->execute($args);
    $rows = [];
    foreach ($st->fetchAll() as $r) $rows[] = [
        'dialect_word' => $r['dialect_word'], 'standard_word' => $r['standard_word'],
        'roman' => bn_romanize($r['dialect_word']), 'conf' => round((float) $r['p_std'], 2),
        'count' => (int) $r['cnt'], 'verified' => (int) $r['verified'],
    ];
    // per-dialect totals only change when the data is reloaded, so cache them in a temp file
    $lf = __DIR__ . '/../database/lexicon.csv';
    $cf = sys_get_temp_dir() . '/vashasetu_dtot_' . md5(__DIR__ . (is_file($lf) ? filemtime($lf) . filesize($lf) : '')) . '.json';
    $totals = is_file($cf) ? json_decode((string) @file_get_contents($cf), true) : null;
    if (!is_array($totals) || !$totals) {
        $totals = [];
        foreach ($pdo->query("SELECT dialect, COUNT(*) c FROM lexicon WHERE dialect_word <> standard_word AND p_std >= 0.5 GROUP BY dialect")->fetchAll() as $r) $totals[$r['dialect']] = (int) $r['c'];
        if ($totals) @file_put_contents($cf, json_encode($totals));
    }
    echo json_encode(['success' => true, 'dialect' => $dialect, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $per)), 'rows' => $rows, 'dialect_totals' => $totals], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Dictionary failed', 'detail' => $e->getMessage()]);
}
