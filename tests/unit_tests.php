<?php
// Vashasetu unit tests: the small functions the translator is built from, tested on their own (no web server needed).
// Run:  php tests/unit_tests.php        (needs the database loaded once with setup.php, for the dictionary-based cases)
// Writes tests/results/unit_results.json.
define('KEEP_SESSION_OPEN', true);
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/matching_engine.php';
require_once __DIR__ . '/../api/word_evidence.php';
ob_end_clean();
$results = [];
function t(string $id, string $module, string $title, string $input, string $expected, $actual, bool $ok): void {
    global $results;
    $results[] = ['id' => $id, 'module' => $module, 'title' => $title, 'input' => $input, 'expected' => $expected, 'actual' => is_string($actual) ? $actual : json_encode($actual, JSON_UNESCAPED_UNICODE), 'result' => $ok ? 'Pass' : 'Fail'];
    echo ($ok ? 'PASS ' : 'FAIL ') . "$id $title" . ($ok ? '' : '   -> got: ' . (is_string($actual) ? $actual : json_encode($actual, JSON_UNESCAPED_UNICODE))) . "\n";
}
$n = fn($s) => normalizer_normalize((string) $s, Normalizer::FORM_C);
$eq = fn($a, $b) => $n($a) === $n($b);

// ---- Bangla text helpers
t('UT-01', 'Text helpers', 'bn_normalize joins the two ways of writing য়', 'য + ় vs য়', 'same text', bn_normalize("য\u{09BC}") === bn_normalize("\u{09DF}") ? 'same' : 'different', bn_normalize("য\u{09BC}") === bn_normalize("\u{09DF}"));
t('UT-02', 'Text helpers', 'bn_tokens splits Bangla words and drops punctuation', 'আমি ভাত খাবো। তুমি?', '["আমি","ভাত","খাবো","তুমি"]', bn_tokens('আমি ভাত খাবো। তুমি?'), count(bn_tokens('আমি ভাত খাবো। তুমি?')) === 4);
t('UT-03', 'Text helpers', 'bn_romanize writes Bangla in Roman letters', 'ভাত', 'bhat', bn_romanize('ভাত'), in_array(bn_romanize('ভাত'), ['bhat', 'bhaat', 'vat'], true));
$sp = vs_split_sentences("আমি ভাত খাবো। তুমি কোথায় যাবে? ভালো!");
t('UT-04', 'Text helpers', 'vs_split_sentences keeps each sentence with its own end mark', 'three sentences', '3 parts ending । ? !', $sp, count($sp) === 3 && str_contains($sp[0], '।') && str_contains($sp[1], '?'));
t('UT-05', 'Text helpers', 'vs_punctuate puts commas back by position', 'আমি, তুমি ও সে + আঁই তুঁই আর হিতে', 'আঁই, তুঁই আর হিতে', vs_punctuate('আমি, তুমি ও সে', 'আঁই তুঁই আর হিতে'), $eq(vs_punctuate('আমি, তুমি ও সে', 'আঁই তুঁই আর হিতে'), 'আঁই, তুঁই আর হিতে'));
t('UT-06', 'Text helpers', 'vs_punctuate keeps a hyphenated word together', 'ইন্দো-আর্য ভাষা', 'ইন্দো-আর্য ভাষা', vs_punctuate('ইন্দো-আর্য ভাষা', 'ইন্দো আর্য ভাষা'), $eq(vs_punctuate('ইন্দো-আর্য ভাষা', 'ইন্দো আর্য ভাষা'), 'ইন্দো-আর্য ভাষা'));
t('UT-07', 'Text helpers', 'vs_punctuate does not double the full stop', 'আমি যাবো। + আঁই যাইয়ুম।', 'one ।', vs_punctuate('আমি যাবো।', 'আঁই যাইয়ুম।'), substr_count(vs_punctuate('আমি যাবো।', 'আঁই যাইয়ুম।'), '।') === 1);
// ---- validation helpers
t('UT-08', 'Validation', 'valid_dialect accepts the 11 dialects and nothing else', 'Chattogram, Tangail, Dhaka, chattogram', 'true, true, false, false', [valid_dialect('Chattogram'), valid_dialect('Tangail'), valid_dialect('Dhaka'), valid_dialect('chattogram')], valid_dialect('Chattogram') && valid_dialect('Tangail') && !valid_dialect('Dhaka') && !valid_dialect('chattogram'));
t('UT-09', 'Validation', 'vs_password_problem rejects short, common and email passwords', '1234567, password, a@b.co as password, Correct#Horse9', '3 problems, 1 ok',
  [vs_password_problem('1234567'), vs_password_problem('password'), vs_password_problem('a@b.co12', 'a@b.co12'), vs_password_problem('Correct#Horse9')],
  vs_password_problem('1234567') && vs_password_problem('password') && vs_password_problem('a@b.co12', 'a@b.co12') && vs_password_problem('Correct#Horse9') === null);
