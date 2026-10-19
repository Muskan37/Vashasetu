<?php
// GET api/word.php?dialect=Chattogram|any&mode=daily|random&n=0&exclude_id=12
// daily  = the same word for everyone all day (changes at midnight, server time)
// random = a different word each call
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/text.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Use GET']);
    exit;
}

$dialect = trim($_GET['dialect'] ?? 'any');
$mode = ($_GET['mode'] ?? 'daily') === 'random' ? 'random' : 'daily';
$n = (int) ($_GET['n'] ?? 0);
$excludeId = (int) ($_GET['exclude_id'] ?? 0);

$pdo = get_db();
try {
    // Only well-supported pairs: clearly different from the Standard word, seen several times
    $base = "SELECT id, dialect_word, standard_word AS standard_meaning, dialect AS dialect_type FROM lexicon
             WHERE dialect_word <> standard_word AND p_std >= 0.8 AND cnt >= 4 AND CHAR_LENGTH(dialect_word) >= 2";
    if ($dialect !== '' && strtolower($dialect) !== 'any') {
        $stmt = $pdo->prepare("$base AND dialect = ? ORDER BY id");
        $stmt->execute([$dialect]);
    } else {
        $stmt = $pdo->query("$base ORDER BY id");
    }
    $rows = $stmt->fetchAll();
    if (!$rows) { echo json_encode(['success' => true, 'word' => null]); exit; }

    if ($mode === 'daily') {
        $idx = (((int) date('Ymd')) * 7 + $n) % count($rows);
        if ($idx < 0) $idx += count($rows);
        $word = $rows[$idx];
    } else {
        $pool = array_values(array_filter($rows, fn($r) => (int) $r['id'] !== $excludeId));
        if (!$pool) $pool = $rows;
        $word = $pool[random_int(0, count($pool) - 1)];
    }
    $word['roman'] = bn_romanize($word['dialect_word']);
    echo json_encode(['success' => true, 'word' => $word], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not load a word']);
}
