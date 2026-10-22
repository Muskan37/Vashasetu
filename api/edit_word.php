<?php
// Add, edit or delete one dictionary word. Saved in MySQL and in database/user_edits.csv
// (setup.php re-applies that file, so edits survive a reset).
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/matching_engine.php';
require_once __DIR__ . '/word_evidence.php';
require_once __DIR__ . '/curated_edit.php';
const EDIT_PASSCODE = '';   // optional: put a secret here to stop strangers editing a public link
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$lang = trim($in['language'] ?? ''); $word = bn_normalize(trim($in['word'] ?? '')); $mean = bn_normalize(trim($in['meaning'] ?? ''));
$phrase = !empty($in['phrase']);
$oldWord = bn_normalize(trim($in['old_word'] ?? ''));
$action = ($in['action'] ?? 'save') === 'delete' ? 'delete' : 'save';
if (EDIT_PASSCODE !== '' && ($in['passcode'] ?? '') !== EDIT_PASSCODE) { http_response_code(403); echo json_encode(['error'=>'Wrong passcode']); exit; }
if (!in_array($lang, array_merge(DIALECTS, array_keys(VS_NATIVE_LANGUAGES)), true)) { http_response_code(400); echo json_encode(['error'=>'Unsupported language']); exit; }
if ($word === '' || mb_strlen($word) > 100 || ($action === 'save' && ($mean === '' || mb_strlen($mean) > 100))) { http_response_code(400); echo json_encode(['error'=>'Word and meaning are required']); exit; }
try {
    $db = get_db();
    $f = __DIR__ . '/../database/user_edits.csv';
    $new = !is_file($f);
    $h = @fopen($f, 'a');
    if ($h && $new) fputcsv($h, ['language','source_word','standard_word','action','time','kind']);
    $kind = $phrase ? 'phrase' : 'word';
    // The dialect word itself was changed: remove the old entry first.
    if ($action === 'save' && $oldWord !== '' && $oldWord !== $word) {
        vs_apply_word_edit($db, $lang, $oldWord, '', 'delete', $phrase);
        if ($h) fputcsv($h, [$lang,$oldWord,'','delete',date('c'),$kind]);
    }
    vs_apply_word_edit($db, $lang, $word, $mean, $action, $phrase);
    if (!$phrase && in_array($lang, DIALECTS, true)) vs_curated_edit($lang, $oldWord, $word, $mean, $action);   // so translation uses the edit too
    if ($h) { fputcsv($h, [$lang,$word,$mean,$action,date('c'),$kind]); fclose($h); }
    echo json_encode(['success'=>true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { http_response_code(500); echo json_encode(['error'=>'Could not save the edit']); }
function vs_apply_word_edit(PDO $db, string $lang, string $word, string $mean, string $action, bool $phrase = false): void {
    if ($phrase) {
        $key = trim(preg_replace('/\s+/u', ' ', preg_replace('/[?!।.,;:"“”\']/u', '', $word)));
        $db->prepare('DELETE FROM parallel_sentences WHERE language=? AND source_key=?')->execute([$lang,$key]);
        if ($action === 'save') {
            $mk = trim(preg_replace('/\s+/u', ' ', preg_replace('/[?!।.,;:"“”\']/u', '', $mean)));
            $db->prepare("INSERT IGNORE INTO parallel_sentences (pair_hash,language,source_text,source_key,standard_bangla,standard_key,source_dataset,review_status,eligible_exact,split_name,script,source_count) VALUES (?,?,?,?,?,?, 'Edited in the app','native_speaker_reported',1,'train','Bengali transliteration',1)")
               ->execute([hash('sha256',"editp|$lang|$key|$mk"),$lang,$word,$key,$mean,$mk]);
        }
        return;
    }
    $db->prepare('DELETE FROM word_meanings WHERE language=? AND source_word=?')->execute([$lang,$word]);
    if ($action === 'save') {
        $h = hash('sha256', "edit|$lang|$word|$mean");
        $db->prepare("INSERT IGNORE INTO word_meanings (pair_hash,language,source_word,standard_word,meaning_status,source_dataset,evidence_count) VALUES (?,?,?,?, 'native_speaker_reported','Edited in the app',1)")->execute([$h,$lang,$word,$mean]);
    }
}
