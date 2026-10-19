<?php
// GET api/lookup.php?word=ভালো   (or Banglish "valo", or English "water")
// Dictionary card: meaning in every dialect, English gloss, pronunciation, example sentences.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/matching_engine.php';

$word = trim($_GET['word'] ?? '');
if ($word === '') { http_response_code(400); echo json_encode(['error' => 'word required']); exit; }
$word = mb_substr(preg_split('/\s+/u', $word)[0], 0, 40);
$pdo = get_db();
try {
    $stats = script_stats($word);
    $bn = $word; $english = null; $alts = [];
    if ($stats['bangla'] === 0) {
        $lw = strtolower($word);
        $eng = english_to_bangla($pdo, [$lw])[0];
        $isEn = $eng['matched'] ? true : false;
        $b = banglish_word($pdo, $lw);
        // a known English word wins unless the Roman spelling is a very close Bangla word
        if ($isEn && !($b['best'] !== null && $b['rel'] <= 0.1 && $b['freq'] > 5)) {
            $bn = bn_normalize($eng['std']); $english = $lw;
            $bn = preg_split('/\s+/u', $bn)[0];
        } elseif ($b['best'] !== null && empty($b['guess']) && $b['rel'] <= 0.2 && mb_strlen($lw) <= 3 * mb_strlen($b['best']) + 2) {
            $bn = $b['best']; $alts = $b['alts'];
        } else {
            echo json_encode(['success' => true, 'found' => false, 'word' => $word]); exit;
        }
    }
    $bn = bn_normalize($bn);

    // 1) what the word means (if it is a dialect word) — best standard word per dialect
    $asDialect = [];
    $st = $pdo->prepare('SELECT dialect, standard_word, p_std, cnt, verified FROM lexicon WHERE dialect_word = ? AND p_std >= 0.3 AND standard_word <> dialect_word ORDER BY verified DESC, p_std DESC');
    $st->execute([$bn]);
    foreach ($st->fetchAll() as $r) {
        if (!isset($asDialect[$r['dialect']])) $asDialect[$r['dialect']] = ['dialect' => $r['dialect'], 'standard' => $r['standard_word'], 'conf' => round((float) $r['p_std'], 2), 'verified' => (int) $r['verified']];
    }
    // 2) how it is said in each dialect (if it is a Standard word)
    $inDialects = [];
    foreach (DIALECTS as $d) {
        $r = standard_to_dialect($pdo, $d, [$bn])[0];
        if ($r['matched']) $inDialects[] = ['dialect' => $d, 'word' => $r['tgt'], 'roman' => bn_romanize($r['tgt']), 'conf' => $r['conf']];
    }
    // 3) English gloss
    $gloss = bangla_to_english($pdo, [$bn])[0];
    // 4) example sentences
    $ex = [];
    $st = $pdo->prepare("SELECT variety, text, standard_text FROM sentences WHERE text LIKE ? ESCAPE '|' LIMIT 4");
    $st->execute(['%' . str_replace(['|', '%', '_'], ['||', '|%', '|_'], $bn) . '%']);
    foreach ($st->fetchAll() as $r) $ex[] = ['variety' => $r['variety'], 'label' => VARIETY_LABEL[$r['variety']] ?? $r['variety'], 'text' => $r['text'], 'standard' => $r['standard_text']];

    echo json_encode([
        'success' => true, 'found' => true,
        'word' => $bn, 'typed' => $word, 'english_input' => $english, 'alternatives' => $alts,
        'roman' => bn_romanize($bn),
        'english' => $gloss['matched'] ? $gloss['tgt'] : null,
        'means' => array_values($asDialect),
        'in_dialects' => $inDialects,
        'examples' => $ex,
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lookup failed', 'detail' => $e->getMessage()]);
}
