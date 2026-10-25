<?php
// Add, edit or delete one dictionary word. Saved in MySQL and in database/user_edits.csv
// (setup.php re-applies that file, so edits survive a reset).
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/matching_engine.php';
require_once __DIR__ . '/word_evidence.php';
require_once __DIR__ . '/curated_edit.php';
require_once __DIR__ . '/edit_lib.php';
const EDIT_PASSCODE = '';   // optional: put a secret here to stop strangers editing a public link
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$lang = trim($in['language'] ?? ''); $word = bn_normalize(trim($in['word'] ?? '')); $mean = bn_normalize(trim($in['meaning'] ?? ''));
$phrase = !empty($in['phrase']);
$oldWord = bn_normalize(trim($in['old_word'] ?? ''));
$action = ($in['action'] ?? 'save') === 'delete' ? 'delete' : 'save';
// Editing is open to anyone with the link for now (REQUIRE_LOGIN = false). Every edit is recorded with the
// editor's internet address, in database/user_edits.csv and separately per address in database/edits_by_ip/,
// so all edits from one address can be reviewed or undone at http://localhost/vashasetu/admin_edits.php
const REQUIRE_LOGIN = false;
const EDITOR_EMAILS = [];   // with REQUIRE_LOGIN = true: e.g. ['you@example.com', 'friend@example.com']
if (REQUIRE_LOGIN) {
    if (current_user_id() === null) { http_response_code(401); echo json_encode(['error'=>'Log in to edit the dictionary']); exit; }
    if (EDITOR_EMAILS) {
        $em = get_db()->prepare('SELECT email FROM users WHERE id=?'); $em->execute([current_user_id()]);
        if (!in_array(strtolower((string) $em->fetchColumn()), array_map('strtolower', EDITOR_EMAILS), true)) { http_response_code(403); echo json_encode(['error'=>'Your account is not allowed to edit the dictionary']); exit; }
    }
}
if (EDIT_PASSCODE !== '' && ($in['passcode'] ?? '') !== EDIT_PASSCODE) { http_response_code(403); echo json_encode(['error'=>'Wrong passcode']); exit; }
if (!in_array($lang, array_merge(DIALECTS, array_keys(VS_NATIVE_LANGUAGES)), true)) { http_response_code(400); echo json_encode(['error'=>'Unsupported language']); exit; }
if ($word === '' || mb_strlen($word) > 100 || ($action === 'save' && ($mean === '' || mb_strlen($mean) > 100))) { http_response_code(400); echo json_encode(['error'=>'Word and meaning are required']); exit; }
try {
    $db = get_db();
    $ip = vs_client_ip();
    $uid = (string) (current_user_id() ?? '');
    $kind = $phrase ? 'phrase' : 'word';
    // what the entry was before, so an edit can be undone later
    $before = function (string $w) use ($db, $lang, $phrase): string {
        if ($phrase) {
            $k = trim(preg_replace('/\s+/u', ' ', preg_replace('/[?!।.,;:"“”\']/u', '', $w)));
            $q = $db->prepare('SELECT standard_bangla FROM parallel_sentences WHERE language=? AND source_key=? LIMIT 1'); $q->execute([$lang, $k]);
        } else {
            $q = $db->prepare("SELECT standard_word FROM word_meanings WHERE language=? AND source_word=? ORDER BY FIELD(meaning_status,'native_speaker_reported','community_verified') DESC LIMIT 1"); $q->execute([$lang, $w]);
        }
        return (string) ($q->fetchColumn() ?: '');
    };
    $log = [];
    // The dialect word itself was changed: remove the old entry first.
    if ($action === 'save' && $oldWord !== '' && $oldWord !== $word) {
        $prev = $before($oldWord);
        vs_apply_word_edit($db, $lang, $oldWord, '', 'delete', $phrase);
        if (!$phrase && in_array($lang, DIALECTS, true)) vs_curated_edit($lang, '', $oldWord, '', 'delete');
        $log[] = [$lang, $oldWord, '', 'delete', date('c'), $kind, $ip, $prev, $uid];
    }
    $prev = $before($word);
    vs_apply_word_edit($db, $lang, $word, $mean, $action, $phrase);
    if (!$phrase && in_array($lang, DIALECTS, true)) vs_curated_edit($lang, $oldWord, $word, $mean, $action);   // so translation uses the edit too
    $log[] = [$lang, $word, $mean, $action, date('c'), $kind, $ip, $prev, $uid];
    vs_log_edits($log);
    echo json_encode(['success'=>true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { http_response_code(500); echo json_encode(['error'=>'Could not save the edit']); }
