<?php
// ============================================================
// One-time setup for XAMPP. Open:  http://localhost/vashasetu/setup.php
// - creates the "vashasetu" database (if missing) and all tables
// - loads the dictionary (about 30,000 dialect word pairs, 12,000 aligned
//   sentences, 120,000 Bangla words, 93,000 English words)
// Safe to run again: add ?reset=1 to wipe and reload the dictionary tables.
// Also works from a terminal:  php setup.php
// ============================================================
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/lib/text.php';
// Setup can wipe and reload the data, so it only runs for someone at this computer, never through the public link.
if (!vs_is_local_request()) { http_response_code(403); exit('setup.php can only be opened on the computer running XAMPP: http://localhost/vashasetu/setup.php'); }
@set_time_limit(0);
@ini_set('memory_limit', '512M');
header('Content-Type: text/html; charset=utf-8');
$cli = PHP_SAPI === 'cli';
$reset = $cli ? in_array('--reset', $argv ?? [], true) : isset($_GET['reset']);
$refreshIndigenous = $cli ? in_array('--refresh-indigenous', $argv ?? [], true) : isset($_GET['refresh_indigenous']);
$refreshLocalizations = $cli ? in_array('--refresh-localizations', $argv ?? [], true) : isset($_GET['refresh_localizations']);
$refreshParallel = $cli ? in_array('--refresh-parallel', $argv ?? [], true) : isset($_GET['refresh_parallel']);
$refreshWords = $cli ? in_array('--refresh-words', $argv ?? [], true) : isset($_GET['refresh_words']);
$refreshParallel = $refreshParallel || $refreshIndigenous;
$refreshWords = $refreshWords || $refreshIndigenous;

function say(string $m, bool $ok = true): void {
    global $cli;
    echo $cli ? $m . "\n" : '<div style="font:15px system-ui;margin:4px 0;color:' . ($ok ? '#0a5' : '#c22') . '">' . htmlspecialchars($m) . '</div>';
    if (!$cli) { @ob_flush(); flush(); }
}
if (!$cli) echo '<!doctype html><meta charset="utf-8"><title>Vashasetu setup</title><body style="font-family:system-ui;max-width:640px;margin:30px auto;padding:0 16px"><h2>Vashasetu setup</h2>';

try {
    $root = null; $lastErr = null;
    foreach (array_unique([DB_HOST, 'localhost']) as $h) {
        try { $root = new PDO('mysql:host=' . $h . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); break; }
        catch (PDOException $e2) { $lastErr = $e2; }
    }
    if (!$root) throw $lastErr;
} catch (PDOException $e) {
    say('Cannot reach MySQL. Open the XAMPP Control Panel and press Start next to MySQL, then reload this page.', false);
    exit;
}
$root->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$root->exec('USE `' . DB_NAME . '`');
$root->exec('SET NAMES utf8mb4');
say('Database "' . DB_NAME . '" ready.');

$sql = file_get_contents(__DIR__ . '/database/schema.sql');
foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', $sql)))) as $stmt) $root->exec($stmt);
say('Tables ready.');
// Non-destructive V6 upgrade: add current district to existing users without dropping rows.
$hasDistrict = $root->query("SHOW COLUMNS FROM `users` LIKE 'current_district'")->fetch();
if (!$hasDistrict) { $root->exec('ALTER TABLE `users` ADD COLUMN `current_district` VARCHAR(70) NULL DEFAULT NULL'); say('Users: added current_district (existing accounts preserved).'); }
$hasSpoken = $root->query("SHOW COLUMNS FROM `users` LIKE 'spoken_language'")->fetch();
if (!$hasSpoken) { $root->exec('ALTER TABLE `users` ADD COLUMN `spoken_language` VARCHAR(60) NULL DEFAULT NULL'); say('Users: added spoken_language (existing accounts preserved).'); }



