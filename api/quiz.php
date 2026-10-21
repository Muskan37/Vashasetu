<?php
// GET api/quiz.php?dialect=any|Chattogram&exclude=1,2,3
// One multiple-choice question: "What does this dialect word mean?"
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Use GET']);
    exit;
}

$dialect = trim($_GET['dialect'] ?? 'Chattogram');
if (!in_array($dialect,['Barishal','Chattogram','Mymensingh','Noakhali','Rajshahi','Sylheti'],true)) {http_response_code(400);echo json_encode(['error'=>'Select a supported dialect for quizzes']);exit;}
$exclude = array_values(array_filter(array_map('intval', explode(',', $_GET['exclude'] ?? ''))));

$pdo = get_db();
try {
    // Quizzes are scoped to one selected dialect and use independently attested
    // one-word evidence, never mixed lexicon candidate alignments.
    $st=$pdo->prepare("SELECT id,source_word AS dialect_word,standard_word AS standard_meaning,language AS dialect_type FROM word_meanings
             WHERE language=? AND source_word<>standard_word
             AND meaning_status IN ('publication_reviewed_not_reaudited','existing_curated_needs_recheck','community_verified','native_speaker_reported')");
    $st->execute([$dialect]); $all=$st->fetchAll();

    $questions = array_values(array_filter($all, fn($r) => !in_array((int) $r['id'], $exclude, true)));
    if (!$questions) $questions = $all;
    if (count($all) < 4) { echo json_encode(['success' => true, 'question' => null]); exit; }

    $q = $questions[random_int(0, count($questions) - 1)];
    $correct = $q['standard_meaning'];
    $meanings = array_values(array_unique(array_map(fn($r) => $r['standard_meaning'], $all)));
    $wrong = array_values(array_filter($meanings, fn($m) => $m !== $correct && $m !== $q['dialect_word']));
    shuffle($wrong);
    $options = array_slice($wrong, 0, 3);
    $options[] = $correct;
    shuffle($options);

    echo json_encode([
        'success' => true,
        'question' => [
            'id' => (int) $q['id'],
            'word' => $q['dialect_word'],
            'dialect' => $q['dialect_type'],
            'options' => $options,
            'answer' => array_search($correct, $options, true),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not load a question']);
}
