<?php
// Shared by api/edit_word.php and admin_edits.php.
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

/** Append edits to database/user_edits.csv (all edits, re-applied by setup.php) and to database/edits_by_ip/<address>.csv. */
function vs_log_edits(array $rows): void {
    $head = ['language','source_word','standard_word','action','time','kind','ip','previous_meaning','user_id'];
    $dir = __DIR__ . '/../database';
    $files = [$dir . '/user_edits.csv'];
    $ip = $rows[0][6] ?? 'unknown';
    if (!is_dir($dir . '/edits_by_ip')) @mkdir($dir . '/edits_by_ip', 0775, true);
    $files[] = $dir . '/edits_by_ip/' . preg_replace('/[^0-9A-Za-z.]/', '_', $ip ?: 'unknown') . '.csv';
    foreach ($files as $f) {
        // older files have no "ip" column yet: rewrite them once with the new header
        if (is_file($f) && ($fh = fopen($f, 'r'))) {
            $first = fgetcsv($fh); fclose($fh);
            if ($first && !in_array('ip', $first, true)) {
                $old = array_map('str_getcsv', file($f, FILE_IGNORE_NEW_LINES)); array_shift($old);
                $w = fopen($f, 'w'); fputcsv($w, $head);
                foreach ($old as $o) if (count($o) >= 4) fputcsv($w, array_merge(array_pad(array_slice($o, 0, 6), 6, ''), ['before-ip-logging', '', '']));
                fclose($w);
            }
        }
        $new = !is_file($f);
        if (!($h = @fopen($f, 'a'))) continue;
        if ($new) fputcsv($h, $head);
        foreach ($rows as $r) fputcsv($h, $r);
        fclose($h);
    }
}