function csv_rows(string $file): Generator {
    $fh = fopen(__DIR__ . '/database/' . $file, 'r');
    $head = fgetcsv($fh);
    if ($head) $head[0] = preg_replace('/^\x{FEFF}/u', '', $head[0]);
    while (($r = fgetcsv($fh)) !== false) if (count($r) >= count($head)) yield array_combine($head, array_slice($r, 0, count($head)));
    fclose($fh);
}
// Every language is physically partitioned in database/languages/<language>/.
// Validate manifest paths against the fixed language directory; never trust a request path.
function language_csv_rows(string $file): Generator {
    $manifest = __DIR__ . '/database/languages/manifest.csv';
    $requested = $file === 'sentences' ? 'parallel_sentences' : 'word_meanings';
    if (!is_file($manifest)) throw new RuntimeException('Missing language manifest');
    $fh = fopen($manifest, 'r'); $head = fgetcsv($fh);
    $count = 0;
    while (($line = fgetcsv($fh)) !== false) {
        if (count($line) !== count($head)) continue;
        $row = array_combine($head, $line);
        if ($row['dataset'] !== $requested) continue;
        $path = $row['path'];
        if (!preg_match('#^languages/[a-z0-9_-]+/(sentences|words)\.csv$#D', $path)) throw new RuntimeException('Invalid dataset path');
        foreach (csv_rows($path) as $data) {
            if ($data['language'] !== $row['language']) throw new RuntimeException('Language contamination in ' . $path);
            $count++; yield $data;
        }
    }
    fclose($fh);
}

function bulk(PDO $db, string $table, array $cols, iterable $rows, int $batch = 400): int {
    $n = 0; $buf = [];
    $flush = function () use (&$buf, $db, $table, $cols, &$n) {
        if (!$buf) return;
        $one = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
        $st = $db->prepare("INSERT IGNORE INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES " . implode(',', array_fill(0, count($buf), $one)));
        $st->execute(array_merge(...$buf));
        $n += count($buf); $buf = [];
    };
    $db->beginTransaction();
    foreach ($rows as $r) { $buf[] = $r; if (count($buf) >= $batch) $flush(); }
    $flush();
    $db->commit();
    return $n;
}
function load(PDO $db, string $table, callable $make, bool $reset): void {
    global $root;
    // Learning items are referenced by learner progress; never truncate them on reset.
    // Existing lesson content remains in place when setup is re-run.
    if ($reset && !in_array($table, ['learning_units','learning_items'], true)) $db->exec("TRUNCATE TABLE `$table`");
    $have = (int) $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    if ($have > 0) { say("$table: already has " . number_format($have) . " rows (skipped)."); return; }
    $t = microtime(true);
    [$cols, $gen] = $make();
    $n = bulk($db, $table, $cols, $gen);
    say("$table: loaded " . number_format($n) . ' rows (' . round(microtime(true) - $t, 1) . 's).');
}

$varieties = ['Barishal', 'Chattogram', 'Mymensingh', 'Noakhali', 'Rajshahi', 'Sylheti'];   // columns of corpus.csv (newer dialects live in database/languages/)

load($root, 'lexicon', fn() => [['dialect','dialect_word','standard_word','p_std','p_dia','cnt','verified'], (function () {
    foreach (csv_rows('lexicon.csv') as $r) yield [$r['dialect'], $r['dialect_word'], $r['standard_word'], $r['p_to_std'], $r['p_to_dialect'], $r['count'], $r['verified']];
})()], $reset);

load($root, 'sentences', function () use ($varieties) {
    return [['variety','text','standard_text'], (function () use ($varieties) {
        // Example-sentence pool only (dictionary cards): short sentences from the curated sources.
        // Translation itself never uses this table; the big datasets feed the lexicon instead.
        $keep = ['Vashantor' => 1, 'Safin-2025' => 1, 'ONUBAD' => 1, 'BD-Dialect' => 1];
        foreach (csv_rows('corpus.csv') as $r) {
            if (!isset($keep[$r['source']]) || substr_count($r['standard'], ' ') > 11) continue;
            yield ['standard', $r['standard'], $r['standard']];
            foreach ($varieties as $v) if ($r[$v] !== '' && substr_count($r[$v], ' ') <= 11) yield [$v, $r[$v], $r['standard']];
        }
    })()];
}, $reset);

