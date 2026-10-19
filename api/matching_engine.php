<?php
// ============================================================
// Vashasetu translation engine
//   * detect_text()        – script (Bangla / Banglish / English) + dialect guess
//   * banglish_to_bangla() – understands Roman-script Bangla ("tumi kemon acho")
//   * translate_full()     – any variety -> any variety, pivoting through Standard Bangla
// Dictionary data lives in MySQL (see setup.php); no external API is used.
// ============================================================
require_once __DIR__ . '/lib/text.php';

// Word-by-word translation only (like a statistical translator). Set true to also allow exact stored-sentence lookups.
const USE_SENTENCE_MEMORY = false;
const VARIETIES = ['standard', 'Barishal', 'Chattogram', 'Mymensingh', 'Noakhali', 'Rajshahi', 'Sylheti'];
const RAJSHAHI_PRIOR = 1.2;
const DIALECTS  = ['Barishal', 'Chattogram', 'Mymensingh', 'Noakhali', 'Rajshahi', 'Sylheti'];
const VARIETY_LABEL = [
    'standard' => 'Standard Bangla', 'Barishal' => 'Barishal', 'Chattogram' => 'Chattogram',
    'Mymensingh' => 'Mymensingh', 'Noakhali' => 'Noakhali', 'Rajshahi' => 'Rajshahi', 'Sylheti' => 'Sylheti', 'english' => 'English',
];

function in_placeholders(array $a): string { return implode(',', array_fill(0, count($a), '?')); }


// A few hundred of the most common words, hand-checked, so everyday English <-> Bangla is natural.
const CORE_EN_BN = [
 'i'=>'আমি','you'=>'তুমি','he'=>'সে','she'=>'সে','we'=>'আমরা','they'=>'তারা','it'=>'এটা','me'=>'আমাকে','my'=>'আমার','your'=>'তোমার',
 'is'=>'হয়','am'=>'আমি','are'=>'হও','was'=>'ছিল','be'=>'হওয়া','what'=>'কী','where'=>'কোথায়','when'=>'কখন','why'=>'কেন','how'=>'কেমন','who'=>'কে',
 'which'=>'কোনটা','yes'=>'হ্যাঁ','no'=>'না','not'=>'না','and'=>'এবং','or'=>'অথবা','but'=>'কিন্তু','this'=>'এই','that'=>'ওই','here'=>'এখানে','there'=>'সেখানে',
 'hello'=>'হ্যালো','hi'=>'হ্যালো','thanks'=>'ধন্যবাদ','thank'=>'ধন্যবাদ','please'=>'দয়া করে','sorry'=>'দুঃখিত','good'=>'ভালো','bad'=>'খারাপ','very'=>'খুব',
 'water'=>'পানি','rice'=>'ভাত','food'=>'খাবার','eat'=>'খাওয়া','drink'=>'পান করা','go'=>'যাওয়া','come'=>'আসা','see'=>'দেখা','know'=>'জানা','want'=>'চাওয়া',
 'love'=>'ভালোবাসা','name'=>'নাম','home'=>'বাড়ি','house'=>'বাড়ি','mother'=>'মা','father'=>'বাবা','brother'=>'ভাই','sister'=>'বোন','friend'=>'বন্ধু',
 'today'=>'আজ','tomorrow'=>'আগামীকাল','yesterday'=>'গতকাল','now'=>'এখন','morning'=>'সকাল','night'=>'রাত','day'=>'দিন','money'=>'টাকা','fish'=>'মাছ',
 'tea'=>'চা','book'=>'বই','school'=>'স্কুল','market'=>'বাজার','doctor'=>'ডাক্তার','big'=>'বড়','small'=>'ছোট','new'=>'নতুন','one'=>'এক','two'=>'দুই','three'=>'তিন',
];
function core_bn_en(): array {
    static $m = null;
    if ($m === null) {
        $m = [];
        foreach (array_reverse(CORE_EN_BN, true) as $e => $b) $m[$b] = $e;  // first English key wins for each Bangla word
        $m['আমি'] = 'I'; $m['তুমি'] = 'you'; $m['কী'] = 'what'; $m['কি'] = 'what'; $m['হয়'] = 'is'; $m['সে'] = 'he/she'; $m['আপনি'] = 'you';
        $m['তুই'] = 'you'; $m['খাই'] = 'eat'; $m['খাচ্ছি'] = 'eating'; $m['আছি'] = 'am'; $m['আছো'] = 'are'; $m['আছেন'] = 'are'; $m['আছে'] = 'is';
        $m['যাই'] = 'go'; $m['যাব'] = 'will go'; $m['যাবো'] = 'will go'; $m['করি'] = 'do'; $m['করো'] = 'do'; $m['করছি'] = 'doing'; $m['ভালোবাসি'] = 'love'; $m['কাল'] = 'tomorrow/yesterday'; $m['আজ'] = 'today'; $m['এখন'] = 'now'; $m['বই'] = 'book';
    }
    return $m;
}


// Everyday phrases, hand-checked, so common English <-> Bangla sentences read naturally.
const CORE_PHRASES = [
 'how are you'=>'তুমি কেমন আছো','what is your name'=>'তোমার নাম কী','my name is'=>'আমার নাম','i am fine'=>'আমি ভালো আছি','i love you'=>'আমি তোমাকে ভালোবাসি',
 'good morning'=>'শুভ সকাল','good night'=>'শুভ রাত্রি','good evening'=>'শুভ সন্ধ্যা','thank you'=>'ধন্যবাদ','thank you very much'=>'অনেক ধন্যবাদ','you are welcome'=>'আপনাকেও স্বাগতম',
 'where are you going'=>'তুমি কোথায় যাচ্ছো','where are you from'=>'তুমি কোথা থেকে এসেছো','i do not know'=>'আমি জানি না','i do not understand'=>'আমি বুঝতে পারছি না',
 'have you eaten'=>'তুমি কি খেয়েছো','i am hungry'=>'আমার খিদে পেয়েছে','i am thirsty'=>'আমার পিপাসা পেয়েছে','how much is this'=>'এটার দাম কত','what is this'=>'এটা কী',
 'i want water'=>'আমি পানি চাই','i want to go home'=>'আমি বাড়ি যেতে চাই','come here'=>'এখানে এসো','please help me'=>'দয়া করে আমাকে সাহায্য করুন','i am sorry'=>'আমি দুঃখিত',
 'see you later'=>'পরে দেখা হবে','nice to meet you'=>'আপনার সাথে পরিচিত হয়ে ভালো লাগলো','what are you doing'=>'তুমি কী করছো','i am going home'=>'আমি বাড়ি যাচ্ছি',
 'do you speak bangla'=>'তুমি কি বাংলা বলতে পারো','i speak bangla'=>'আমি বাংলা বলি','how is the weather'=>'আবহাওয়া কেমন','i am happy'=>'আমি খুশি','i am tired'=>'আমি ক্লান্ত',
 'what time is it'=>'এখন কয়টা বাজে','where is the market'=>'বাজার কোথায়','i am a student'=>'আমি একজন ছাত্র','happy birthday'=>'শুভ জন্মদিন','welcome'=>'স্বাগতম',
];
function phrase_en_bn(string $t): ?string { $k = trim(preg_replace('/\s+/', ' ', strtolower(preg_replace("/[^a-z' ]+/i", ' ', $t)))); return CORE_PHRASES[$k] ?? null; }
function phrase_bn_en(string $t): ?string { static $r = null; if ($r === null) { $r = []; foreach (CORE_PHRASES as $e => $b) $r[bn_normalize($b)] = $e; } return $r[bn_normalize($t)] ?? null; }

