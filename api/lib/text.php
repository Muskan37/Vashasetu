<?php
// ============================================================
// Text helpers: normalisation, script detection, romanisation
// (Bangla -> Roman) and the Banglish phonetic key used to match
// casual Roman typing ("tumi kemon acho") against Bangla words.
// ============================================================

function bn_normalize(string $s): string {
    $s = str_replace(["\u{200D}", "\u{200C}"], '', $s);
    // decompose precomposed nukta letters so everybody types the same thing
    $s = strtr($s, ["\u{09DC}" => "\u{09A1}\u{09BC}", "\u{09DD}" => "\u{09A2}\u{09BC}", "\u{09DF}" => "\u{09AF}\u{09BC}"]);
    $s = preg_replace('/[?!,.;:।"\'()\[\]‘’“”—\-]+/u', ' ', $s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return trim($s);
}

function bn_tokens(string $s): array {
    $s = bn_normalize($s);
    return $s === '' ? [] : preg_split('/\s+/u', $s);
}

/** counts of Bangla-script vs Latin letters */
function script_stats(string $s): array {
    $bn = preg_match_all('/[\x{0980}-\x{09FF}]/u', $s);
    $la = preg_match_all('/[A-Za-z]/u', $s);
    return ['bangla' => (int)$bn, 'latin' => (int)$la];
}

// ---------- Bangla -> Roman (display / pronunciation line) ----------
const BN_CONS = [
    'ক'=>'k','খ'=>'kh','গ'=>'g','ঘ'=>'gh','ঙ'=>'ng','চ'=>'ch','ছ'=>'chh','জ'=>'j','ঝ'=>'jh','ঞ'=>'n',
    'ট'=>'t','ঠ'=>'th','ড'=>'d','ঢ'=>'dh','ণ'=>'n','ত'=>'t','থ'=>'th','দ'=>'d','ধ'=>'dh','ন'=>'n',
    'প'=>'p','ফ'=>'ph','ব'=>'b','ভ'=>'bh','ম'=>'m','য'=>'j','র'=>'r','ল'=>'l','শ'=>'sh','ষ'=>'sh',
    'স'=>'s','হ'=>'h','ৎ'=>'t','ং'=>'ng','ঃ'=>'h',
];
const BN_VOWEL = ['অ'=>'o','আ'=>'a','ই'=>'i','ঈ'=>'i','উ'=>'u','ঊ'=>'u','ঋ'=>'ri','এ'=>'e','ঐ'=>'oi','ও'=>'o','ঔ'=>'ou'];
const BN_SIGN = ['া'=>'a','ি'=>'i','ী'=>'i','ু'=>'u','ূ'=>'u','ৃ'=>'ri','ে'=>'e','ৈ'=>'oi','ো'=>'o','ৌ'=>'ou'];
const BN_DIGIT = ['০'=>'0','১'=>'1','২'=>'2','৩'=>'3','৪'=>'4','৫'=>'5','৬'=>'6','৭'=>'7','৮'=>'8','৯'=>'9'];

function bn_romanize_word(string $w): string {
    $c = mb_str_split($w);
    $n = count($c);
    $out = '';
    for ($i = 0; $i < $n; $i++) {
        $ch = $c[$i];
        $next = $c[$i + 1] ?? '';
        if (isset(BN_DIGIT[$ch])) { $out .= BN_DIGIT[$ch]; continue; }
        if (isset(BN_VOWEL[$ch])) { $out .= BN_VOWEL[$ch]; continue; }
        if ($ch === 'ড' && $next === "\u{09BC}") { $out .= 'r'; $i++; $next = $c[$i + 1] ?? ''; $r = true; }
        elseif ($ch === 'ঢ' && $next === "\u{09BC}") { $out .= 'rh'; $i++; $next = $c[$i + 1] ?? ''; $r = true; }
        elseif ($ch === 'য' && $next === "\u{09BC}") { $out .= 'y'; $i++; $next = $c[$i + 1] ?? ''; $r = true; }
        elseif (isset(BN_CONS[$ch])) { $out .= BN_CONS[$ch]; $r = true; }
        else { if ($ch === 'ঁ' || $ch === "\u{09CD}") continue; $out .= $ch; continue; }
        // after a consonant: explicit vowel sign, virama (conjunct), or inherent 'o'
        if ($next !== '' && isset(BN_SIGN[$next])) { $out .= BN_SIGN[$next]; $i++; }
        elseif ($next === "\u{09CD}") { $i++; }
        elseif ($ch === 'ং' || $ch === 'ঃ' || $ch === 'ৎ') { /* no inherent vowel */ }
        else {
            // inherent vowel: skip at end of word (unless it closes a conjunct), and before chandrabindu-only endings
            $isEnd = ($i + 1 >= $n);
            $prevVirama = ($i > 0 && $c[$i - 1] === "\u{09CD}");
            if (!$isEnd || $prevVirama) $out .= 'o';
        }
    }
    // tidy: "yo" at end is common for ্য words; leave as is
    return $out;
}

function bn_romanize(string $s): string {
    $out = [];
    foreach (preg_split('/(\s+)/u', $s, -1, PREG_SPLIT_DELIM_CAPTURE) as $p) {
        $out[] = preg_match('/[\x{0980}-\x{09FF}]/u', $p) ? bn_romanize_word($p) : $p;
    }
    return implode('', $out);
}

// ---------- Phonetic key for Banglish matching ----------
// A word becomes a list of tokens: consonant tokens (K,KH,G,...) and vowel tokens (A,O,U,I,E,OI,OU).
// Bangla-side inherent 'o' is emitted as 'o' (lowercase) = optional, cheap to skip.

function bn_key(string $w): array {
    static $map = [
        'ক'=>'K','খ'=>'KH','গ'=>'G','ঘ'=>'GH','ঙ'=>'NG','চ'=>'C','ছ'=>'CH','জ'=>'J','ঝ'=>'JH','ঞ'=>'N',
        'ট'=>'T','ঠ'=>'TH','ড'=>'D','ঢ'=>'DH','ণ'=>'N','ত'=>'T','থ'=>'TH','দ'=>'D','ধ'=>'DH','ন'=>'N',
        'প'=>'P','ফ'=>'PH','ব'=>'B','ভ'=>'BH','ম'=>'M','য'=>'J','র'=>'R','ল'=>'L','শ'=>'S','ষ'=>'S',
        'স'=>'S','হ'=>'H','ৎ'=>'T','ং'=>'NG','ঃ'=>'H',
    ];
    static $vow = ['অ'=>'O','আ'=>'A','ই'=>'I','ঈ'=>'I','উ'=>'U','ঊ'=>'U','ঋ'=>'RI','এ'=>'E','ঐ'=>'OI','ও'=>'O','ঔ'=>'OU'];
    static $sign = ['া'=>'A','ি'=>'I','ী'=>'I','ু'=>'U','ূ'=>'U','ৃ'=>'RI','ে'=>'E','ৈ'=>'OI','ো'=>'O','ৌ'=>'OU'];
    $c = mb_str_split(bn_normalize($w));
    $n = count($c);
    $t = [];
    for ($i = 0; $i < $n; $i++) {
        $ch = $c[$i];
        $next = $c[$i + 1] ?? '';
        if (isset($vow[$ch])) { $t[] = $vow[$ch]; continue; }
        $tok = null;
        if (($ch === 'ড' || $ch === 'ঢ') && $next === "\u{09BC}") { $tok = 'R'; $i++; $next = $c[$i + 1] ?? ''; }
        elseif ($ch === 'য' && $next === "\u{09BC}") { $tok = 'Y'; $i++; $next = $c[$i + 1] ?? ''; }
        elseif (isset($map[$ch])) { $tok = $map[$ch]; if ($ch === 'য' && $i > 0 && $c[$i - 1] === "\u{09CD}") $tok = 'Y'; }
        else { continue; }
        $t[] = $tok;
        if ($next !== '' && isset($sign[$next])) { $t[] = $sign[$next]; $i++; }
        elseif ($next === "\u{09CD}") { $i++; }
        elseif ($tok === 'NG' && $ch === 'ং') { }
        elseif ($i + 1 < $n) { $t[] = 'o'; }      // optional inherent vowel (mid-word)
    }
    return key_collapse($t);
}

function key_collapse(array $t): array {
    $o = [];
    foreach ($t as $x) { if ($o && end($o) === $x && !ctype_lower($x)) continue; $o[] = $x; }
    return $o;
}

/** Roman typing -> same token alphabet */
function latin_key(string $w): array {
    $w = strtolower(preg_replace('/[^a-z]/i', '', $w));
    $n = strlen($w);
    $t = [];
    $i = 0;
    $isV = fn($c) => $c !== '' && strpos('aeiou', $c) !== false;
    while ($i < $n) {
        $c = $w[$i];
        $c2 = substr($w, $i, 2);
        $c3 = substr($w, $i, 3);
        $nx = $w[$i + 1] ?? '';
        if ($c3 === 'chh') { $t[] = 'CH'; $i += 3; continue; }
        if ($c3 === 'ngk' || $c3 === 'ngg') { $t[] = 'NG'; $i += 2; continue; }
        switch ($c2) {
            case 'kh': $t[] = 'KH'; $i += 2; continue 2;
            case 'gh': $t[] = 'GH'; $i += 2; continue 2;
            case 'ch': $t[] = 'C';  $i += 2; continue 2;
            case 'jh': $t[] = 'JH'; $i += 2; continue 2;
            case 'th': $t[] = 'TH'; $i += 2; continue 2;
            case 'dh': $t[] = 'DH'; $i += 2; continue 2;
            case 'ph': $t[] = 'PH'; $i += 2; continue 2;
            case 'bh': $t[] = 'BH'; $i += 2; continue 2;
            case 'sh': $t[] = 'S';  $i += 2; continue 2;
            case 'ng': $t[] = 'NG'; $i += 2; continue 2;
            case 'oi': case 'ou': $t[] = strtoupper($c2); $i += 2; continue 2;
            case 'ow': $t[] = 'OU'; $i += 2; continue 2;
        }
        if ($c === 'a') $t[] = 'A';
        elseif ($c === 'e') $t[] = 'E';
        elseif ($c === 'i') $t[] = 'I';
        elseif ($c === 'o') $t[] = 'O';
        elseif ($c === 'u') $t[] = 'U';
        elseif ($c === 'k' || $c === 'q') $t[] = 'K';
        elseif ($c === 'c') $t[] = 'K';
        elseif ($c === 'x') { $t[] = 'K'; $t[] = 'S'; }
        elseif ($c === 'g') $t[] = 'G';
        elseif ($c === 'j' || $c === 'z') $t[] = 'J';
        elseif ($c === 't') $t[] = 'T';
        elseif ($c === 'd') $t[] = 'D';
        elseif ($c === 'n') $t[] = 'N';
        elseif ($c === 'p') $t[] = 'P';
        elseif ($c === 'f') $t[] = 'PH';
        elseif ($c === 'b' || $c === 'v') $t[] = 'B';
        elseif ($c === 'w') $t[] = ($isV($nx) ? 'B' : 'U');
        elseif ($c === 'm') $t[] = 'M';
        elseif ($c === 'r') $t[] = 'R';
        elseif ($c === 'l') $t[] = 'L';
        elseif ($c === 's') $t[] = 'S';
        elseif ($c === 'h') $t[] = 'H';
        elseif ($c === 'y') { $pv = $i > 0 ? $w[$i - 1] : ''; $t[] = ($isV($nx) || $isV($pv)) ? 'Y' : 'I'; }
        $i++;
    }
    // Latin typing: a vowel inside is a real vowel; mark 'o' typed mid-word as normal 'O'
    return key_collapse($t);
}

function is_vowel_tok(string $x): bool {
    return in_array($x, ['A','O','U','I','E','OI','OU','RI','o'], true);
}

/** consonant skeleton folded so aspirates/ch/s/sh and spelling variants meet: used as a DB index key */
function fold_key(array $t): string {
    static $f = ['KH'=>'K','GH'=>'G','TH'=>'T','DH'=>'D','PH'=>'P','BH'=>'B','JH'=>'J','CH'=>'S','C'=>'S','NG'=>'N','Y'=>'J'];
    $o = '';
    foreach ($t as $x) {
        if (is_vowel_tok($x)) continue;
        $x = $f[$x] ?? $x;
        if ($o === '' || substr($o, -1) !== $x) $o .= $x;
    }
    return $o;
}

function key_sub_cost(string $a, string $b): float {
    if ($a === $b) return 0.0;
    $av = is_vowel_tok($a); $bv = is_vowel_tok($b);
    if ($av && $bv) {
        $x = strtoupper($a); $y = strtoupper($b);
        if ($x === $y) return 0.05;
        $pairs = ['OU'=>0.25, 'EI'=>0.3, 'OA'=>0.7, 'AE'=>0.7, 'IU'=>0.8, 'OOI'=>0.5, 'EOI'=>0.4, 'IOI'=>0.6, 'IE'=>0.4, 'AI'=>0.8, 'EO'=>0.4, 'EU'=>0.7];
        $k = $x < $y ? $x . $y : $y . $x;
        $k2 = $y < $x ? $y . $x : $x . $y;
        foreach ([$x . $y, $y . $x] as $kk) if (isset($pairs[$kk])) return $pairs[$kk];
        return 0.9;
    }
    if ($av !== $bv) return 1.5;
    $sim = [
        'K|KH'=>0.15,'G|GH'=>0.15,'T|TH'=>0.15,'D|DH'=>0.15,'P|PH'=>0.15,'B|BH'=>0.15,'J|JH'=>0.15,
        'C|CH'=>0.0,'C|S'=>0.5,'CH|S'=>0.5,'J|Y'=>0.5,'N|NG'=>0.5,'R|D'=>0.8,'PH|B'=>0.9,'T|D'=>0.9,
        'S|CH'=>0.5,'BH|PH'=>0.9,'K|G'=>0.9,'J|C'=>0.9,'T|S'=>1.0,
    ];
    if (isset($sim["$a|$b"])) return $sim["$a|$b"];
    if (isset($sim["$b|$a"])) return $sim["$b|$a"];
    return 1.2;
}

function key_ins_cost(string $x): float {
    if ($x === 'o') return 0.12;
    if (is_vowel_tok($x)) return 0.3;
    if ($x === 'H' || $x === 'Y') return 0.45;
    if ($x === 'R') return 0.8;
    return 1.0;
}

/** weighted edit distance between two token lists */
function key_dist(array $a, array $b): float {
    $la = count($a); $lb = count($b);
    $prev = [0.0];
    for ($j = 1; $j <= $lb; $j++) $prev[$j] = $prev[$j - 1] + key_ins_cost($b[$j - 1]);
    for ($i = 1; $i <= $la; $i++) {
        $cur = [$prev[0] + key_ins_cost($a[$i - 1])];
        for ($j = 1; $j <= $lb; $j++) {
            $cur[$j] = min(
                $prev[$j] + key_ins_cost($a[$i - 1]),
                $cur[$j - 1] + key_ins_cost($b[$j - 1]),
                $prev[$j - 1] + key_sub_cost($a[$i - 1], $b[$j - 1])
            );
        }
        $prev = $cur;
    }
    return $prev[$lb];
}

function key_len_weight(array $a): float {
    $w = 0.0; foreach ($a as $x) $w += is_vowel_tok($x) ? 0.5 : 1.0;
    return max(1.0, $w);
}

function key_rel_dist(array $a, array $b): float {
    return key_dist($a, $b) / max(key_len_weight($a), key_len_weight($b));
}

/** DB lookup keys for a typed Latin word: exact folded key plus one-edit neighbours */
function fold_variants(string $fk): array {
    $v = [$fk => true];
    $n = strlen($fk);
    for ($i = 0; $i < $n; $i++) $v[substr($fk, 0, $i) . substr($fk, $i + 1)] = true;
    foreach (str_split('KGSJTDNPBMRLHY') as $c)
        for ($i = 0; $i <= $n; $i++) $v[substr($fk, 0, $i) . $c . substr($fk, $i)] = true;
    unset($v['']);
    return array_keys($v);
}


// ---------- Avro-style transliteration (Roman -> Bangla letters), used when a word is not in any dictionary ----------
function avro_word(string $w): string {
    $w = strtolower(preg_replace('/[^a-z]/i', '', $w));
    $cons = ['chh'=>'ছ','ngg'=>'ঙ্গ','kh'=>'খ','gh'=>'ঘ','ng'=>'ং','ch'=>'ছ','jh'=>'ঝ','th'=>'থ','dh'=>'ধ','ph'=>'ফ','bh'=>'ভ','sh'=>'শ','rr'=>'ড়',
             'k'=>'ক','g'=>'গ','c'=>'চ','j'=>'জ','t'=>'ত','d'=>'দ','n'=>'ন','p'=>'প','f'=>'ফ','b'=>'ব','v'=>'ভ','m'=>'ম','z'=>'য','r'=>'র','l'=>'ল','s'=>'স','h'=>'হ','y'=>'য়','w'=>'ও','q'=>'ক','x'=>'ক্স'];
    $indep = ['oi'=>'ঐ','ou'=>'ঔ','aa'=>'আ','ii'=>'ঈ','uu'=>'ঊ','a'=>'আ','i'=>'ই','u'=>'উ','e'=>'এ','o'=>'ও'];
    $sign  = ['oi'=>'ৈ','ou'=>'ৌ','aa'=>'া','ii'=>'ী','uu'=>'ূ','a'=>'া','i'=>'ি','u'=>'ু','e'=>'ে','o'=>'ো'];
    $n = strlen($w); $i = 0; $out = ''; $afterCons = false;
    while ($i < $n) {
        $m = null;
        foreach ([3, 2, 1] as $L) { $x = substr($w, $i, $L); if (isset($cons[$x])) { $m = [$x, $cons[$x], 'c']; break; } }
        if ($m === null) foreach ([2, 1] as $L) { $x = substr($w, $i, $L); if (isset($indep[$x])) { $m = [$x, $x, 'v']; break; } }
        if ($m === null) { $i++; continue; }
        $len = strlen($m[0]);
        if ($m[2] === 'c') {
            if ($afterCons) $out .= "\u{09CD}";          // consonant cluster -> hasanta
            $out .= $m[1]; $afterCons = true;
        } else {
            $v = $m[1];
            if ($afterCons) {
                $atEnd = ($i + $len >= $n);
                if ($v === 'o') { if ($atEnd) $out .= 'ো'; }          // inherent vowel mid-word
                else $out .= $sign[$v];
            } else $out .= $indep[$v];
            $afterCons = false;
        }
        // the clustering rule only applies to consecutive consonants with no vowel between
        $i += $len;
    }
    return bn_normalize($out);
}