load($root, 'vocab_freq', fn() => [['variety','word','cnt'], (function () {
    foreach (csv_rows('vocab.csv') as $r) yield [$r['variety'], $r['word'], $r['count']];
})()], $reset);

load($root, 'bn_words', fn() => [['word','fkey','freq'], (function () {
    foreach (csv_rows('bn_words.csv') as $r) {
        $k = fold_key(bn_key($r['word']));
        if ($k === '') continue;
        yield [$r['word'], substr($k, 0, 40), $r['freq']];
    }
})()], $reset);

load($root, 'en_bn', fn() => [['english','bangla'], (function () {
    foreach (csv_rows('en_bn.csv') as $r) yield [$r['english'], $r['bangla']];
})()], $reset);

load($root, 'bigrams', fn() => [['w1','w2','cnt'], (function () use ($varieties) {
    $c = [];
    foreach (csv_rows('corpus.csv') as $r) {
        foreach (array_merge(['standard'], $varieties) as $v) {
            $t = $v === 'standard' ? $r['standard'] : ($r[$v] ?? '');
            if ($t === '') continue;
            $w = bn_tokens($t);
            for ($i = 0; $i + 1 < count($w); $i++) { $k = $w[$i] . "\t" . $w[$i + 1]; $c[$k] = ($c[$k] ?? 0) + 1; }
        }
    }
    foreach ($c as $k => $n) { [$a, $b] = explode("\t", $k); yield [$a, $b, $n]; }
})()], $reset);

// New learning tables can be installed on an existing installation by re-opening setup.php.
// --reset / ?reset=1 reloads content but never truncates user learning progress.
load($root, 'language_catalog', fn() => [['language_id','display_name','language_type','existing_app_key','scripts','app_status','geography_nonexclusive','notes'], (function () {
    foreach (csv_rows('language_registry.csv') as $r) yield [$r['language_id'], $r['display_name'], $r['type'], $r['existing_app_key'], $r['scripts'], $r['app_status'], $r['geography_nonexclusive'], $r['notes']];
})()], $reset);
load($root, 'regional_exact_pairs', fn() => [['variety','source_text','standard_bangla','source_dataset','review_status'], (function () {
    foreach (csv_rows('regional_exact_pairs.csv') as $r) yield [$r['variety'], implode(' ', bn_tokens($r['source_text'])), implode(' ', bn_tokens($r['standard_bangla'])), $r['dataset'], $r['review_status']];
})()], $reset);
load($root, 'indigenous_pairs', fn() => [['language_id','source_text','standard_bangla','english','script','source_dataset','review_status','source_url'], (function () {
    foreach (csv_rows('indigenous_pairs.csv') as $r) yield [$r['language_id'], $r['source_text'], $r['standard_bangla'], $r['english'], $r['script'], $r['source_dataset'], $r['review_status'], $r['source_url']];
})()], ($reset || $refreshIndigenous));
load($root, 'learning_units', fn() => [['unit_key','sort_order','stage','title_en','title_bn','description_en'], (function () {
    foreach (csv_rows('learning_units.csv') as $r) yield array_values($r);
})()], $reset);
load($root, 'learning_items', fn() => [['unit_key','item_order','exercise_type','prompt','display_bn','ipa','romanization','options_json','correct_answer','gloss_en','speech_bn','review_status'], (function () {
    foreach (csv_rows('learning_items.csv') as $r) yield array_values($r);
})()], $reset);
load($root, 'learning_localizations', fn() => [['language_id','unit_key','item_order','prompt_native','hint_native','script','audio_path','review_status','reviewer'], (function () {
    foreach (csv_rows('learning_localizations.csv') as $r) yield array_values($r);
})()], ($reset || $refreshLocalizations));