// ---------- Banglish (Roman-script Bangla) -> Bangla ----------

/** best Bangla spelling(s) for one Roman-typed word */
const ENGLISH_STOP = ['the' => 1, 'a' => 1, 'an' => 1, 'is' => 1, 'are' => 1, 'am' => 1, 'was' => 1, 'were' => 1, 'be' => 1, 'been' => 1, 'i' => 1, 'you' => 1, 'he' => 1, 'she' => 1, 'it' => 1, 'we' => 1, 'they' => 1, 'me' => 1, 'my' => 1, 'your' => 1, 'his' => 1, 'her' => 1, 'our' => 1, 'their' => 1, 'this' => 1, 'that' => 1, 'these' => 1, 'those' => 1, 'what' => 1, 'who' => 1, 'how' => 1, 'why' => 1, 'when' => 1, 'where' => 1, 'which' => 1, 'and' => 1, 'or' => 1, 'but' => 1, 'if' => 1, 'of' => 1, 'to' => 1, 'in' => 1, 'on' => 1, 'at' => 1, 'for' => 1, 'with' => 1, 'from' => 1, 'by' => 1, 'as' => 1, 'not' => 1, 'no' => 1, 'yes' => 1, 'do' => 1, 'does' => 1, 'did' => 1, 'have' => 1, 'has' => 1, 'had' => 1, 'can' => 1, 'will' => 1, 'would' => 1, 'should' => 1, 'could' => 1, 'hello' => 1, 'hi' => 1, 'hey' => 1, 'thanks' => 1, 'thank' => 1, 'please' => 1, 'good' => 1, 'morning' => 1, 'night' => 1, 'day' => 1, 'food' => 1, 'water' => 1, 'eat' => 1, 'drink' => 1, 'go' => 1, 'come' => 1, 'want' => 1, 'need' => 1, 'like' => 1, 'love' => 1, 'know' => 1, 'see' => 1, 'give' => 1, 'take' => 1, 'make' => 1, 'name' => 1, 'home' => 1, 'house' => 1, 'friend' => 1];

function banglish_word(PDO $pdo, string $w): array {
    static $cache = [];
    $w = strtolower(trim($w));
    if (isset($cache[$w])) return $cache[$w];
    // vowel-only little words have no consonant skeleton to match on
    static $VOWEL_WORDS = ['ai' => 'আই', 'ei' => 'এই', 'oi' => 'ওই', 'o' => 'ও', 'e' => 'এ', 'a' => 'আ', 'i' => 'ই', 'u' => 'উ', 'oi' => 'ওই', 'oo' => 'ও', 'aa' => 'আ', 'ee' => 'ই', 'uu' => 'উ'];
    $VOWEL_WORDS += ['kothay' => 'কোথায়', 'kothai' => 'কোথায়', 'kothae' => 'কোথায়'];   // people type these for কোথায়; literal Avro would give কথায়
    if (isset($VOWEL_WORDS[$w])) return $cache[$w] = ['best' => $VOWEL_WORDS[$w], 'rel' => 0.0, 'alts' => [], 'freq' => 50];
    $lk = latin_key($w);
    $fk = fold_key($lk);
    if ($fk === '') return $cache[$w] = ['best' => null, 'rel' => 1.0, 'alts' => [], 'freq' => 0];
    $avro = avro_word($w);

    $cands = [];
    $st = $pdo->prepare('SELECT word, freq FROM bn_words WHERE fkey = ? ORDER BY freq DESC LIMIT 500');
    $st->execute([$fk]);
    foreach ($st->fetchAll() as $r) $cands[$r['word']] = (int) $r['freq'];
    // neighbours (one skeleton letter dropped / extra) only when the exact bucket is thin
    if (count($cands) < 40) {
        $vars = array_values(array_diff(fold_variants($fk), [$fk]));
        foreach (array_chunk($vars, 150) as $chunk) {
            $st = $pdo->prepare('SELECT word, freq FROM bn_words WHERE fkey IN (' . in_placeholders($chunk) . ') ORDER BY freq DESC LIMIT 300');
            $st->execute($chunk);
            foreach ($st->fetchAll() as $r) $cands[$r['word']] = (int) $r['freq'];
        }
    }
    $scored = [];
    foreach ($cands as $bn => $freq) {
        $rel = key_rel_dist($lk, bn_key($bn));
        if ($rel > 0.34) continue;
        // closer spelling wins; common words (seen in the dialect corpus) get a boost
        $score = $rel - 0.12 * log10(1 + $freq) - ($rel < 0.001 ? 0.12 : 0);   // an exact phonetic match beats a merely common neighbour (khamu → খামু, not কাম)
        $scored[] = [$score, $rel, $bn, $freq];
    }
    usort($scored, fn($a, $b) => $a[0] <=> $b[0]);
    $res = ['best' => null, 'rel' => 1.0, 'alts' => [], 'freq' => 0, 'avro' => $avro];
    if (!$scored && $avro !== '') { $res['best'] = $avro; $res['rel'] = 0.5; $res['guess'] = true; }
    // Avro first: if the Avro-style spelling of what was typed is itself a real Bangla word, use it
    if ($avro !== '') {
        $hit = null;
        foreach ($scored as $sc) if ($sc[2] === $avro) { $hit = $sc; break; }
        if ($hit === null) {
            $st = $pdo->prepare('SELECT freq FROM bn_words WHERE word = ? LIMIT 1'); $st->execute([$avro]);
            $f = $st->fetchColumn();
            if ($f !== false) $hit = [0, key_rel_dist($lk, bn_key($avro)), $avro, (int) $f];
        }
        if ($hit !== null && $hit[1] <= 0.34 && !($scored && $scored[0][2] !== $avro && $scored[0][3] > 5 * max(1, $hit[3]) && $scored[0][1] <= 0.15)) { $scored = array_values(array_filter($scored, fn($x) => $x[2] !== $avro)); array_unshift($scored, $hit); }
    }
    if ($scored) {
        $res['best'] = $scored[0][2];
        $res['rel'] = $scored[0][1];
        $res['freq'] = $scored[0][3];
        $res['alts'] = array_slice(array_column($scored, 2), 1, 4);
        $res['cands'] = array_map(fn($x) => ['w' => $x[2], 'rel' => $x[1], 'freq' => $x[3], 'sc' => $x[0]], array_slice($scored, 0, 6));
    }
    return $cache[$w] = $res;
}

