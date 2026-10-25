<?php
// POST api/translate.php   {text, source:'auto|banglish|english|standard|<dialect>', target:'standard|<dialect>|english', log:true|false}
// (old clients may still send dialect_type; it is treated as "from that dialect to Standard")
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/matching_engine.php';
require_once __DIR__ . '/word_evidence.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Use POST']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$text = trim($input['text'] ?? '');
$source = trim($input['source'] ?? '');
$target = trim($input['target'] ?? '');
if ($source === '' && isset($input['dialect_type'])) { $source = trim($input['dialect_type']); $target = 'standard'; }
if ($source === '') $source = 'auto';
if ($target === '') $target = 'standard';
$log = !empty($input['log']);
$allowApproximate = array_key_exists('allow_approximate', $input) ? !empty($input['allow_approximate']) : true;

if ($text === '') {
    http_response_code(400);
    echo json_encode(['error' => 'No text provided']);
    exit;
}
if (mb_strlen($text) > 500) $text = mb_substr($text, 0, 500);

$pdo = get_db();
try {
    $result = vs_translate_with_evidence($pdo, $text, $source, $target, $allowApproximate);

    if ($log && $result['translated_text'] !== '') {
        $userId = current_user_id();
        $sessionId = session_id() ?: substr(md5(uniqid('', true)), 0, 20);
        $label = (VARIETY_LABEL[$result['source']] ?? $result['source']) . ' → ' . (VARIETY_LABEL[$result['target']] ?? $result['target']);
        $stmt = $pdo->prepare(
            "INSERT INTO translation_history (user_id, session_id, original_text, translated_text, dialect_type)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $sessionId, mb_substr($text, 0, 500), mb_substr($result['translated_text'], 0, 500), mb_substr($label, 0, 80)]);
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Translation failed']);
}
