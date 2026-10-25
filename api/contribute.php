<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Use POST']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$word = trim($input['dialect_word'] ?? '');
$meaning = trim($input['meaning'] ?? '');
$dialectType = trim($input['dialect_type'] ?? '');

$word = mb_substr(strip_tags($word), 0, 120); $meaning = mb_substr(strip_tags($meaning), 0, 255);
if ($word === '' || $meaning === '' || $dialectType === '') {
    http_response_code(400);
    echo json_encode(['error' => 'dialect_word, meaning, and dialect_type are required']);
    exit;
}

if (!valid_dialect($dialectType)) {
    http_response_code(400);
    echo json_encode(['error' => 'Choose one of the supported dialects']);
    exit;
}

$pdo = get_db();
$userId = current_user_id();

// Attribute to the real logged-in account (server-trusted), never a client-supplied
// name string — a guest submission is recorded as "Anonymous", never spoofed as someone else.
$submittedBy = 'Anonymous';
if ($userId !== null) {
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $u = $stmt->fetch();
    if ($u) $submittedBy = $u['name'];
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO contributions (user_id, dialect_word, meaning, dialect_type, submitted_by, status)
         VALUES (?, ?, ?, ?, ?, 'pending')"
    );
    $stmt->execute([$userId, $word, $meaning, $dialectType, $submittedBy]);

    echo json_encode([
        'success' => true,
        'message' => 'Contribution submitted for review',
        'id' => $pdo->lastInsertId(),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Submission failed']);
}