function banglish_to_bangla(PDO $pdo, array $tokens): array {
    $info = [];
    foreach ($tokens as $t) $info[] = banglish_word($pdo, $t);
    $n = count($tokens);
    // candidate lists: the chosen word plus any spelling that is nearly as good (these are the "same spelling, different word" cases)
    $cand = [];
    foreach ($info as $i => $r) {
        $list = [];
        $cs = $r['cands'] ?? [];
        if ($cs) {
            $top = $cs[0]['sc'];
            foreach ($cs as $k => $c) {
                // Avro/first choice always stays; others must be close in spelling and score
                if ($k === 0 || ($c['rel'] <= 0.02 && $c['freq'] >= 1 && $c['sc'] - $top <= 0.45)) $list[] = $c;
            }
        } elseif ($r['best'] !== null) $list[] = ['w' => $r['best'], 'rel' => $r['rel'], 'freq' => $r['freq'], 'sc' => 0.0];
        $cand[$i] = $list;
    }
    $choice = [];
    $ambig = false; foreach ($cand as $l) if (count($l) > 1) $ambig = true;
    if ($ambig && $n > 1) {
        // bigram counts between neighbouring candidates
        $big = [];
        for ($i = 0; $i + 1 < $n; $i++) {
            if (!$cand[$i] || !$cand[$i + 1]) continue;
            $a = array_values(array_unique(array_column($cand[$i], 'w'))); $b = array_values(array_unique(array_column($cand[$i + 1], 'w')));
            $st = $pdo->prepare('SELECT w1, w2, cnt FROM bigrams WHERE w1 IN (' . in_placeholders($a) . ') AND w2 IN (' . in_placeholders($b) . ')');
            $st->execute(array_merge($a, $b));
            foreach ($st->fetchAll() as $row) $big[$i][$row['w1'] . "\t" . $row['w2']] = (int) $row['cnt'];
        }
        // Viterbi: lowest total (own score - context bonus) wins
        $best = []; $back = [];
        foreach ($cand as $i => $list) {
            foreach ($list as $k => $c) {
                $own = $c['sc'];
                if ($i === 0 || !$cand[$i - 1]) { $best[$i][$k] = $own; $back[$i][$k] = -1; continue; }
                $bk = null; $bv = INF;
                foreach ($cand[$i - 1] as $pk => $pc) {
                    $cnt = $big[$i - 1][$pc['w'] . "\t" . $c['w']] ?? 0;
                    $v = $best[$i - 1][$pk] + $own - 0.30 * log(1 + $cnt);
                    if ($v < $bv) { $bv = $v; $bk = $pk; }
                }
                $best[$i][$k] = $bv; $back[$i][$k] = $bk;
            }
        }
        // trace back from the end (skip trailing words that had no candidates)
        $last = $n - 1; while ($last >= 0 && !$cand[$last]) $last--;
        $k = $last >= 0 ? array_keys($best[$last], min($best[$last]))[0] : null;
        for ($i = $last; $i >= 0 && $k !== null && $k !== -1; $i--) {
            if (!$cand[$i]) continue;
            $choice[$i] = $k; $k = $back[$i][$k];
            if ($i > 0 && !$cand[$i - 1]) break;
        }
    }
    $out = [];
    foreach ($tokens as $i => $t) {
        $r = $info[$i];
        $bn = $r['best'];
        if (isset($choice[$i]) && isset($cand[$i][$choice[$i]])) $bn = $cand[$i][$choice[$i]]['w'];
        $alts = array_values(array_filter($r['alts'], fn($a) => $a !== $bn));
        if ($bn !== $r['best'] && $r['best'] !== null) array_unshift($alts, $r['best']);
        if (!empty($r['avro']) && $r['avro'] !== $bn && !in_array($r['avro'], $alts, true)) $alts[] = $r['avro'];
        $out[] = ['src' => $t, 'bn' => $bn ?? $t, 'ok' => $r['best'] !== null && empty($r['guess']), 'rel' => $r['rel'], 'alts' => array_slice($alts, 0, 5), 'context' => ($bn !== $r['best'])];
    }
    return $out;
}

// ---------- Detection ----------

function english_word_known(PDO $pdo, array $words): array {
    $words = array_values(array_unique(array_map('strtolower', $words)));
    if (!$words) return [];
    $st = $pdo->prepare('SELECT english FROM en_bn WHERE english IN (' . in_placeholders($words) . ')');
    $st->execute($words);
    return array_flip(array_column($st->fetchAll(), 'english'));
}

function detect_variety(PDO $pdo, array $tokens): array {
    static $totals = null, $vsize = null;
    if ($totals === null) {
        // The two totals below never change between data reloads, but computing them scans the whole
        // vocabulary table, so cache them in a small temp file keyed by the data file's date and size.
        $vf = __DIR__ . '/../database/vocab.csv';
        $cf = sys_get_temp_dir() . '/vashasetu_vocab_' . md5(__DIR__ . (is_file($vf) ? filemtime($vf) . filesize($vf) : '')) . '.json';
        $cached = is_file($cf) ? json_decode((string) @file_get_contents($cf), true) : null;
        if (is_array($cached) && isset($cached['t'], $cached['v'])) { $totals = $cached['t']; $vsize = (float) $cached['v']; }
        else {
            $totals = [];
            foreach ($pdo->query('SELECT variety, SUM(cnt) s FROM vocab_freq GROUP BY variety')->fetchAll() as $r) $totals[$r['variety']] = (float) $r['s'];
            $vsize = (float) $pdo->query('SELECT COUNT(DISTINCT word) FROM vocab_freq')->fetchColumn();
            if ($totals) @file_put_contents($cf, json_encode(['t' => $totals, 'v' => $vsize]));
        }
    }
    $tokens = array_values(array_unique($tokens));
    $score = array_fill_keys(VARIETIES, 0.0);
    $known = 0;
    if ($tokens) {
        $st = $pdo->prepare('SELECT variety, word, cnt FROM vocab_freq WHERE word IN (' . in_placeholders($tokens) . ')');
        $st->execute($tokens);
        $counts = [];
        foreach ($st->fetchAll() as $r) $counts[$r['word']][$r['variety']] = (float) $r['cnt'];
        $alpha = 0.1;
        foreach ($counts as $w => $per) {
            $known++;
            foreach (VARIETIES as $v) $score[$v] += log((($per[$v] ?? 0) + $alpha) / (($totals[$v] ?? 1) + $alpha * $vsize));
        }
    }
    if ($known === 0) return ['variety' => 'standard', 'confidence' => 0.0, 'scores' => [], 'known' => 0];
    // Rajshahi has far less training text than the others, so everyday words it happens to contain would otherwise look
    // "too Rajshahi". A small prior keeps plain Standard Bangla from being mistaken for it.
    if (isset($score['Rajshahi'])) $score['Rajshahi'] -= RAJSHAHI_PRIOR * min(3, $known);
    $max = max($score);
    $p = []; $z = 0.0;
    foreach ($score as $v => $s) { $p[$v] = exp($s - $max); $z += $p[$v]; }
    arsort($p);
    $scores = [];
    foreach ($p as $v => $x) $scores[] = ['variety' => $v, 'label' => VARIETY_LABEL[$v], 'p' => round($x / $z, 3)];
    return ['variety' => $scores[0]['variety'], 'confidence' => $scores[0]['p'], 'scores' => $scores, 'known' => $known];
}

/**
 * Work out what the person typed. Returns script (bangla|banglish|english|other),
 * the Bangla form of the text (transliterated when it was Banglish), and the dialect guess.
 */
