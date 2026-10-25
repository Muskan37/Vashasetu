<?php
// Review dictionary edits grouped by the editor's internet address, and undo all edits from one address.
// Opens only on the computer running XAMPP: http://localhost/vashasetu/admin_edits.php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/matching_engine.php';
require_once __DIR__ . '/api/word_evidence.php';
require_once __DIR__ . '/api/curated_edit.php';
require_once __DIR__ . '/api/edit_lib.php';
if (!vs_is_local_request()) { http_response_code(403); exit('This page only opens on the computer running XAMPP (http://localhost/vashasetu/admin_edits.php).'); }
header('Content-Type: text/html; charset=utf-8');
$dir = __DIR__ . '/database/edits_by_ip';
$files = is_dir($dir) ? glob($dir . '/*.csv') : [];
function read_edits(string $f): array { $r = array_map('str_getcsv', file($f, FILE_IGNORE_NEW_LINES)); $h = array_shift($r); return array_map(fn($x) => array_combine($h, array_pad($x, count($h), '')), $r); }
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['undo'])) {
    $f = $dir . '/' . basename($_POST['undo']);
    if (is_file($f) && str_ends_with($f, '.csv')) {
        $db = get_db(); $undo = []; $n = 0;
        foreach (array_reverse(read_edits($f)) as $e) {
            if (($e['undone'] ?? '') !== '' || str_starts_with($e['action'], 'undo')) continue;
            $phrase = $e['kind'] === 'phrase'; $prev = $e['previous_meaning'];
            // put back what was there before this edit (or remove the entry if it was new)
            $act = $prev !== '' ? 'save' : 'delete';
            vs_apply_word_edit($db, $e['language'], $e['source_word'], $prev, $act, $phrase);
            if (!$phrase && in_array($e['language'], DIALECTS, true)) vs_curated_edit($e['language'], '', $e['source_word'], $prev, $act);
            $undo[] = [$e['language'], $e['source_word'], $prev, $act, date('c'), $e['kind'], 'undo:' . $e['ip'], $e['standard_word'], ''];
            $n++;
        }
        // record the undo in the main log (so setup.php replays it) and mark the address file as undone
        if ($undo) {
            $h = fopen(__DIR__ . '/database/user_edits.csv', 'a'); foreach ($undo as $u) fputcsv($h, $u); fclose($h);
            $rows = read_edits($f); $head = array_keys($rows[0]); if (!in_array('undone', $head, true)) $head[] = 'undone';
            $w = fopen($f, 'w'); fputcsv($w, $head);
            foreach ($rows as $r) { $r['undone'] = ($r['undone'] ?? '') ?: date('c'); fputcsv($w, array_map(fn($k) => $r[$k] ?? '', $head)); }
            fclose($w);
        }
        $msg = "Undid $n edit(s) from " . htmlspecialchars(basename($f, '.csv')) . '.';
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dictionary edits by address</title>
<style>body{font:15px system-ui,sans-serif;margin:24px;color:#173}h1{font-size:22px}table{border-collapse:collapse;width:100%;margin:8px 0 26px}td,th{border:1px solid #cdd;padding:5px 8px;text-align:left}th{background:#eef5f3}.undone{color:#999;text-decoration:line-through}.box{border:1px solid #cdd;border-radius:10px;padding:12px 16px;margin:14px 0}button{background:#b44932;color:#fff;border:0;border-radius:8px;padding:7px 12px;cursor:pointer}.msg{background:#e5f4ea;padding:8px 12px;border-radius:8px}</style></head><body>
<h1>Dictionary edits, grouped by internet address</h1>
<p>Every word someone changes through the app is listed under the address it came from. "Undo all" puts back what each word was before that person's edits.</p>
<?php if ($msg) echo '<p class="msg">' . $msg . '</p>'; ?>
<?php if (!$files) echo '<p>No edits yet.</p>'; ?>
<?php foreach ($files as $f): $rows = read_edits($f); $ip = basename($f, '.csv'); $open = count(array_filter($rows, fn($r) => ($r['undone'] ?? '') === '')); ?>
<div class="box"><h2 style="font-size:17px;margin:0 0 6px"><?= htmlspecialchars($ip) ?> · <?= count($rows) ?> edit(s)<?= $open ? '' : ' · all undone' ?></h2>
<?php if ($open): ?><form method="post" onsubmit="return confirm('Undo all edits from this address?')"><input type="hidden" name="undo" value="<?= htmlspecialchars(basename($f)) ?>"><button>Undo all <?= $open ?> edit(s) from this address</button></form><?php endif; ?>
<table><tr><th>Time</th><th>Language</th><th>Word</th><th>Meaning before</th><th>New meaning</th><th>Action</th></tr>
<?php foreach (array_reverse($rows) as $r): ?><tr class="<?= ($r['undone'] ?? '') !== '' ? 'undone' : '' ?>"><td><?= htmlspecialchars(substr($r['time'], 0, 16)) ?></td><td><?= htmlspecialchars($r['language']) ?></td><td><?= htmlspecialchars($r['source_word']) ?></td><td><?= htmlspecialchars($r['previous_meaning']) ?></td><td><?= htmlspecialchars($r['standard_word']) ?></td><td><?= htmlspecialchars($r['action']) ?></td></tr><?php endforeach; ?>
</table></div>
<?php endforeach; ?>
</body></html>
