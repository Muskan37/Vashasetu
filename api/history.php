<?php
require_once __DIR__ . '/config.php';

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = current_user_id();
    try {
        if ($userId !== null) {
            // Logged in: only this user's own history, ever.
            $stmt = $pdo->prepare(
                "SELECT id, original_text, translated_text, dialect_type, created_at
                 FROM translation_history WHERE user_id = ?
                 ORDER BY created_at DESC LIMIT 50"
            );
            $stmt->execute([$userId]);
        } else {
            // Guest: only this browser's own anonymous session, never anyone else's.
            $sessionId = session_id();
            $stmt = $pdo->prepare(
                "SELECT id, original_text, translated_text, dialect_type, created_at
                 FROM translation_history WHERE session_id = ? AND user_id IS NULL
                 ORDER BY created_at DESC LIMIT 50"
            );
            $stmt->execute([$sessionId]);
        }
        echo json_encode(['success' => true, 'history' => $stmt->fetchAll()]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Fetch failed']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid id required']);
        exit;
    }
    $userId = current_user_id();
    try {
        // Ownership check baked into the WHERE clause — you can only ever
        // delete a row that is actually yours (by user_id, or by session_id
        // for guests), never anyone else's by guessing an id.
        if ($userId !== null) {
            $stmt = $pdo->prepare("DELETE FROM translation_history WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM translation_history WHERE id = ? AND session_id = ? AND user_id IS NULL");
            $stmt->execute([$id, session_id()]);
        }
        echo json_encode(['success' => true, 'deleted' => $stmt->rowCount() > 0]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Delete failed']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Use GET or DELETE']);