function detect_text(PDO $pdo, string $text, string $hint = 'auto'): array {
    $text = trim($text);
    $stats = script_stats($text);
    $res = ['script' => 'other', 'variety' => 'standard', 'confidence' => 0.0, 'scores' => [], 'bangla_text' => $text, 'transliteration' => null];
    if ($stats['bangla'] === 0 && $stats['latin'] === 0) return $res;

    if ($stats['bangla'] >= $stats['latin']) {
        $res['script'] = 'bangla';
        $d = detect_variety($pdo, bn_tokens($text));
        return array_merge($res, $d);
    }
    // Roman letters: English or Banglish?
    $toks = array_values(array_filter(preg_split('/[^A-Za-z\x{0980}-\x{09FF}]+/u', strtolower($text))));
    $eng = english_word_known($pdo, $toks);
    $bnScore = 0; $enScore = 0; $conv = []; $solid = 0; $stop = 0;
    foreach ($toks as $t) {
        $r = banglish_word($pdo, $t);
        $strong = $r['best'] !== null && $r['rel'] <= 0.15 && $r['freq'] > 0 && mb_strlen($r['best']) >= 2;
        if ($strong) $bnScore++;
        if (isset($eng[$t])) $enScore++;
        if ($strong && $r['rel'] < 0.001 && $r['freq'] >= 10) $solid++;
        if (isset(ENGLISH_STOP[$t])) $stop++;
    }
    // every word is both a dictionary English word and a solid Bangla spelling (ami bhat khai): no real English word → Banglish
    $tieBanglish = $stop === 0 && $solid === count($toks);
    $isEnglish = $hint === 'english' || ($hint === 'auto' && ($enScore > $bnScore || ($stop * 2 > count($toks) && $enScore >= 2) || ($enScore === $bnScore && $enScore > 0 && $enScore === count($toks) && !$tieBanglish)));
    if ($hint === 'banglish') $isEnglish = false;
    if ($isEnglish) { $res['script'] = 'english'; $res['variety'] = 'english'; $res['confidence'] = 0.9; return $res; }

    $conv = banglish_to_bangla($pdo, $toks);
    $bn = implode(' ', array_column($conv, 'bn'));
    $res['script'] = 'banglish';
    $res['bangla_text'] = $bn;
    $res['transliteration'] = $conv;
    $d = detect_variety($pdo, bn_tokens($bn));
    return array_merge($res, $d);
}

// ---------- Dictionary lookups ----------

function approved_contrib(PDO $pdo, string $dialect): array {
    $st = $pdo->prepare("SELECT dialect_word, meaning FROM contributions WHERE status = 'approved' AND dialect_type = ?");
    $st->execute([$dialect]);
    $m = [];
    foreach ($st->fetchAll() as $r) $m[bn_normalize($r['dialect_word'])] = bn_normalize($r['meaning']);
    return $m;
}

/** Pick one candidate per position using target-side bigram counts.
 *  $cands[i] = list of [word, score(higher=better), ...extra]. Returns chosen index per position. */
function context_pick(PDO $pdo, array $cands): array {
    $n = count($cands);
    $pick = array_fill(0, $n, 0);
    $multi = false;
    foreach ($cands as $c) if (count($c) > 1) { $multi = true; break; }
    if (!$multi) return $pick;
    $words = [];
    foreach ($cands as $c) foreach ($c as $x) $words[$x[0]] = 1;
    $words = array_keys($words);
    $bg = [];
    if (count($words) <= 400) {
        $ph = in_placeholders($words);
        $st = $pdo->prepare("SELECT w1, w2, cnt FROM bigrams WHERE w1 IN ($ph) AND w2 IN ($ph)");
        $st->execute(array_merge($words, $words));
        foreach ($st->fetchAll() as $r) $bg[$r['w1'] . "\t" . $r['w2']] = (int) $r['cnt'];
    }
    $W = 0.7;
    $dp = []; $bp = [];
    foreach ($cands[0] as $j => $x) { $dp[0][$j] = $x[1]; }
    for ($i = 1; $i < $n; $i++) {
        foreach ($cands[$i] as $j => $x) {
            $best = -1e9; $arg = 0;
            foreach ($cands[$i - 1] as $k => $y) {
                $v = $dp[$i - 1][$k] + $x[1] + $W * log(1 + ($bg[$y[0] . "\t" . $x[0]] ?? 0));
                if ($v > $best) { $best = $v; $arg = $k; }
            }
            $dp[$i][$j] = $best; $bp[$i][$j] = $arg;
        }
    }
    $bj = 0; $bv = -1e9;
    foreach ($dp[$n - 1] as $j => $v) if ($v > $bv) { $bv = $v; $bj = $j; }
    for ($i = $n - 1; $i >= 0; $i--) { $pick[$i] = $bj; if ($i > 0) $bj = $bp[$i][$bj]; }
    return $pick;
}

/** Hand-verified corrections read straight from database/curated.csv (no database reload needed). */
function curated_pairs(): array {
    static $c = null;
    if ($c !== null) return $c;
    $c = [];
    $f = __DIR__ . '/../database/curated.csv';
    if (is_file($f) && ($h = fopen($f, 'r'))) {
        fgetcsv($h);
        while (($r = fgetcsv($h)) !== false) {
            if (count($r) < 3 || $r[1] === '' || $r[2] === '') continue;
            $c[$r[0]][bn_normalize($r[1])][] = bn_normalize($r[2]);
        }
        fclose($h);
    }
    return $c;
}

/** Pool meanings from every dialect, weighted by how likely each dialect is (used when the dialect guess is unsure). */
function pooled_dialect_candidates(PDO $pdo, array $tokens, array $post): array {
    $uniq = array_values(array_unique($tokens));
    $by = [];
    if (!$uniq) return $by;
    $st = $pdo->prepare('SELECT dialect, dialect_word, standard_word, p_std, verified FROM lexicon WHERE p_std > 0 AND dialect_word IN (' . in_placeholders($uniq) . ')');
    $st->execute($uniq);
    foreach ($st->fetchAll() as $r) {
        $w = 0.3 + 0.7 * ($post[$r['dialect']] ?? 0.0);
        $v = $w * ((float) $r['p_std'] + ($r['verified'] ? 1 : 0));
        $k = $r['dialect_word']; $c = $r['standard_word'];
        if (!isset($by[$k][$c])) $by[$k][$c] = [$c, 0.0, 0.0, 0];
        $by[$k][$c][1] += $v;
        $by[$k][$c][2] = max($by[$k][$c][2], (float) $r['p_std']);
        $by[$k][$c][3] = max($by[$k][$c][3], (int) $r['verified']);
    }
    return $by;
}