// ---- Noakhali rule
$rows = [['src' => 'আইঁ', 'std' => 'আমি', 'tgt' => 'আইঁ'], ['src' => 'খাইতান', 'std' => 'খাইতান', 'tgt' => ''], ['src' => 'ন', 'std' => 'ন', 'tgt' => '']];
$r = vs_noakhali_negative_future($rows, true);
t('UT-10', 'Dialect rules', 'Noakhali "খাইতান ন" is read as Standard "খাবো না"', 'আইঁ খাইতান ন', 'আমি খাবো না', implode(' ', array_column($r, 'std')), $eq(implode(' ', array_column($r, 'std')), 'আমি খাবো না'));
$rows = [['std' => 'আমি', 'tgt' => 'আইঁ'], ['std' => 'করবো', 'tgt' => 'কইরমু'], ['std' => 'না', 'tgt' => 'না']];
$r = vs_noakhali_negative_future($rows, false);
t('UT-11', 'Dialect rules', 'Standard "করবো না" becomes Noakhali "কইরতান ন"', 'আমি করবো না', 'আইঁ কইরতান ন', implode(' ', array_column($r, 'tgt')), $eq(implode(' ', array_column($r, 'tgt')), 'আইঁ কইরতান ন'));
// ---- English grammar
$V = en_bn_verbs();
$conj = [['eat', 'fut', 0, 'খাবো'], ['eat', 'past', 2, 'খেলো'], ['go', 'perf', 1, 'গিয়েছো'], ['go', 'past', 0, 'গেলাম'], ['write', 'pres', 2, 'লেখে'], ['write', 'pres', 0, 'লিখি'],
         ['come', 'past', 0, 'এলাম'], ['give', 'fut', 0, 'দেবো'], ['do', 'cont', 2, 'করছে'], ['work', 'fut', 1, 'কাজ করবে'], ['eat', 'neg_past', 0, 'খাইনি'], ['sleep', 'perf', 0, 'ঘুমিয়েছি']];
$bad = [];
foreach ($conj as [$v, $tense, $p, $want]) { $got = en_bn_conj($V[$v], $tense, $p); if (!$eq($got, $want)) $bad[] = "$v/$tense/$p=$got"; }
t('UT-12', 'English grammar', 'Bangla verb endings for 12 verb / tense / person combinations', 'eat fut I, eat past he, go perf you, …', 'খাবো, খেলো, গিয়েছো, গেলাম, লেখে, লিখি, এলাম, দেবো, করছে, কাজ করবে, খাইনি, ঘুমিয়েছি', $bad ?: 'all correct', !$bad);
$lem = ['ate' => ['eat', 'past'], 'eating' => ['eat', 'ing'], 'goes' => ['go', 'base'], 'studied' => ['study', 'past'], 'written' => ['write', 'pp'], 'stopped' => null, 'running' => ['run', 'ing'], 'cooked' => ['cook', 'past']];
$bad = [];
foreach ($lem as $w => $want) { $g = en_bn_lemma($w); if ($want === null ? $g !== null : ($g === null || $g[0] !== $want[0])) $bad[] = "$w=" . json_encode($g); }
t('UT-13', 'English grammar', 'en_bn_lemma finds the base verb of English forms', 'ate, eating, goes, studied, written, running, cooked', 'eat, eat, go, study, write, run, cook', $bad ?: 'all correct', !$bad);
t('UT-14', 'English grammar', 'Place ending: -এ, -তে, -য়', 'বাজার, বাড়ি, ঢাকা, Dhaka', 'বাজারে, বাড়িতে, ঢাকায়, Dhaka-এ', [en_bn_loc('বাজার'), en_bn_loc('বাড়ি'), en_bn_loc('ঢাকা'), en_bn_loc('Dhaka')],
  $eq(en_bn_loc('বাজার'), 'বাজারে') && $eq(en_bn_loc('বাড়ি'), 'বাড়িতে') && $eq(en_bn_loc('ঢাকা'), 'ঢাকায়') && en_bn_loc('Dhaka') === 'Dhaka-এ');
t('UT-15', 'English grammar', 'Possessive ending: -র, -ের, মায়ের', 'বাবা, ভাই, রহিম, মা', 'বাবার, ভাইয়ের? (ভাইর), রহিমের, মায়ের', [en_bn_gen('বাবা'), en_bn_gen('রহিম'), en_bn_gen('মা')], $eq(en_bn_gen('বাবা'), 'বাবার') && $eq(en_bn_gen('রহিম'), 'রহিমের') && $eq(en_bn_gen('মা'), 'মায়ের'));
$pdo = get_db();
$g = en_bn_grammar($pdo, ['I', 'will', 'eat', 'rice']);
t('UT-16', 'English grammar', 'en_bn_grammar builds a whole sentence', 'I will eat rice', 'আমি ভাত খাবো', $g['text'] ?? null, $g && $eq($g['text'], 'আমি ভাত খাবো'));
$g = en_bn_grammar($pdo, ['Colourless', 'green', 'ideas', 'sleep', 'furiously', 'zzq']);
t('UT-17', 'English grammar', 'Sentences the rules cannot read return null (word-by-word is used instead)', 'Colourless green ideas sleep furiously zzq', 'null or a sentence, never an error', $g === null ? 'null' : $g['text'], true);
// ---- security helpers
$_SERVER['REMOTE_ADDR'] = '192.168.1.20'; $_SERVER['HTTP_CF_CONNECTING_IP'] = '9.9.9.9';
$a = vs_client_ip();
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$b = vs_client_ip();
t('UT-18', 'Security helpers', 'vs_client_ip trusts CF-Connecting-IP only from the tunnel on this computer', 'from 192.168.1.20 and from 127.0.0.1, header 9.9.9.9', '192.168.1.20, then 9.9.9.9', [$a, $b], $a === '192.168.1.20' && $b === '9.9.9.9');
unset($_SERVER['HTTP_CF_CONNECTING_IP']);
t('UT-19', 'Security helpers', 'vs_is_local_request: command line counts as local', 'php CLI', 'true', vs_is_local_request() ? 'true' : 'false', vs_is_local_request());

@mkdir(__DIR__ . '/results', 0775, true);
file_put_contents(__DIR__ . '/results/unit_results.json', json_encode(['time' => date('Y-m-d H:i'), 'results' => $results], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$p = count(array_filter($results, fn($r) => $r['result'] === 'Pass'));
echo "$p/" . count($results) . " unit tests passed\n";
exit($p === count($results) ? 0 : 1);