// V3 datasets. Each dataset lives in database/languages/<language>/ and is isolated at load time.
// Run ?refresh_parallel=1 or ?refresh_words=1 to update ONLY the new evidence tables.
load($root, 'parallel_sentences', fn() => [['pair_hash','language','source_text','source_key','standard_bangla','standard_key','source_dataset','review_status','eligible_exact','split_name','script','source_count'], (function () {
    foreach (language_csv_rows('sentences') as $r) yield [$r['pair_hash'],$r['language'],$r['source_text'],bn_normalize($r['source_key']),$r['standard_bangla'],bn_normalize($r['standard_key']),$r['source_dataset'],$r['review_status'],$r['eligible_exact'],$r['split'],$r['script'],$r['source_count']];
})()], ($reset || $refreshParallel));
load($root, 'word_meanings', fn() => [['pair_hash','language','source_word','standard_word','meaning_status','source_dataset','evidence_count'], (function () {
    foreach (language_csv_rows('words') as $r) yield [$r['hash'],$r['language'],bn_normalize($r['source_word']),bn_normalize($r['standard_word']),$r['meaning_status'],$r['source_dataset'],$r['evidence_count']];
})()], ($reset || $refreshWords));

// Re-apply dictionary edits made inside the app.
if (is_file(__DIR__ . '/database/user_edits.csv')) {
    $applied = 0;
    foreach (csv_rows('user_edits.csv') as $e) {
        if (($e['kind'] ?? 'word') === 'phrase') {
            $key = trim(preg_replace('/\s+/u', ' ', preg_replace('/[?!।.,;:"“”\']/u', '', $e['source_word'])));
            $root->prepare('DELETE FROM parallel_sentences WHERE language=? AND source_key=?')->execute([$e['language'],$key]);
            if (($e['action'] ?? 'save') === 'save') {
                $mk = trim(preg_replace('/\s+/u', ' ', preg_replace('/[?!।.,;:"“”\']/u', '', $e['standard_word'])));
                $root->prepare("INSERT IGNORE INTO parallel_sentences (pair_hash,language,source_text,source_key,standard_bangla,standard_key,source_dataset,review_status,eligible_exact,split_name,script,source_count) VALUES (?,?,?,?,?,?, 'Edited in the app','native_speaker_reported',1,'train','Bengali transliteration',1)")
                     ->execute([hash('sha256','editp|'.$e['language'].'|'.$key.'|'.$mk),$e['language'],$e['source_word'],$key,$e['standard_word'],$mk]);
            }
            $applied++; continue;
        }
        if (is_file(__DIR__ . '/api/curated_edit.php') && valid_dialect($e['language'])) { require_once __DIR__ . '/api/curated_edit.php'; vs_curated_edit($e['language'], '', $e['source_word'], ($e['action'] ?? 'save') === 'save' ? $e['standard_word'] : '', $e['action'] ?? 'save'); }
        $root->prepare('DELETE FROM word_meanings WHERE language=? AND source_word=?')->execute([$e['language'],$e['source_word']]);
        if (($e['action'] ?? 'save') === 'save') {
            $root->prepare("INSERT IGNORE INTO word_meanings (pair_hash,language,source_word,standard_word,meaning_status,source_dataset,evidence_count) VALUES (?,?,?,?, 'native_speaker_reported','Edited in the app',1)")
                 ->execute([hash('sha256','edit|'.$e['language'].'|'.$e['source_word'].'|'.$e['standard_word']),$e['language'],$e['source_word'],$e['standard_word']]);
        }
        $applied++;
    }
    say("Dictionary edits re-applied: $applied.");
}

try {
    $bp = (int) $root->query("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'")->fetch(PDO::FETCH_NUM)[1];
    if ($bp > 0 && $bp < 64 * 1048576) {
        say('Speed tip: MySQL is using only ' . round($bp / 1048576) . ' MB of memory for this data, so translating can feel slow. '
          . 'In XAMPP click MySQL → Config → my.ini, change (or add under [mysqld]) the line  innodb_buffer_pool_size=256M , save, then Stop and Start MySQL.', false);
    }
} catch (Throwable $e) { /* ignore */ }
say('Done. Open http://localhost/vashasetu/ to use the app.');
if (!$cli) echo '<p><a href="./">Open Vashasetu</a></p></body>';