function dialect_to_standard(PDO $pdo, string $dialect, array $tokens, ?array $post = null): array {
    $contrib = approved_contrib($pdo, $dialect);
    $pooled = $post ? pooled_dialect_candidates($pdo, $tokens, $post) : [];
    $uniq = array_values(array_unique($tokens));
    $map = []; $alt = [];
    if ($uniq && !$post) {
        $st = $pdo->prepare('SELECT dialect_word, standard_word, p_std, verified FROM lexicon WHERE dialect = ? AND p_std > 0 AND dialect_word IN (' . in_placeholders($uniq) . ')');
        $st->execute(array_merge([$dialect], $uniq));
        foreach ($st->fetchAll() as $r) {
            $k = $r['dialect_word'];
            $sc = (float) $r['p_std'] + ($r['verified'] ? 1 : 0);
            $alt[$k][] = [$r['standard_word'], $sc, (float) $r['p_std'], (int) $r['verified']];
            if (!isset($map[$k]) || $sc > $map[$k][1]) $map[$k] = [$r['standard_word'], $sc, (float) $r['p_std'], (int) $r['verified']];
        }
    }
    $cur = curated_pairs();
    foreach ($tokens as $k) {            // verified corrections win over learned pairs
        $dlist = $post ? array_keys($cur) : [$dialect];
        foreach ($dlist as $dd) {
            if (!isset($cur[$dd][$k])) continue;
            $wgt = $post ? (0.3 + 0.7 * ($post[$dd] ?? 0.0)) : 1.0;
            foreach ($cur[$dd][$k] as $std) {
                if ($post) { $pooled[$k][$std] = $pooled[$k][$std] ?? [$std, 0.0, 0.95, 1]; $pooled[$k][$std][1] += $wgt * 1.95; $pooled[$k][$std][3] = 1; }
                else { $alt[$k][] = [$std, 1.95, 0.95, 1]; if (!isset($map[$k]) || 1.95 > $map[$k][1]) $map[$k] = [$std, 1.95, 0.95, 1]; }
            }
        }
    }
    foreach ($pooled as $k => $cl) {      // pooled mode: replace the single-dialect candidates
        $alt[$k] = array_values($cl);
        usort($alt[$k], fn($a, $b) => $b[1] <=> $a[1]);
        $map[$k] = $alt[$k][0];
    }
    // context: re-choose among near-best candidates using standard-side bigrams
    $ctx = [];
    foreach ($tokens as $i => $w) {
        $c = [];
        if (isset($alt[$w]) && !isset($contrib[$w])) {
            usort($alt[$w], fn($a, $b) => $b[1] <=> $a[1]);
            $top = $alt[$w][0][1];
            foreach (array_slice($alt[$w], 0, 4) as $a) if ($a[1] >= $top - 0.6) $c[] = $a;
        }
        $ctx[$i] = $c;
    }
    $ctxPick = [];
    if (count($tokens) > 1) {
        $cl = [];
        foreach ($tokens as $i => $w) $cl[$i] = $ctx[$i] ?: [[$w, 0.0, 0.0, 0]];
        $pk = context_pick($pdo, array_values($cl));
        foreach ($tokens as $i => $w) if ($ctx[$i] && count($ctx[$i]) > 1) $ctxPick[$i] = $ctx[$i][$pk[$i]];
    }
    $out = [];
    $n = count($tokens);
    for ($i = 0; $i < $n; ) {
        // approved user contributions: longest phrase first (up to 4 words)
        $done = false;
        for ($len = min(4, $n - $i); $len >= 1 && $contrib; $len--) {
            $chunk = implode(' ', array_slice($tokens, $i, $len));
            if (isset($contrib[$chunk])) {
                $out[] = ['src' => $chunk, 'std' => $contrib[$chunk], 'matched' => true, 'kind' => 'community', 'conf' => 1.0];
                $i += $len; $done = true; break;
            }
        }
        if ($done) continue;
        $w = $tokens[$i];
        if (isset($map[$w])) {
            $m = $ctxPick[$i] ?? $map[$w];
            $same = $m[0] === $w;
            $out[] = ['src' => $w, 'std' => $m[0], 'matched' => !$same, 'kind' => $m[3] ? 'verified' : 'learned', 'conf' => round($m[2], 2)];
        } else {
            $out[] = ['src' => $w, 'std' => $w, 'matched' => false, 'kind' => 'unknown', 'conf' => 0];
        }
        $i++;
    }
    return $out;
}

function standard_to_dialect(PDO $pdo, string $dialect, array $tokens): array {
    $uniq = array_values(array_unique($tokens));
    $map = []; $alt = [];
    if ($uniq) {
        $st = $pdo->prepare('SELECT standard_word, dialect_word, p_dia, p_std, verified FROM lexicon WHERE dialect = ? AND (p_dia + p_std) >= 0.55 AND standard_word IN (' . in_placeholders($uniq) . ')');
        $st->execute(array_merge([$dialect], $uniq));
        foreach ($st->fetchAll() as $r) {
            $k = $r['standard_word'];
            $sc = (float) $r['p_dia'] + (float) $r['p_std'] + ($r['verified'] ? 1 : 0);
            $c3 = min(1.0, ((float) $r['p_dia'] + (float) $r['p_std']) / 2);
            $alt[$k][] = [$r['dialect_word'], $sc, $c3];
            if (!isset($map[$k]) || $sc > $map[$k][1]) $map[$k] = [$r['dialect_word'], $sc, $c3];
        }
    }
    foreach (curated_pairs()[$dialect] ?? [] as $dw => $stds) {      // verified corrections (reverse direction)
        foreach ($stds as $std) if (in_array($std, $tokens, true)) { $alt[$std][] = [$dw, 1.95, 0.95]; if (!isset($map[$std]) || 1.95 > $map[$std][1]) $map[$std] = [$dw, 1.95, 0.95]; }
    }
    $ctxPick = [];
    if (count($tokens) > 1) {
        $cl = []; $any = false;
        foreach ($tokens as $i => $w) {
            $c = [];
            if (isset($alt[$w])) {
                usort($alt[$w], fn($a, $b) => $b[1] <=> $a[1]);
                $top = $alt[$w][0][1];
                foreach (array_slice($alt[$w], 0, 4) as $a) if ($a[1] >= $top - 0.6) $c[] = $a;
            }
            if (count($c) > 1) $any = true;
            $cl[$i] = $c ?: [[$w, 0.0, 0.0]];
        }
        if ($any) { $pk = context_pick($pdo, array_values($cl)); foreach ($tokens as $i => $w) if (count($cl[$i]) > 1) $ctxPick[$i] = $cl[$i][$pk[$i]]; }
    }
    $out = [];
    foreach ($tokens as $i => $w) {
        if (isset($map[$w])) { $m = $ctxPick[$i] ?? $map[$w]; $out[] = ['std' => $w, 'tgt' => $m[0], 'matched' => $m[0] !== $w, 'conf' => round($m[2], 2)]; }
        else $out[] = ['std' => $w, 'tgt' => $w, 'matched' => false, 'conf' => 0];
    }
    return $out;
}

function english_to_bangla(PDO $pdo, array $words): array {
    $lw = array_map('strtolower', $words);
    $uniq = array_values(array_unique($lw));
    $map = [];
    if ($uniq) {
        $st = $pdo->prepare('SELECT english, bangla FROM en_bn WHERE english IN (' . in_placeholders($uniq) . ')');
        $st->execute($uniq);
        foreach ($st->fetchAll() as $r) $map[$r['english']] = bn_normalize($r['bangla']);
    }
    $out = [];
    foreach ($lw as $i => $w) {
        if (!isset($map[$w]) && strlen($w) > 3) {   // crude plural / verb endings
            foreach (['s', 'es', 'ed', 'ing', 'd'] as $suf) {
                if (str_ends_with($w, $suf) && isset($map[substr($w, 0, -strlen($suf))])) { $map[$w] = $map[substr($w, 0, -strlen($suf))]; break; }
            }
            if (!isset($map[$w])) {
                foreach (['s', 'es', 'ed', 'ing', 'd'] as $suf) {
                    if (str_ends_with($w, $suf)) {
                        $base = substr($w, 0, -strlen($suf));
                        $st = $pdo->prepare('SELECT bangla FROM en_bn WHERE english = ?'); $st->execute([$base]);
                        $b = $st->fetchColumn(); if ($b !== false) { $map[$w] = bn_normalize($b); break; }
                    }
                }
            }
        }
        if (isset(CORE_EN_BN[$w])) $map[$w] = CORE_EN_BN[$w];
        $out[] = ['src' => $words[$i], 'std' => $map[$w] ?? $words[$i], 'matched' => isset($map[$w]), 'kind' => 'english', 'conf' => 1];
    }
    return $out;
}

