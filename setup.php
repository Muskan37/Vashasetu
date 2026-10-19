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
@set_time_limit(0);
@ini_set('memory_limit', '512M');
header('Content-Type: text/html; charset=utf-8');
$cli = PHP_SAPI === 'cli';
$reset = $cli ? in_array('--reset', $argv ?? [], true) : isset($_GET['reset']);

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

function csv_rows(string $file): Generator {
    $fh = fopen(__DIR__ . '/database/' . $file, 'r');
    $head = fgetcsv($fh);
    if ($head) $head[0] = preg_replace('/^\x{FEFF}/u', '', $head[0]);
    while (($r = fgetcsv($fh)) !== false) if (count($r) >= count($head)) yield array_combine($head, array_slice($r, 0, count($head)));
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
    if ($reset) $db->exec("TRUNCATE TABLE `$table`");
    $have = (int) $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    if ($have > 0) { say("$table: already has " . number_format($have) . " rows (skipped)."); return; }
    $t = microtime(true);
    [$cols, $gen] = $make();
    $n = bulk($db, $table, $cols, $gen);
    say("$table: loaded " . number_format($n) . ' rows (' . round(microtime(true) - $t, 1) . 's).');
}

$varieties = ['Barishal', 'Chattogram', 'Mymensingh', 'Noakhali', 'Rajshahi', 'Sylheti'];

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

try {
    $bp = (int) $root->query("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'")->fetch(PDO::FETCH_NUM)[1];
    if ($bp > 0 && $bp < 64 * 1048576) {
        say('Speed tip: MySQL is using only ' . round($bp / 1048576) . ' MB of memory for this data, so translating can feel slow. '
          . 'In XAMPP click MySQL → Config → my.ini, change (or add under [mysqld]) the line  innodb_buffer_pool_size=256M , save, then Stop and Start MySQL.', false);
    }
} catch (Throwable $e) { /* ignore */ }
say('Done. Open http://localhost/vashasetu/ to use the app.');
if (!$cli) echo '<p><a href="./">Open Vashasetu</a></p></body>';
