<?php
// Keeps database/curated.csv (the trusted word list the translator reads) in step with dictionary edits.
// Several people can edit at the same moment, so the read-change-write is done under a lock and the new file
// replaces the old one in one step (a half-written or emptied file can never be read).
function vs_curated_edit(string $dialect, string $oldWord, string $word, string $meaning, string $action): void {
    $f = __DIR__ . '/../database/curated.csv';
    $lock = @fopen($f . '.lock', 'c');
    if ($lock) flock($lock, LOCK_EX);
    try {
        $rows = [['dialect','dialect_word','standard_word']];
        if (is_file($f) && ($h = fopen($f, 'r'))) {
            fgetcsv($h);
            while (($r = fgetcsv($h)) !== false) if (count($r) >= 3) $rows[] = [$r[0],$r[1],$r[2]];
            fclose($h);
        }
        $kept = [$rows[0]];
        foreach (array_slice($rows, 1) as $r) {
            if ($r[0] !== $dialect) { $kept[] = $r; continue; }
            $drop = ($oldWord !== '' && $r[1] === $oldWord) || $r[1] === $word || ($action === 'save' && $meaning !== '' && $r[2] === $meaning);
            if (!$drop) $kept[] = $r;
        }
        if ($action === 'save' && $meaning !== '') $kept[] = [$dialect, $word, $meaning];
        $tmp = $f . '.tmp' . getmypid();
        if ($h = @fopen($tmp, 'w')) {
            foreach ($kept as $r) fputcsv($h, $r);
            fclose($h);
            if (!@rename($tmp, $f)) { @copy($tmp, $f); @unlink($tmp); }   // Windows: rename over an open file can fail
        }
    } finally {
        if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
    }
}