// ---------- Standard Bangla -> English word order (SOV -> SVO) ----------
function bn_verb_forms(): array {
    static $F = null;
    if ($F !== null) return $F;
    // [present stem, vowel-stem?, perfect stem, past stem, base, 3sg, -ing, past, participle]
    $V = [
        ['খা', 1, 'খেয়ে', 'খে', 'eat', 'eats', 'eating', 'ate', 'eaten'],
        ['যা', 1, 'গিয়ে', 'গে', 'go', 'goes', 'going', 'went', 'gone'],
        ['কর', 0, 'করে', 'কর', 'do', 'does', 'doing', 'did', 'done'],
        ['দেখ', 0, 'দেখে', 'দেখ', 'see', 'sees', 'seeing', 'saw', 'seen'],
        ['আস', 0, 'এসে', 'এ', 'come', 'comes', 'coming', 'came', 'come'],
        ['পড়', 0, 'পড়ে', 'পড়', 'read', 'reads', 'reading', 'read', 'read'],
        ['লিখ', 0, 'লিখে', 'লিখ', 'write', 'writes', 'writing', 'wrote', 'written'],
        ['কিন', 0, 'কিনে', 'কিন', 'buy', 'buys', 'buying', 'bought', 'bought'],
        ['জান', 0, 'জেনে', 'জান', 'know', 'knows', 'knowing', 'knew', 'known'],
        ['বল', 0, 'বলে', 'বল', 'say', 'says', 'saying', 'said', 'said'],
        ['খেল', 0, 'খেলে', 'খেল', 'play', 'plays', 'playing', 'played', 'played'],
        ['শুন', 0, 'শুনে', 'শুন', 'hear', 'hears', 'hearing', 'heard', 'heard'],
        ['চল', 0, 'চলে', 'চল', 'walk', 'walks', 'walking', 'walked', 'walked'],
        ['ঘুমা', 1, 'ঘুমিয়ে', 'ঘুমা', 'sleep', 'sleeps', 'sleeping', 'slept', 'slept'],
        ['পাঠা', 1, 'পাঠিয়ে', 'পাঠা', 'send', 'sends', 'sending', 'sent', 'sent'],
        ['ভালোবাস', 0, 'ভালোবেসে', 'ভালোবাস', 'love', 'loves', 'loving', 'loved', 'loved'],
    ];
    $F = [];
    foreach ($V as $v) {
        [$st, $vow, $pf, $ps, $b, $s3, $ing, $pa, $pp] = $v;
        $info = ['b' => $b, 's3' => $s3, 'ing' => $ing, 'pa' => $pa, 'pp' => $pp];
        $pres = $vow ? ['ই', 'ও', 'স', 'ন', 'য়'] : ['ি', 'ো', 'িস', 'েন', 'ে'];
        foreach ($pres as $k => $suf) $F[$st . $suf] = [$info, 'pres'];
        if (!$vow) { $F[$st . 'ি'] = [$info, 'pres']; }
        $cont = $vow ? ['চ্ছি', 'চ্ছ', 'চ্ছিস', 'চ্ছেন', 'চ্ছে'] : ['ছি', 'ছ', 'ছিস', 'ছেন', 'ছে'];
        foreach ($cont as $suf) $F[$st . $suf] = [$info, 'cont'];
        foreach (['ব', 'বো', 'বে', 'বি', 'বেন'] as $suf) $F[$st . $suf] = [$info, 'fut'];
        foreach (['ছি', 'ছ', 'ছো', 'ছিস', 'ছেন', 'ছে'] as $suf) $F[$pf . $suf] = [$info, 'perf'];
        foreach (['লাম', 'লে', 'লি', 'লেন', 'ল'] as $suf) $F[$ps . $suf] = [$info, 'past'];
    }
    // plain copulas
    foreach (['আছি' => 'am', 'আছো' => 'are', 'আছ' => 'are', 'আছিস' => 'are', 'আছেন' => 'are', 'আছে' => 'is'] as $bn => $en) $F[$bn] = [['cop' => $en], 'cop'];
    return $F;
}
function bn_subject_info(string $tok): ?array {
    static $S = null;
    if ($S === null) $S = ['আমি' => ['I', 'am', 0], 'আমরা' => ['we', 'are', 0], 'তুমি' => ['you', 'are', 0], 'তুই' => ['you', 'are', 0],
        'আপনি' => ['you', 'are', 0], 'আপনারা' => ['you', 'are', 0], 'তোমরা' => ['you', 'are', 0], 'তারা' => ['they', 'are', 0], 'তাঁরা' => ['they', 'are', 0],
        'সে' => ['he/she', 'is', 1], 'ও' => ['he/she', 'is', 1], 'তিনি' => ['he/she', 'is', 1], 'এটা' => ['it', 'is', 1], 'ওটা' => ['it', 'is', 1]];
    return $S[$tok] ?? null;
}
/** Handle common case endings: -ে/-তে (in/at), -কে (to), -র/-ের (of) on nouns. */
function bn_en_case_word(PDO $pdo, string $tok, string $en, bool $motion = false): string {
    static $st = null;
    if ($st === null) $st = $pdo->prepare('SELECT english FROM en_bn WHERE bangla = ? AND CHAR_LENGTH(english) >= 3 ORDER BY CHAR_LENGTH(english) ASC LIMIT 1');
    $core = core_bn_en();
    $rules = [['কে', 'to '], ['ের', 'of '], ['তে', 'in '], ['ে', 'in/at '], ['র', 'of ']];
    if (isset($core[$tok]) || mb_strlen($tok) < 4) return $en;
    foreach ($rules as [$suf, $prep]) {
        if (mb_substr($tok, -mb_strlen($suf)) !== $suf) continue;
        $stem = mb_substr($tok, 0, mb_strlen($tok) - mb_strlen($suf));
        $e = $core[$stem] ?? null;
        if ($e === null) { $st->execute([$stem]); $f = $st->fetchColumn(); $e = $f !== false ? $f : null; }
        if ($e !== null && mb_strlen($stem) >= 2) return ($motion && in_array($suf, ['ে', 'তে'], true) ? 'to ' : $prep) . $e;
    }
    return $en;
}
function bn_en_reorder(PDO $pdo, array $rows, array $tok): ?string {
    $n = count($tok);
    if ($n < 2) return null;
    $F = bn_verb_forms();
    $neg = false; $last = $n - 1;
    if ($tok[$last] === 'না' || $tok[$last] === 'নি') { $neg = true; $last--; }
    if ($last < 1 || !isset($F[$tok[$last]])) return null;
    [$vi, $tense] = $F[$tok[$last]];
    $sub = bn_subject_info($tok[0]);
    $subEn = $sub ? $sub[0] : $rows[0]['tgt'];
    $aux = $sub ? $sub[1] : 'is';
    $third = $sub ? $sub[2] === 1 : true;
    $mid = [];
    $wh = null;
    for ($i = 1; $i < $last; $i++) {
        if (($tok[$i] === 'কী' || $tok[$i] === 'কি' || $tok[$i] === 'কোথায়' || $tok[$i] === 'কেন' || $tok[$i] === 'কখন') && $wh === null && $tense !== 'cop') { $wh = $rows[$i]['tgt']; continue; }
        $mid[] = bn_en_case_word($pdo, $tok[$i], $rows[$i]['tgt'], in_array($vi['b'] ?? '', ['go', 'come'], true));
    }
    $verb = '';
    if ($tense === 'cop') {
        $verb = $neg ? $aux . ' not' : $aux;
        $res = $subEn . ' ' . $verb . ($mid ? ' ' . implode(' ', $mid) : '');
        return $res;
    }
    $hv = $third ? 'has' : 'have'; $do = $third ? 'does' : 'do';
    $b = $vi['b'];
    if ($wh !== null) {
        $aux2 = ['pres' => $do, 'cont' => $aux, 'fut' => 'will', 'perf' => $hv, 'past' => 'did'][$tense];
        $vb = ['pres' => $b, 'cont' => $vi['ing'], 'fut' => $b, 'perf' => $vi['pp'], 'past' => $b][$tense];
        $res = $wh . ' ' . $aux2 . ($neg ? ' not' : '') . ' ' . $subEn . ' ' . $vb . ($mid ? ' ' . implode(' ', $mid) : '');
        return ucfirst($res);
    }
    if ($neg) {
        $verb = ['pres' => "$do not $b", 'cont' => "$aux not {$vi['ing']}", 'fut' => "will not $b", 'perf' => "$hv not {$vi['pp']}", 'past' => "did not $b"][$tense];
    } else {
        $verb = ['pres' => $third ? $vi['s3'] : $b, 'cont' => "$aux {$vi['ing']}", 'fut' => "will $b", 'perf' => "$hv {$vi['pp']}", 'past' => $vi['pa']][$tense];
    }
    return $subEn . ' ' . $verb . ($mid ? ' ' . implode(' ', $mid) : '');
}

function bangla_to_english(PDO $pdo, array $tokens): array {
    $out = [];
    $st = $pdo->prepare('SELECT english FROM en_bn WHERE bangla = ? AND CHAR_LENGTH(english) >= 3 ORDER BY CHAR_LENGTH(english) ASC LIMIT 1');
    $core = core_bn_en();
    foreach ($tokens as $w) {
        if (isset($core[$w])) { $out[] = ['std' => $w, 'tgt' => $core[$w], 'matched' => true, 'conf' => 1]; continue; }
        $st->execute([$w]);
        $e = $st->fetchColumn();
        $out[] = ['std' => $w, 'tgt' => $e !== false ? $e : $w, 'matched' => $e !== false, 'conf' => $e !== false ? 1 : 0];
    }
    return $out;
}

// ---------- Translate ----------

/**
 * $src: auto | banglish | english | standard | <dialect>      $tgt: standard | <dialect> | english
 */
function translate_core(PDO $pdo, string $text, string $src = 'auto', string $tgt = 'standard'): array {
    $text = trim($text);
    $notes = [];
    if ($tgt !== 'english' && !in_array($tgt, VARIETIES, true)) $tgt = 'standard';

    $det = detect_text($pdo, $text, $src === 'banglish' ? 'banglish' : ($src === 'english' ? 'english' : 'auto'));
    $script = $det['script'];

    // decide the source variety
    if ($src === 'auto' || $src === 'banglish') $srcVar = $det['variety'];
    elseif ($src === 'english') $srcVar = 'english';
    else $srcVar = in_array($src, VARIETIES, true) ? $src : 'standard';
    if ($script === 'english') $srcVar = 'english';
    if ($srcVar === 'english' && $script !== 'english' && $src === 'english') $script = 'english';

    $banglaText = $det['bangla_text'];
    if ($script === 'banglish') {
        $notes[] = 'Read as Banglish (Roman-script Bangla) and converted to Bangla script.';
    }

    $breakdown = [];
    $memory = false;
    $standardTokens = [];

    // ---- source -> Standard Bangla (pivot) ----
    if ($srcVar === 'english') {
        $words = array_values(array_filter(preg_split('/[^A-Za-z\']+/', expand_contractions($text))));
        $n = count($words); $stdParts = []; $usedPhrase = false; $i = 0; $pending = [];
        $flush = function () use (&$pending, &$stdParts, &$breakdown, $pdo, &$notes) {
            if (!$pending) return;
            foreach (english_to_bangla($pdo, $pending) as $r) { $breakdown[] = ['src' => $r['src'], 'std' => $r['std'], 'matched' => $r['matched'], 'kind' => 'english']; $stdParts[] = $r['std']; }
            $pending = [];
        };
        while ($i < $n) {
            $hit = null;
            for ($L = min(7, $n - $i); $L >= 2; $L--) {     // longest known phrase starting here ("hello how are you" → hello + how are you)
                $k = strtolower(implode(' ', array_slice($words, $i, $L)));
                if (isset(CORE_PHRASES[$k])) { $hit = [$L, CORE_PHRASES[$k]]; break; }
            }
            if ($hit) {
                $flush();
                $btoks = bn_tokens($hit[1]);
                for ($j = 0; $j < $hit[0]; $j++) $breakdown[] = ['src' => $words[$i + $j], 'std' => $btoks[$j] ?? '', 'matched' => true, 'kind' => 'phrase'];
                $stdParts[] = $hit[1]; $usedPhrase = true; $i += $hit[0];
            } else { $pending[] = $words[$i]; $i++; }
        }
        $flush();
        $stdText = implode(' ', $stdParts);
        if ($usedPhrase) { $memory = $usedPhrase && count($breakdown) === $n && !array_filter($breakdown, fn($b) => $b['kind'] !== 'phrase'); $notes[] = 'Matched a common English phrase.'; }
        if (!$usedPhrase || array_filter($breakdown, fn($b) => $b['kind'] === 'english')) $notes[] = 'English input is translated word-by-word from a dictionary (no sentence grammar).';
    } else {
        $tokens = bn_tokens($banglaText);
        $norm = implode(' ', $tokens);
        $stdText = null;
        if ($srcVar === 'standard') {
            $stdText = $norm;
            foreach ($tokens as $t) $breakdown[] = ['src' => $t, 'std' => $t, 'matched' => false, 'kind' => 'standard'];
        } else {
            $hit = false;
            if (USE_SENTENCE_MEMORY) {
                $st = $pdo->prepare('SELECT standard_text FROM sentences WHERE variety = ? AND text = ? LIMIT 1');
                $st->execute([$srcVar, $norm]);
                $hit = $st->fetchColumn();
            }
            if ($hit !== false) {
                $stdText = $hit; $memory = true;
                $a = $tokens; $b = bn_tokens($hit);
                foreach ($a as $i => $t) $breakdown[] = ['src' => $t, 'std' => (count($a) === count($b) ? $b[$i] : $t), 'matched' => true, 'kind' => 'sentence'];
                $notes[] = 'This exact sentence is in the dialect corpus.';
            } else {
                $post = null;
                if (($src === 'auto' || $src === 'banglish') && $srcVar !== 'standard' && ($det['confidence'] < 0.9 || count($tokens) <= 4)) {
                    $post = [];
                    foreach ($det['scores'] ?? [] as $sc) $post[$sc['variety']] = (float) $sc['p'];
                    $notes[] = 'The dialect was not certain, so words were matched across all dialects.';
                }
                $rows = dialect_to_standard($pdo, $srcVar, $tokens, $post);
                foreach ($rows as $r) $breakdown[] = ['src' => $r['src'], 'std' => $r['std'], 'matched' => $r['matched'], 'kind' => $r['kind'], 'conf' => $r['conf']];
                $stdText = implode(' ', array_column($rows, 'std'));
            }
        }
    }

    // ---- Standard Bangla -> target ----
    $stdTok = bn_tokens($stdText);
    if ($tgt === 'standard') {
        $out = $stdText;
        if ($srcVar === 'standard' && $script !== 'banglish') $notes[] = 'The text already looks like Standard Bangla. Pick a dialect in "To" to see it in that dialect.';
    } elseif ($tgt === 'english') {
        $rows = bangla_to_english($pdo, $stdTok);
        $out = implode(' ', array_column($rows, 'tgt'));
        $php = phrase_bn_en($stdText);
        $ro = $php === null ? bn_en_reorder($pdo, $rows, $stdTok) : null;
        if ($php !== null) { $out = $php; $notes[] = 'Matched a common phrase.'; }
        elseif ($ro !== null) { $out = $ro; $notes[] = 'English word order was adjusted (subject-verb-object); other words are from a dictionary.'; }
        else $notes[] = 'English output is word-by-word from a dictionary.';
        $out = preg_replace('/\\bi\\b/', 'I', $out); $out = mb_strtoupper(mb_substr($out, 0, 1)) . mb_substr($out, 1);
        foreach ($breakdown as $i => &$b) { if (isset($rows[$i])) $b['tgt'] = $rows[$i]['tgt']; } unset($b);
    } else {
        $mem = null;
        if (USE_SENTENCE_MEMORY && ($memory || $srcVar === 'standard')) {
            $st = $pdo->prepare('SELECT text FROM sentences WHERE variety = ? AND standard_text = ? LIMIT 1');
            $st->execute([$tgt, $stdText]);
            $m = $st->fetchColumn();
            if ($m !== false) { $mem = $m; $memory = true; }
        }
        if ($mem !== null) {
            $out = $mem;
        } else {
            $rows = standard_to_dialect($pdo, $tgt, $stdTok);
            $out = implode(' ', array_column($rows, 'tgt'));
            if (count($rows) === count($breakdown)) foreach ($breakdown as $i => &$b) $b['tgt'] = $rows[$i]['tgt']; unset($b);
        }
    }

    $matched = 0;
    foreach ($breakdown as $b) if (!empty($b['matched'])) $matched++;
    $romanTarget = ($tgt === 'english') ? null : bn_romanize($out);

    return [
        'success' => true,
        'original_text' => $text,
        'detected' => [
            'script' => $script,
            'variety' => $srcVar,
            'label' => VARIETY_LABEL[$srcVar] ?? $srcVar,
            'confidence' => $det['confidence'] ?? 0,
            'scores' => $det['scores'] ?? [],
            'was_auto' => ($src === 'auto' || $src === 'banglish'),
        ],
        'bangla_input' => $script === 'banglish' ? $banglaText : null,
        'transliteration' => $script === 'banglish' ? ($det['transliteration'] ?? []) : [],
        'source' => $srcVar,
        'target' => $tgt,
        'translated_text' => $out,
        'romanization' => $romanTarget,
        'breakdown' => $breakdown,
        'matched' => $matched > 0 || $memory,
        'matched_words' => $matched,
        'from_memory' => $memory,
        'notes' => $notes,
    ];
}


// Keeps punctuation and line breaks: translates each clause on its own and puts the original marks back.
function translate_full(PDO $pdo, string $text, string $src = 'auto', string $tgt = 'standard'): array {
    $text = trim($text);
    $parts = preg_split('/((?:[।?!]+|[,;:.](?=\s|$)|\n+)[ \t]*)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $isMark = fn($x) => preg_match('/^(?:[।?!]+|[,;:.]|\n+)[ \t]*$/u', $x) === 1;
    $chunks = array_values(array_filter($parts, fn($x) => !$isMark($x) && preg_match('/[\p{L}\p{N}]/u', $x)));
    // nothing translatable at all (e.g. "?!...")
    if (!$chunks) {
        return ['success' => true, 'original_text' => $text, 'detected' => ['script' => 'other', 'variety' => 'standard', 'label' => 'Standard Bangla', 'confidence' => 0, 'scores' => [], 'was_auto' => $src === 'auto'],
            'bangla_input' => null, 'transliteration' => [], 'source' => 'standard', 'target' => $tgt, 'translated_text' => $text, 'romanization' => null, 'breakdown' => [],
            'matched' => false, 'matched_words' => 0, 'from_memory' => false, 'notes' => ['Nothing to translate — add some words.']];
    }
    if (count($parts) === 1) {
        // single single-letter Bangla "word": do not guess
        if (mb_strlen($text) < 2 && preg_match('/\p{Bengali}/u', $text)) {
            $r = translate_core($pdo, $text, $src, $tgt); $r['translated_text'] = $text; $r['romanization'] = bn_romanize($text);
            $r['notes'][] = 'One letter is too little to translate. Try a whole word.'; return $r;
        }
        return translate_core($pdo, $text, $src, $tgt);
    }
    // decide the source once for the whole text, so every clause is read the same way
    $whole = detect_text($pdo, $text, $src === 'banglish' ? 'banglish' : ($src === 'english' ? 'english' : 'auto'));
    $useSrc = $src;
    if ($src === 'auto') $useSrc = $whole['script'] === 'english' ? 'english' : ($whole['script'] === 'banglish' ? 'banglish' : $whole['variety']);
    $out = ''; $agg = null; $notes = []; $conv = []; $bi = [];
    foreach ($parts as $x) {
        if ($isMark($x) || !preg_match('/[\p{L}\p{N}]/u', $x)) { $out .= $x; continue; }
        $one = (mb_strlen(trim($x)) < 2 && preg_match('/\p{Bengali}/u', $x));
        $r = translate_core($pdo, trim($x), $useSrc, $tgt);
        $tt = $one ? trim($x) : $r['translated_text'];
        $out .= $tt;
        if ($agg === null) $agg = $r; else {
            $agg['breakdown'] = array_merge($agg['breakdown'], $r['breakdown']);
            $agg['matched_words'] += $r['matched_words']; $agg['matched'] = $agg['matched'] || $r['matched'];
            $agg['from_memory'] = $agg['from_memory'] && $r['from_memory'];
            $agg['transliteration'] = array_merge($agg['transliteration'], $r['transliteration']);
            if ($r['bangla_input'] !== null) $bi[] = $r['bangla_input'];
        }
        if ($agg === $r) { if ($r['bangla_input'] !== null) $bi[] = $r['bangla_input']; }
        foreach ($r['notes'] as $n) $notes[$n] = true;
    }
    if ($tgt !== 'english' && in_array($whole['script'], ['banglish', 'english'], true)) $out = preg_replace('/\.(?=\s|$)/u', '।', $out);   // Roman full stop → Bangla দাঁড়ি
    $agg['original_text'] = $text; $agg['translated_text'] = trim($out);
    $agg['bangla_input'] = $bi ? implode(' ', $bi) : null;
    $agg['notes'] = array_keys($notes);
    $agg['romanization'] = $tgt === 'english' ? null : bn_romanize($agg['translated_text']);
    $agg['detected']['was_auto'] = ($src === 'auto' || $src === 'banglish');
    return $agg;
}


function expand_contractions(string $t): string {
    static $m = ["can't" => 'can not', "cannot" => 'can not', "won't" => 'will not', "don't" => 'do not', "doesn't" => 'does not', "didn't" => 'did not', "isn't" => 'is not', "aren't" => 'are not', "wasn't" => 'was not', "weren't" => 'were not',
        "i'm" => 'i am', "you're" => 'you are', "we're" => 'we are', "they're" => 'they are', "he's" => 'he is', "she's" => 'she is', "it's" => 'it is', "that's" => 'that is', "what's" => 'what is', "where's" => 'where is', "who's" => 'who is', "there's" => 'there is', "let's" => 'let us',
        "i'll" => 'i will', "you'll" => 'you will', "we'll" => 'we will', "i've" => 'i have', "you've" => 'you have', "we've" => 'we have', "i'd" => 'i would', "haven't" => 'have not', "hasn't" => 'has not', "couldn't" => 'could not', "wouldn't" => 'would not', "shouldn't" => 'should not'];
    return preg_replace_callback("/[A-Za-z']+/", fn($x) => $m[strtolower(str_replace('’', "'", $x[0]))] ?? $x[0], str_replace('’', "'", $t));
}
