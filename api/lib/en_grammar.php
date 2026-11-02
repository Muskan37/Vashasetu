<?php
/**
 * English -> Standard Bangla with sentence grammar (rule based).
 *
 * English is subject–verb–object ("I will eat rice"); Bangla is subject–object–verb with the tense and the
 * person in the verb ending ("আমি ভাত খাবো"). en_bn_grammar() reads one English clause, finds the subject,
 * the tense (will, am …ing, did, have …ed, can, want to …), the main verb, negation and question form,
 * then builds the Bangla sentence in Bangla order with the right verb form.
 * It returns null when it cannot read the clause safely; the caller then falls back to word-by-word.
 */

/** Bangla verb table. Keys are English base forms.
 *  'c' = consonant stem (কর → করি, করো, করে), 'v' = vowel stem (খা → খাই, খাও, খায়).
 *  s1 = stem for I/we and all tenses, s23 = stem for you/he simple present (লিখি but লেখো, লেখে),
 *  ps = simple-past stem (খে → খেলাম), pf = perfect stem (খেয়ে → খেয়েছি), inf = "to …" form (খেতে),
 *  pre = a noun in front of the verb (কাজ করা = work, ফোন করা = call), obj = how the object is marked:
 *  'with' (তার সাথে দেখা করবো), 'for' (আমার জন্য অপেক্ষা করো), default = plain / -কে for people.
 *  Irregular verbs list their forms in 'f' (tense => [I, you, he]). */
function en_bn_verbs(): array {
    static $V = null;
    if ($V !== null) return $V;
    $c = fn($s1, $pf, $more = []) => array_merge(['t' => 'c', 's1' => $s1, 's23' => $s1, 'ps' => $s1, 'pf' => $pf], $more);
    $v = fn($s1, $ps, $pf, $inf, $more = []) => array_merge(['t' => 'v', 's1' => $s1, 's23' => $s1, 'ps' => $ps, 'pf' => $pf, 'inf' => $inf], $more);
    $kor = fn($pre, $more = []) => array_merge(['t' => 'c', 's1' => 'কর', 's23' => 'কর', 'ps' => 'কর', 'pf' => 'করে', 'pre' => $pre, 'nimp' => 'কোরো'], $more);
    $dei = ['pres' => ['দিই', 'দাও', 'দেয়'], 'cont' => ['দিচ্ছি', 'দিচ্ছো', 'দিচ্ছে'], 'fut' => ['দেবো', 'দেবে', 'দেবে'], 'past' => ['দিলাম', 'দিলে', 'দিলো'],
            'perf' => ['দিয়েছি', 'দিয়েছো', 'দিয়েছে'], 'pcont' => ['দিচ্ছিলাম', 'দিচ্ছিলে', 'দিচ্ছিল'], 'pperf' => ['দিয়েছিলাম', 'দিয়েছিলে', 'দিয়েছিল'],
            'neg_past' => ['দিইনি', 'দাওনি', 'দেয়নি'], 'inf' => 'দিতে', 'imp' => 'দাও', 'nimp' => 'দিও'];
    $V = [
        'eat' => $v('খা', 'খে', 'খেয়ে', 'খেতে', ['nimp' => 'খেও']), 'drink' => $v('খা', 'খে', 'খেয়ে', 'খেতে', ['nimp' => 'খেও']),
        'go' => $v('যা', 'গে', 'গিয়ে', 'যেতে', ['nimp' => 'যেও', 'motion' => 1]),
        'come' => $c('আস', 'এসে', ['ps' => 'এ', 'imp' => 'এসো', 'nimp' => 'এসো', 'motion' => 1]),
        'do' => $c('কর', 'করে', ['nimp' => 'কোরো']), 'see' => $c('দেখ', 'দেখে'), 'watch' => $c('দেখ', 'দেখে'), 'look' => $c('দেখ', 'দেখে'),
        'read' => $c('পড়', 'পড়ে'), 'write' => $c('লিখ', 'লিখে', ['s23' => 'লেখ']), 'buy' => $c('কিন', 'কিনে', ['s23' => 'কেন']),
        'know' => $c('জান', 'জেনে'), 'say' => $c('বল', 'বলে'), 'tell' => $c('বল', 'বলে'), 'speak' => $c('বল', 'বলে'), 'talk' => $kor('কথা', ['obj' => 'with']),
        'play' => $c('খেল', 'খেলে'), 'hear' => $c('শুন', 'শুনে', ['s23' => 'শোন']), 'listen' => $c('শুন', 'শুনে', ['s23' => 'শোন']),
        'walk' => $c('হাঁট', 'হেঁটে'), 'run' => $v('দৌড়া', 'দৌড়া', 'দৌড়ে', 'দৌড়াতে'), 'sleep' => $v('ঘুমা', 'ঘুমা', 'ঘুমিয়ে', 'ঘুমাতে'),
        'send' => $v('পাঠা', 'পাঠা', 'পাঠিয়ে', 'পাঠাতে'), 'love' => $c('ভালোবাস', 'ভালোবেসে'), 'like' => $kor('পছন্দ'),
        'want' => ['f' => ['pres' => ['চাই', 'চাও', 'চায়'], 'cont' => ['চাইছি', 'চাইছো', 'চাইছে'], 'fut' => ['চাইবো', 'চাইবে', 'চাইবে'], 'past' => ['চাইলাম', 'চাইলে', 'চাইলো'],
                   'perf' => ['চেয়েছি', 'চেয়েছো', 'চেয়েছে'], 'pcont' => ['চাইছিলাম', 'চাইছিলে', 'চাইছিল'], 'pperf' => ['চেয়েছিলাম', 'চেয়েছিলে', 'চেয়েছিল'],
                   'neg_past' => ['চাইনি', 'চাওনি', 'চায়নি'], 'inf' => 'চাইতে', 'imp' => 'চাও', 'nimp' => 'চেও']],
        'give' => ['f' => $dei],
        'take' => ['f' => ['pres' => ['নিই', 'নাও', 'নেয়'], 'cont' => ['নিচ্ছি', 'নিচ্ছো', 'নিচ্ছে'], 'fut' => ['নেবো', 'নেবে', 'নেবে'], 'past' => ['নিলাম', 'নিলে', 'নিলো'],
                   'perf' => ['নিয়েছি', 'নিয়েছো', 'নিয়েছে'], 'pcont' => ['নিচ্ছিলাম', 'নিচ্ছিলে', 'নিচ্ছিল'], 'pperf' => ['নিয়েছিলাম', 'নিয়েছিলে', 'নিয়েছিল'],
                   'neg_past' => ['নিইনি', 'নাওনি', 'নেয়নি'], 'inf' => 'নিতে', 'imp' => 'নাও', 'nimp' => 'নিও']],
        'get' => $v('পা', 'পে', 'পেয়ে', 'পেতে'), 'receive' => $v('পা', 'পে', 'পেয়ে', 'পেতে'),
        'live' => $c('থাক', 'থেকে'), 'stay' => $c('থাক', 'থেকে'), 'learn' => $c('শিখ', 'শিখে', ['s23' => 'শেখ']),
        'teach' => $v('শেখা', 'শেখা', 'শিখিয়ে', 'শেখাতে'), 'cook' => $kor('রান্না'), 'work' => $kor('কাজ'), 'help' => $kor('সাহায্য'),
        'call' => $kor('ফোন'), 'wait' => $kor('অপেক্ষা', ['obj' => 'for']), 'open' => $c('খুল', 'খুলে', ['s23' => 'খোল']), 'close' => $kor('বন্ধ'),
        'sit' => $c('বস', 'বসে'), 'stand' => $v('দাঁড়া', 'দাঁড়া', 'দাঁড়িয়ে', 'দাঁড়াতে'), 'cry' => $c('কাঁদ', 'কেঁদে'), 'laugh' => $c('হাস', 'হেসে'),
        'smile' => $c('হাস', 'হেসে'), 'think' => $c('ভাব', 'ভেবে'), 'understand' => $c('বুঝ', 'বুঝে', ['s23' => 'বোঝ']), 'bring' => $c('আন', 'এনে'),
        'meet' => $kor('দেখা', ['obj' => 'with']), 'return' => $c('ফির', 'ফিরে', ['s23' => 'ফের', 'motion' => 1]), 'start' => $kor('শুরু'), 'begin' => $kor('শুরু'),
        'finish' => $kor('শেষ'), 'clean' => $kor('পরিষ্কার'), 'swim' => $c('কাট', 'কেটে', ['pre' => 'সাঁতার']), 'dance' => $c('নাচ', 'নেচে'),
        'travel' => $kor('ভ্রমণ'), 'marry' => $kor('বিয়ে'), 'use' => $kor('ব্যবহার'), 'forget' => $v('যা', 'গে', 'গিয়ে', 'যেতে', ['pre' => 'ভুলে', 'nimp' => 'যেও']),
        'remember' => $c('রাখ', 'রেখে', ['pre' => 'মনে']), 'keep' => $c('রাখ', 'রেখে'), 'ask' => $kor('জিজ্ঞেস'), 'answer' => ['f' => $dei, 'pre' => 'উত্তর'],
        'show' => $v('দেখা', 'দেখা', 'দেখিয়ে', 'দেখাতে'), 'try' => $kor('চেষ্টা'), 'break' => $c('ভাঙ', 'ভেঙে'), 'win' => $c('জিত', 'জিতে', ['s23' => 'জেত']),
        'lose' => $v('হারা', 'হারা', 'হারিয়ে', 'হারাতে'), 'reach' => $v('পৌঁছা', 'পৌঁছা', 'পৌঁছে', 'পৌঁছাতে', ['motion' => 1]), 'arrive' => $v('পৌঁছা', 'পৌঁছা', 'পৌঁছে', 'পৌঁছাতে', ['motion' => 1]),
        'cut' => $c('কাট', 'কেটে'), 'believe' => $kor('বিশ্বাস'), 'feel' => $kor('অনুভব'), 'pray' => $kor('প্রার্থনা'), 'study' => $kor('পড়াশোনা'),
        'visit' => $c('বেড়া', 'বেড়িয়ে', ['t' => 'v', 'inf' => 'বেড়াতে']), 'sell' => $kor('বিক্রি'), 'pay' => ['f' => $dei, 'pre' => 'টাকা'],
        'leave' => $v('যা', 'গে', 'গিয়ে', 'যেতে', ['pre' => 'চলে', 'nimp' => 'যেও', 'motion' => 1]), 'fly' => $c('উড়', 'উড়ে'), 'climb' => $c('উঠ', 'উঠে', ['s23' => 'ওঠ']),
        'wake' => $c('উঠ', 'উঠে', ['s23' => 'ওঠ', 'pre' => 'ঘুম থেকে']), 'die' => $c('মর', 'মরে'), 'catch' => $c('ধর', 'ধরে'), 'hold' => $c('ধর', 'ধরে'),
        'enter' => $kor('প্রবেশ'), 'wear' => $c('পর', 'পরে'), 'sing' => ['f' => ['pres' => ['গাই', 'গাও', 'গায়'], 'cont' => ['গাইছি', 'গাইছো', 'গাইছে'], 'fut' => ['গাইবো', 'গাইবে', 'গাইবে'],
                   'past' => ['গাইলাম', 'গাইলে', 'গাইলো'], 'perf' => ['গেয়েছি', 'গেয়েছো', 'গেয়েছে'], 'pcont' => ['গাইছিলাম', 'গাইছিলে', 'গাইছিল'], 'pperf' => ['গেয়েছিলাম', 'গেয়েছিলে', 'গেয়েছিল'],
                   'neg_past' => ['গাইনি', 'গাওনি', 'গায়নি'], 'inf' => 'গাইতে', 'imp' => 'গাও', 'nimp' => 'গেও'], 'pre' => 'গান'],
    ];
    return $V;
}

/** Irregular English verb forms -> [base, form]. form: past | pp | ing | s */
function en_bn_irregular(): array {
    return ['ate' => ['eat', 'past'], 'eaten' => ['eat', 'pp'], 'drank' => ['drink', 'past'], 'drunk' => ['drink', 'pp'], 'went' => ['go', 'past'], 'gone' => ['go', 'pp'],
        'came' => ['come', 'past'], 'did' => ['do', 'past'], 'done' => ['do', 'pp'], 'saw' => ['see', 'past'], 'seen' => ['see', 'pp'], 'wrote' => ['write', 'past'],
        'written' => ['write', 'pp'], 'bought' => ['buy', 'past'], 'knew' => ['know', 'past'], 'known' => ['know', 'pp'], 'said' => ['say', 'past'], 'told' => ['tell', 'past'],
        'spoke' => ['speak', 'past'], 'spoken' => ['speak', 'pp'], 'heard' => ['hear', 'past'], 'ran' => ['run', 'past'], 'slept' => ['sleep', 'past'], 'sent' => ['send', 'past'],
        'gave' => ['give', 'past'], 'given' => ['give', 'pp'], 'took' => ['take', 'past'], 'taken' => ['take', 'pp'], 'got' => ['get', 'past'], 'gotten' => ['get', 'pp'],
        'learnt' => ['learn', 'past'], 'taught' => ['teach', 'past'], 'sat' => ['sit', 'past'], 'stood' => ['stand', 'past'], 'thought' => ['think', 'past'],
        'understood' => ['understand', 'past'], 'brought' => ['bring', 'past'], 'met' => ['meet', 'past'], 'began' => ['begin', 'past'], 'begun' => ['begin', 'pp'],
        'swam' => ['swim', 'past'], 'swum' => ['swim', 'pp'], 'forgot' => ['forget', 'past'], 'forgotten' => ['forget', 'pp'], 'kept' => ['keep', 'past'],
        'shown' => ['show', 'pp'], 'broke' => ['break', 'past'], 'broken' => ['break', 'pp'], 'won' => ['win', 'past'], 'lost' => ['lose', 'past'], 'left' => ['leave', 'past'],
        'flew' => ['fly', 'past'], 'flown' => ['fly', 'pp'], 'woke' => ['wake', 'past'], 'woken' => ['wake', 'pp'], 'caught' => ['catch', 'past'], 'held' => ['hold', 'past'],
        'wore' => ['wear', 'past'], 'worn' => ['wear', 'pp'], 'sang' => ['sing', 'past'], 'sung' => ['sing', 'pp'], 'felt' => ['feel', 'past'], 'paid' => ['pay', 'past'],
        'sold' => ['sell', 'past'], 'read' => ['read', 'base'], 'cut' => ['cut', 'base'], 'dying' => ['die', 'ing']];
}

/** English verb word -> [base, form] or null. */
function en_bn_lemma(string $w): ?array {
    $V = en_bn_verbs(); $I = en_bn_irregular();
    if (isset($I[$w])) return isset($V[$I[$w][0]]) ? $I[$w] : null;   // a form whose verb has no Bangla forms yet (lying) is not a verb here
    if (isset($V[$w])) return [$w, 'base'];
    $try = function (array $cands, string $form) use ($V) { foreach ($cands as $c) if ($c !== '' && isset($V[$c])) return [$c, $form]; return null; };
    if (str_ends_with($w, 'ing')) { $b = substr($w, 0, -3); return $try([$b, $b . 'e', substr($b, 0, -1), preg_replace('/y$/', 'ie', $b)], 'ing'); }
    if (str_ends_with($w, 'ied')) return $try([substr($w, 0, -3) . 'y', substr($w, 0, -1)], 'past');   // studied -> study, died -> die
    if (str_ends_with($w, 'ed')) { $b = substr($w, 0, -2); return $try([$b, $b . 'e', substr($b, 0, -1), substr($w, 0, -1)], 'past'); }
    if (str_ends_with($w, 'ies')) return $try([substr($w, 0, -3) . 'y', substr($w, 0, -1)], 's');     // studies -> study, dies -> die
    if (str_ends_with($w, 'es')) { $r = $try([substr($w, 0, -2)], 's'); if ($r) return $r; }
    if (str_ends_with($w, 's')) return $try([substr($w, 0, -1)], 's');
    return null;
}

/** Conjugated Bangla verb. tense: pres|cont|fut|past|perf|pcont|pperf|neg_past ; p: 0 = I/we, 1 = you, 2 = he/she/they */
function en_bn_conj(array $v, string $tense, int $p): string {
    if (isset($v['f'])) $out = $v['f'][$tense][$p] ?? $v['f']['pres'][$p];
    else {
        $s1 = $v['s1']; $s23 = $v['s23'];
        if ($v['t'] === 'v') {
            $forms = ['pres' => [$s1 . 'ই', $s23 . 'ও', $s23 . 'য়'], 'cont' => [$s1 . 'চ্ছি', $s1 . 'চ্ছো', $s1 . 'চ্ছে'], 'pcont' => [$s1 . 'চ্ছিলাম', $s1 . 'চ্ছিলে', $s1 . 'চ্ছিল'],
                      'neg_past' => [$s1 . 'ইনি', $s23 . 'ওনি', $s23 . 'য়নি']];
        } else {
            $forms = ['pres' => [$s1 . 'ি', $s23 . 'ো', $s23 . 'ে'], 'cont' => [$s1 . 'ছি', $s1 . 'ছো', $s1 . 'ছে'], 'pcont' => [$s1 . 'ছিলাম', $s1 . 'ছিলে', $s1 . 'ছিল'],
                      'neg_past' => [$s1 . 'িনি', $s23 . 'োনি', $s23 . 'েনি']];
        }
        $forms['fut'] = [$s1 . 'বো', $s1 . 'বে', $s1 . 'বে'];
        $forms['past'] = [$v['ps'] . 'লাম', $v['ps'] . 'লে', $v['ps'] . 'লো'];
        $forms['perf'] = [$v['pf'] . 'ছি', $v['pf'] . 'ছো', $v['pf'] . 'ছে'];
        $forms['pperf'] = [$v['pf'] . 'ছিলাম', $v['pf'] . 'ছিলে', $v['pf'] . 'ছিল'];
        $out = $forms[$tense][$p];
    }
    return (isset($v['pre']) ? $v['pre'] . ' ' : '') . $out;
}
function en_bn_inf(array $v): string {
    $f = $v['f']['inf'] ?? ($v['inf'] ?? $v['s1'] . 'তে');
    return (isset($v['pre']) ? $v['pre'] . ' ' : '') . $f;
}
/** Verbal noun: করা, লেখা, খাওয়া, ঘুমানো, দেওয়া. */
function en_bn_vn(array $v): string {
    if (isset($v['f'])) { $m = ['দিতে' => 'দেওয়া', 'নিতে' => 'নেওয়া', 'চাইতে' => 'চাওয়া', 'গাইতে' => 'গাওয়া']; $f = $m[$v['f']['inf']] ?? $v['f']['inf']; }
    elseif ($v['t'] === 'v') $f = mb_strlen($v['s1']) > 2 ? $v['s1'] . 'নো' : $v['s1'] . 'ওয়া';
    else $f = $v['s23'] . 'া';
    return (isset($v['pre']) ? $v['pre'] . ' ' : '') . $f;
}
function en_bn_imp(array $v, bool $neg): string {
    $f = $neg ? ($v['f']['nimp'] ?? $v['nimp'] ?? null) : ($v['f']['imp'] ?? $v['imp'] ?? null);
    if ($f === null) $f = en_bn_conj(array_diff_key($v, ['pre' => 1]), 'pres', 1);
    return (isset($v['pre']) ? $v['pre'] . ' ' : '') . $f . ($neg ? ' না' : '');
}

const EN_BN_SUBJ = ['i' => ['আমি', 0, 'আমার', 'আমাকে'], 'we' => ['আমরা', 0, 'আমাদের', 'আমাদেরকে'], 'you' => ['তুমি', 1, 'তোমার', 'তোমাকে'],
    'he' => ['সে', 2, 'তার', 'তাকে'], 'she' => ['সে', 2, 'তার', 'তাকে'], 'they' => ['তারা', 2, 'তাদের', 'তাদেরকে'], 'it' => ['এটা', 2, 'এর', 'এটা'],
    'this' => ['এটা', 2, 'এর', 'এটা'], 'that' => ['ওটা', 2, 'ওটার', 'ওটা'], 'who' => ['কে', 2, 'কার', 'কাকে'], 'what' => ['কী', 2, 'কিসের', 'কী']];
const EN_BN_OBJ = ['me' => ['আমাকে', 'আমার'], 'us' => ['আমাদেরকে', 'আমাদের'], 'you' => ['তোমাকে', 'তোমার'], 'him' => ['তাকে', 'তার'], 'her' => ['তাকে', 'তার'],
    'them' => ['তাদেরকে', 'তাদের'], 'it' => ['এটা', 'এর'], 'this' => ['এটা', 'এর'], 'that' => ['ওটা', 'ওটার']];
const EN_BN_POSS = ['my' => 'আমার', 'our' => 'আমাদের', 'your' => 'তোমার', 'his' => 'তার', 'her' => 'তার', 'their' => 'তাদের', 'its' => 'এর'];
const EN_BN_TIME = ['today' => 'আজ', 'tomorrow' => 'আগামীকাল', 'yesterday' => 'গতকাল', 'now' => 'এখন', 'tonight' => 'আজ রাতে', 'later' => 'পরে', 'soon' => 'শীঘ্রই',
    'every day' => 'প্রতিদিন', 'everyday' => 'প্রতিদিন', 'every morning' => 'প্রতিদিন সকালে', 'every night' => 'প্রতি রাতে', 'this morning' => 'আজ সকালে', 'this evening' => 'আজ সন্ধ্যায়',
    'in the morning' => 'সকালে', 'in the evening' => 'সন্ধ্যায়', 'in the afternoon' => 'বিকেলে', 'at night' => 'রাতে', 'last night' => 'গত রাতে', 'next week' => 'আগামী সপ্তাহে',
    'last week' => 'গত সপ্তাহে', 'next month' => 'আগামী মাসে', 'last month' => 'গত মাসে', 'next year' => 'আগামী বছর', 'last year' => 'গত বছর', 'always' => 'সবসময়',
    'often' => 'প্রায়ই', 'sometimes' => 'মাঝে মাঝে', 'usually' => 'সাধারণত', 'again' => 'আবার', 'already' => 'ইতিমধ্যে'];
const EN_BN_ADV = ['very' => 'খুব', 'well' => 'ভালো', 'fast' => 'তাড়াতাড়ি', 'quickly' => 'তাড়াতাড়ি', 'slowly' => 'ধীরে ধীরে', 'here' => 'এখানে', 'there' => 'সেখানে',
    'hard' => 'মন দিয়ে', 'carefully' => 'সাবধানে', 'loudly' => 'জোরে', 'too' => 'ও', 'also' => 'ও', 'together' => 'একসাথে', 'alone' => 'একা', 'much' => 'অনেক', 'a lot' => 'অনেক', 'well done' => 'ভালো'];
const EN_BN_NUM = ['one' => 'একটা', 'two' => 'দুইটা', 'three' => 'তিনটা', 'four' => 'চারটা', 'five' => 'পাঁচটা', 'six' => 'ছয়টা', 'seven' => 'সাতটা', 'eight' => 'আটটা', 'nine' => 'নয়টা', 'ten' => 'দশটা'];
const EN_BN_WH = ['what' => 'কী', 'where' => 'কোথায়', 'when' => 'কখন', 'why' => 'কেন', 'how' => 'কীভাবে', 'who' => 'কে', 'which' => 'কোনটা', 'whom' => 'কাকে'];
const EN_BN_AUX = ['do', 'does', 'did', 'will', 'shall', 'can', 'could', 'am', 'is', 'are', 'was', 'were', 'have', 'has', 'had', 'would', 'should', 'must'];
const EN_BN_PLACE_FIX = ['home' => ['বাড়ি', 'বাড়িতে', 'বাড়ি থেকে']];   // [after go/come, in/at, from]
const EN_BN_WORD_FIX = ['rice' => 'ভাত', 'water' => 'পানি', 'house' => 'বাড়ি', 'school' => 'স্কুল', 'market' => 'বাজার', 'office' => 'অফিস', 'book' => 'বই', 'mother' => 'মা',
    'father' => 'বাবা', 'brother' => 'ভাই', 'sister' => 'বোন', 'friend' => 'বন্ধু', 'teacher' => 'শিক্ষক', 'student' => 'ছাত্র', 'doctor' => 'ডাক্তার', 'boy' => 'ছেলে',
    'girl' => 'মেয়ে', 'man' => 'লোক', 'woman' => 'মহিলা', 'child' => 'শিশু', 'people' => 'মানুষ', 'fish' => 'মাছ', 'egg' => 'ডিম', 'milk' => 'দুধ', 'tea' => 'চা',
    'food' => 'খাবার', 'money' => 'টাকা', 'bus' => 'বাস', 'train' => 'ট্রেন', 'car' => 'গাড়ি', 'phone' => 'ফোন', 'letter' => 'চিঠি', 'song' => 'গান', 'football' => 'ফুটবল',
    'cricket' => 'ক্রিকেট', 'village' => 'গ্রাম', 'city' => 'শহর', 'river' => 'নদী', 'room' => 'ঘর', 'door' => 'দরজা', 'name' => 'নাম', 'work' => 'কাজ', 'job' => 'চাকরি',
    'happy' => 'খুশি', 'sad' => 'দুঃখিত', 'tired' => 'ক্লান্ত', 'hungry' => 'ক্ষুধার্ত', 'sick' => 'অসুস্থ', 'busy' => 'ব্যস্ত', 'late' => 'দেরি', 'ready' => 'প্রস্তুত',
    'beautiful' => 'সুন্দর', 'good' => 'ভালো', 'bad' => 'খারাপ', 'big' => 'বড়', 'small' => 'ছোট', 'hot' => 'গরম', 'cold' => 'ঠান্ডা', 'new' => 'নতুন', 'old' => 'পুরনো',
    'bangla' => 'বাংলা', 'bengali' => 'বাংলা', 'english' => 'ইংরেজি', 'dhaka' => 'ঢাকা', 'chittagong' => 'চট্টগ্রাম', 'chattogram' => 'চট্টগ্রাম', 'sylhet' => 'সিলেট',
    'bangladesh' => 'বাংলাদেশ', 'country' => 'দেশ', 'lesson' => 'পড়া', 'homework' => 'বাড়ির কাজ', 'dinner' => 'রাতের খাবার', 'lunch' => 'দুপুরের খাবার',
    'breakfast' => 'সকালের নাস্তা', 'bread' => 'রুটি', 'fruit' => 'ফল', 'mango' => 'আম', 'banana' => 'কলা', 'rain' => 'বৃষ্টি', 'shop' => 'দোকান', 'hospital' => 'হাসপাতাল',
    'family' => 'পরিবার', 'son' => 'ছেলে', 'daughter' => 'মেয়ে', 'wife' => 'স্ত্রী', 'husband' => 'স্বামী', 'clothes' => 'কাপড়', 'shirt' => 'শার্ট', 'medicine' => 'ওষুধ', 'station' => 'স্টেশন', 'field' => 'মাঠ', 'weather' => 'আবহাওয়া', 'pen' => 'কলম', 'tree' => 'গাছ', 'flower' => 'ফুল', 'sun' => 'সূর্য', 'moon' => 'চাঁদ', 'sky' => 'আকাশ', 'road' => 'রাস্তা', 'university' => 'বিশ্ববিদ্যালয়', 'class' => 'ক্লাস', 'computer' => 'কম্পিউটার', 'picture' => 'ছবি', 'movie' => 'সিনেমা', 'game' => 'খেলা', 'story' => 'গল্প', 'question' => 'প্রশ্ন', 'word' => 'শব্দ', 'language' => 'ভাষা', 'dialect' => 'আঞ্চলিক ভাষা'];

/** One English noun / adjective / name -> Bangla. */
function en_bn_word(PDO $pdo, string $w, ?bool &$known = null): string {
    $known = true;
    if (isset(EN_BN_WORD_FIX[$w])) return EN_BN_WORD_FIX[$w];
    if (isset(CORE_EN_BN[$w]) && !in_array($w, ['am', 'is', 'are', 'was', 'be', 'not', 'no'], true)) return CORE_EN_BN[$w];
    foreach ([$w, preg_replace('/ies$/', 'y', $w), preg_replace('/es$/', '', $w), preg_replace('/s$/', '', $w)] as $c) {
        if (isset(EN_BN_WORD_FIX[$c])) return EN_BN_WORD_FIX[$c];
    }
    $r = english_to_bangla($pdo, [$w])[0];
    if (!empty($r['matched'])) return $r['std'];
    if (ctype_digit($w)) return strtr($w, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
    $known = false;
    return $GLOBALS['EN_BN_ORIG'][$w] ?? $w;   // a name or an unknown word stays as typed (with its capital letters)
}
/** Bangla case endings. */
function en_bn_loc(string $b): string {        // in / at / to (a place)
    if (preg_match('/[A-Za-z]$/', $b)) return $b . '-এ';
    if (preg_match('/\s/u', $b) || preg_match('/(তে|য়|ে)$/u', $b)) return $b;
    if (preg_match('/[অআইঈউঊএঐওঔ]$/u', $b)) return $b . 'য়ে';      // ends in a vowel letter: বই -> বইয়ে, বউ -> বউয়ে
    if (preg_match('/া$/u', $b)) return $b . 'য়';
    if (preg_match('/[িীুূোৌ]$/u', $b)) return $b . 'তে';
    return $b . 'ে';
}
function en_bn_gen(string $b): string {        // of / 's
    if (preg_match('/[A-Za-z]$/', $b)) return $b . '-এর';
    if ($b === 'মা') return 'মায়ের';
    if (preg_match('/[অআইঈউঊএঐওঔ]$/u', $b)) return $b . 'য়ের';     // ভাই -> ভাইয়ের, বউ -> বউয়ের
    return preg_match('/[ািীুূেোৌ]$/u', $b) ? $b . 'র' : $b . 'ের';
}
/** "of / with / for" form of a noun or object pronoun (তাকে -> তার, ভাই -> ভাইয়ের). */
function en_bn_objgen(string $b): string {
    foreach (EN_BN_OBJ as $o) if ($o[0] === $b) return $o[1];
    return en_bn_gen($b);
}
function en_bn_is_pron(string $b): bool { foreach (EN_BN_OBJ as $o) if ($o[0] === $b) return true; return false; }
const EN_BN_PEOPLE = ['মা', 'বাবা', 'ভাই', 'বোন', 'বন্ধু', 'শিক্ষক', 'ছাত্র', 'ডাক্তার', 'ছেলে', 'মেয়ে', 'লোক', 'মহিলা', 'শিশু', 'স্ত্রী', 'স্বামী'];

/**
 * Read a noun phrase starting at $i (determiners, numbers, possessives, adjectives, nouns, "and").
 * Returns [bangla, next index, isPerson, words-used] or null.
 */
function en_bn_np(PDO $pdo, array $w, int $i, array &$box): ?array {
    $n = count($w); $parts = []; $start = $i; $person = false; $got = false; $plural = false;
    $stop = array_merge(EN_BN_AUX, ['to', 'in', 'at', 'on', 'into', 'from', 'with', 'for', 'about', 'by', 'near', 'after', 'before', 'without', 'not', 'never', 'and', 'but', 'because', 'so', 'if', 'then', 'of']);
    while ($i < $n) {
        $x = $w[$i];
        if (isset(EN_BN_TIME[$x]) || ($i + 1 < $n && isset(EN_BN_TIME[$x . ' ' . $w[$i + 1]])) || ($i + 2 < $n && isset(EN_BN_TIME[$x . ' ' . $w[$i + 1] . ' ' . $w[$i + 2]]))) break;
        if ($got && ($x === 'and' || $x === 'or') && $i + 1 < $n && !in_array($w[$i + 1], EN_BN_AUX, true) && !isset(EN_BN_SUBJ[$w[$i + 1]]) && en_bn_lemma($w[$i + 1]) === null) {
            $parts[] = $x === 'and' ? 'ও' : 'অথবা'; $box[$i] = $parts[count($parts) - 1]; $i++; $got = false; continue;
        }
        if (in_array($x, ['the'], true)) { $box[$i] = '–'; $i++; continue; }
        if (in_array($x, ['a', 'an'], true)) { $parts[] = 'একটা'; $box[$i] = 'একটা'; $i++; continue; }
        if (isset(EN_BN_POSS[$x]) && !($x === 'her' && ($i + 1 >= $n || in_array($w[$i + 1], $stop, true)))) { $parts[] = EN_BN_POSS[$x]; $box[$i] = EN_BN_POSS[$x]; $i++; continue; }
        if (isset(EN_BN_NUM[$x])) { $parts[] = EN_BN_NUM[$x]; $box[$i] = EN_BN_NUM[$x]; $i++; continue; }
        if (in_array($x, ['some', 'many', 'all', 'every', 'this', 'that', 'these', 'those'], true) && $i + 1 < $n && !in_array($w[$i + 1], $stop, true)) {
            $m = ['some' => 'কিছু', 'many' => 'অনেক', 'all' => 'সব', 'every' => 'প্রতিটি', 'this' => 'এই', 'that' => 'ওই', 'these' => 'এই', 'those' => 'ওই'][$x];
            $parts[] = $m; $box[$i] = $m; $i++; continue;
        }
        if (in_array($x, $stop, true) || isset(EN_BN_TIME[$x]) || isset(EN_BN_WH[$x])) break;
        if (isset(EN_BN_ADV[$x]) && $x !== 'very') break;
        if ($got && en_bn_lemma($x) !== null && !isset(EN_BN_WORD_FIX[$x])) break;   // the next word is a verb
        if (isset(EN_BN_OBJ[$x]) && !$got && !$parts) { $parts[] = EN_BN_OBJ[$x][0]; $box[$i] = EN_BN_OBJ[$x][0]; $i++; $person = true; $got = true; break; }
        if ($x === 'very') { $parts[] = 'খুব'; $box[$i] = 'খুব'; $i++; continue; }
        $b = en_bn_word($pdo, $x, $k);
        if (str_ends_with($x, "'s")) { $b = en_bn_gen(en_bn_word($pdo, substr($x, 0, -2))); }
        $plural = $plural || (preg_match('/s$/', $x) && !isset(EN_BN_WORD_FIX[$x]) && isset(EN_BN_WORD_FIX[preg_replace('/(?<=[^s])s$/', '', $x)])) || in_array($x, ['people', 'children', 'men', 'women'], true);
        $parts[] = $b; $box[$i] = $b; $i++; $got = true;
        if (in_array($b, EN_BN_PEOPLE, true)) $person = true;
        // "book of my brother" -> আমার ভাইয়ের বই
        if ($i + 1 < $n && $w[$i] === 'of') {
            $box[$i] = '(এর)';
            $sub = en_bn_np($pdo, $w, $i + 1, $box);
            if ($sub) { $last = array_pop($parts); $parts[] = en_bn_gen($sub[0]); $parts[] = $last; $i = $sub[1]; }
        }
    }
    if (!$got && !$parts) return null;
    return [implode(' ', $parts), $i, $person, $i - $start, $plural];
}

/** Translate one English clause. $words: lower-case tokens. Returns ['text' => ..., 'box' => [index => bangla]] or null. */
function en_bn_clause(PDO $pdo, array $w): ?array {
    $n = count($w); if ($n < 2 || $n > 25) return null;
    $V = en_bn_verbs(); $box = []; $i = 0;
    $please = false;
    if ($w[0] === 'please') { $please = true; $box[0] = 'দয়া করে'; $i = 1; }
    if ($n > $i + 1 && $w[$n - 1] === 'please') { $please = true; $box[$n - 1] = 'দয়া করে'; array_pop($w); $n--; }
    // ---- question word and inverted auxiliary
    $wh = null; $whSubj = false; $q = false; $aux = []; $neg = false;
    if (isset(EN_BN_WH[$w[$i]])) {
        $wh = $w[$i]; $i++;
        if ($wh === 'how' && in_array($w[$i] ?? '', ['much', 'many'], true)) { $wh = 'how ' . $w[$i]; $box[$i] = 'কত'; $i++; }
        $box[$i - 1] = $box[$i - 1] ?? '';
    }
    if ($i < $n && in_array($w[$i], EN_BN_AUX, true) && $i + 1 < $n && (isset(EN_BN_SUBJ[$w[$i + 1]]) || (!in_array($w[$i + 1], ['not', 'never'], true) && !in_array($w[$i + 1], ['a', 'an'], true) && en_bn_lemma($w[$i + 1]) === null))) {
        // "do you …", "will he …", "is the shop …" (question order)
        if ($wh === null || !in_array($wh, ['who', 'what'], true) || isset(EN_BN_SUBJ[$w[$i + 1]]) || en_bn_lemma($w[$i + 1]) === null) { $aux[] = $w[$i]; $box[$i] = ''; $q = true; $i++; }
    }
    if ($i < $n && $w[$i] === 'not') { $neg = true; $box[$i] = 'না'; $i++; }
    // ---- subject
    $subj = null; $p = 2; $sgen = null; $sobj = null;
    if ($wh !== null && in_array($wh, ['who', 'what'], true) && !$aux && $i < $n && (in_array($w[$i], EN_BN_AUX, true) || en_bn_lemma($w[$i]) !== null)) {
        $subj = EN_BN_SUBJ[$wh][0]; $sgen = EN_BN_SUBJ[$wh][2]; $sobj = EN_BN_SUBJ[$wh][3]; $whSubj = true; $box[$i - 1] = $subj;   // "who will come" – the question word is the subject
    } elseif ($i < $n && isset(EN_BN_SUBJ[$w[$i]]) && !($w[$i] === 'that' && $i > 0)) {
        [$subj, $p, $sgen, $sobj] = EN_BN_SUBJ[$w[$i]]; $box[$i] = $subj; $i++;
    } elseif ($i < $n && $w[$i] === 'there' && in_array($w[$i + 1] ?? '', ['is', 'are', 'was', 'were'], true) && $q === false) {
        $subj = '__there__'; $box[$i] = ''; $i++;
    } elseif ($i < $n && en_bn_lemma($w[$i]) !== null && !isset(EN_BN_WORD_FIX[$w[$i]])) {
        // "wait for me", "come here": no subject, the verb comes first
    } else {
        $np = $i < $n ? en_bn_np($pdo, $w, $i, $box) : null;
        if ($np && $np[3] > 0 && ($i + $np[3] < $n || ($q && array_intersect($aux, ['am', 'is', 'are', 'was', 'were'])))) {
            $subj = $np[0]; if ($np[2] && !empty($np[4]) && !in_array($np[0], ['মানুষ'], true)) $subj .= preg_match('/[ািীুূেোৌ]$/u', $subj) ? 'রা' : 'েরা';   // the boys -> ছেলেরা
            $sgen = en_bn_gen($subj); $sobj = $np[2] ? $subj . 'কে' : $subj; $i = $np[1]; }
    }
    // ---- verb group after the subject
    $modal = null; $be = null; $have = null; $going = false;
    while ($i < $n) {
        $x = $w[$i];
        if ($x === 'not' || $x === 'never') { $neg = true; $box[$i] = $x === 'never' ? 'কখনো না' : 'না'; if ($x === 'never') $aux[] = 'never'; $i++; continue; }
        if (in_array($x, ['will', 'shall', 'can', 'could', 'would', 'should', 'must', 'do', 'does', 'did'], true)) { $aux[] = $x; $box[$i] = ''; $i++; continue; }
        if (in_array($x, ['am', 'is', 'are', 'was', 'were', 'be', 'been'], true)) {
            $aux[] = $x; $box[$i] = ''; $i++;
            if ($x !== 'be' && ($w[$i] ?? '') === 'going' && ($w[$i + 1] ?? '') === 'to' && isset($w[$i + 2]) && en_bn_lemma($w[$i + 2])) { $going = true; $box[$i] = ''; $box[$i + 1] = ''; $i += 2; }
            continue;
        }
        if (in_array($x, ['have', 'has', 'had'], true) && isset($w[$i + 1]) && (in_array($w[$i + 1], ['never', 'already', 'just'], true) || $w[$i + 1] === 'to' || ($l = en_bn_lemma($w[$i + 1])) && in_array($l[1], ['pp', 'past'], true) || $w[$i + 1] === 'been' || $w[$i + 1] === 'not')) { $aux[] = $x; $box[$i] = ''; $i++; continue; }
        break;
    }
    $auxs = array_values(array_diff($aux, ['never']));
    $all = implode(' ', $aux);
    $isFut = (bool) array_intersect($auxs, ['will', 'shall']) || $going;
    $past = (bool) array_intersect($auxs, ['did', 'was', 'were', 'had']);
    // ---- the rest: main verb, objects, places, times
    $verb = null; $vform = 'base';
    $want = null;   // "want to eat", "like to read", "need to go", "have to go"
    if ($i < $n && in_array($w[$i], ['want', 'wants', 'wanted', 'like', 'likes', 'liked', 'love', 'loves', 'need', 'needs', 'needed', 'have', 'has', 'had', 'try', 'tries', 'tried', 'start', 'started', 'began'], true)
        && ($w[$i + 1] ?? '') === 'to' && isset($w[$i + 2]) && en_bn_lemma($w[$i + 2])) {
        $want = en_bn_lemma($w[$i]) ?? [$w[$i], 'base']; if (in_array($w[$i], ['had', 'has', 'have'], true)) $want = ['have', $w[$i] === 'had' ? 'past' : 'base'];
        $box[$i] = ''; $box[$i + 1] = ''; $i += 2;
    }
    $copula = false; $possess = false; $needN = false;
    if ($i < $n && ($l = en_bn_lemma($w[$i])) !== null && !($subj === null && !$q && $wh === null && $i > 0 && $w[$i - 1] !== 'please' && $w[$i - 1] !== 'not')) {
        [$verb, $vform] = $l; $vi = $i; $i++;
    } elseif ($i < $n && $subj !== null && in_array($w[$i], ['have', 'has', 'had'], true)) {
        $possess = true; if ($w[$i] === 'had') $past = true; $box[$i] = 'আছে'; $vi = $i; $i++;
    } elseif ($i < $n && $subj !== null && in_array($w[$i], ['need', 'needs', 'needed'], true)) {
        $needN = true; if ($w[$i] === 'needed') $past = true; $box[$i] = 'দরকার'; $vi = $i; $i++;
    } elseif (array_intersect($auxs, ['am', 'is', 'are', 'was', 'were', 'be', 'been'])) {
        $copula = true;
    } elseif ($subj === null && $i < $n && ($l = en_bn_lemma($w[$i])) !== null) {
        [$verb, $vform] = $l; $vi = $i; $i++;
    } else {
        return null;
    }
    if ($subj === null && ($copula || $possess || $needN)) return null;
    if ($possess && in_array('do', $auxs, true) + in_array('does', $auxs, true) + in_array('did', $auxs, true)) { if (in_array('did', $auxs, true)) $past = true; }
    // tense of a normal verb
    $tense = 'pres';
    if ($verb !== null) {
        $hasBe = (bool) array_intersect($auxs, ['am', 'is', 'are', 'was', 'were']);
        $hasHave = (bool) array_intersect($auxs, ['have', 'has', 'had']);
        if ($isFut) $tense = 'fut';
        elseif ($hasHave && in_array($vform, ['pp', 'past'], true)) $tense = in_array('had', $auxs, true) ? 'pperf' : 'perf';
        elseif ($hasBe && $vform === 'ing') $tense = array_intersect($auxs, ['was', 'were']) ? 'pcont' : 'cont';
        elseif (in_array('did', $auxs, true) || $vform === 'past' || ($vform === 'pp' && !$hasHave)) $tense = 'past';
        elseif ($vform === 'ing' && !$hasBe) return null;
        if ($want !== null) $tense = in_array($want[1], ['past'], true) || in_array('did', $auxs, true) ? 'past' : ($isFut ? 'fut' : 'pres');
    }
    // ---- objects, places, times after the verb
    $objs = []; $places = []; $times = []; $advs = []; $comp = []; $recipients = [];
    $vinfo = $verb !== null ? $V[$verb] : null;
    $motion = $vinfo && !empty($vinfo['motion']);
    while ($i < $n) {
        $x = $w[$i];
        $t2 = $i + 1 < $n ? $x . ' ' . $w[$i + 1] : null; $t3 = $i + 2 < $n ? $x . ' ' . $w[$i + 1] . ' ' . $w[$i + 2] : null;
        if ($t3 !== null && isset(EN_BN_TIME[$t3])) { $times[] = EN_BN_TIME[$t3]; $box[$i] = EN_BN_TIME[$t3]; $box[$i + 1] = $box[$i + 2] = ''; $i += 3; continue; }
        if ($t2 !== null && isset(EN_BN_TIME[$t2])) { $times[] = EN_BN_TIME[$t2]; $box[$i] = EN_BN_TIME[$t2]; $box[$i + 1] = ''; $i += 2; continue; }
        if (isset(EN_BN_TIME[$x])) { $times[] = EN_BN_TIME[$x]; $box[$i] = EN_BN_TIME[$x]; $i++; continue; }
        if ($t2 !== null && isset(EN_BN_ADV[$t2])) { $advs[] = EN_BN_ADV[$t2]; $box[$i] = EN_BN_ADV[$t2]; $box[$i + 1] = ''; $i += 2; continue; }
        if ($x === 'not') { $neg = true; $box[$i] = 'না'; $i++; continue; }
        if ($x === 'home' && ($motion || ($w[$i - 1] ?? '') === 'go')) { $places[] = 'বাড়ি'; $box[$i] = 'বাড়ি'; $i++; continue; }
        if (in_array($x, ['here', 'there'], true)) { $places[] = EN_BN_ADV[$x]; $box[$i] = EN_BN_ADV[$x]; $i++; continue; }
        if (isset(EN_BN_ADV[$x])) { $advs[] = EN_BN_ADV[$x]; $box[$i] = EN_BN_ADV[$x]; $i++; continue; }
        if (in_array($x, ['to', 'in', 'at', 'on', 'into', 'from', 'with', 'for', 'about', 'by', 'near', 'after', 'before', 'without'], true)) {
            $prep = $x; $box[$i] = ''; $i++;
            if ($prep === 'to' && $i < $n && en_bn_lemma($w[$i]) && !isset(EN_BN_WORD_FIX[$w[$i]])) {   // "went to buy rice" – purpose
                $l2 = en_bn_lemma($w[$i]); $box[$i] = en_bn_inf($V[$l2[0]]); $i++;
                $np = $i < $n ? en_bn_np($pdo, $w, $i, $box) : null; if ($np) $i = $np[1];
                $places[] = trim(($np ? $np[0] . ' ' : '') . en_bn_inf($V[$l2[0]])); continue;
            }
            if (in_array($w[$i] ?? '', ['home'], true)) {
                $f = EN_BN_PLACE_FIX['home']; $b = $prep === 'from' ? $f[2] : (in_array($prep, ['to'], true) ? $f[0] : $f[1]); $places[] = $b; $box[$i] = $b; $i++; continue;
            }
            $np = $i < $n ? en_bn_np($pdo, $w, $i, $box) : null;
            if (!$np) return null;
            $i = $np[1]; $b = $np[0];
            $isPron = en_bn_is_pron($b);
            $gen = en_bn_objgen($b);
            switch ($prep) {
                case 'to': if ($motion) $places[] = en_bn_loc($b); else $recipients[] = $isPron ? $b : ($np[2] ? $b . 'কে' : en_bn_loc($b)); break;
                case 'in': case 'at': case 'on': case 'into': $places[] = en_bn_loc($b); break;
                case 'from': $places[] = $b . ' থেকে'; break;
                case 'with': $places[] = $gen . ' সাথে'; break;
                case 'for': $places[] = $gen . ' জন্য'; break;
                case 'about': $objs[] = $gen . ' সম্পর্কে'; break;
                case 'by': $places[] = en_bn_loc($b); break;
                case 'near': $places[] = $gen . ' কাছে'; break;
                case 'after': $times[] = $gen . ' পরে'; break;
                case 'before': $times[] = $gen . ' আগে'; break;
                case 'without': $places[] = $b . ' ছাড়া'; break;
            }
            continue;
        }
        if (in_array($x, ['and', 'but', 'because', 'so', 'if', 'when', 'then', 'or'], true)) return null;   // two clauses: the caller splits them
        $np = en_bn_np($pdo, $w, $i, $box);
        if (!$np || $np[1] === $i) return null;
        $i = $np[1];
        if ($copula) $comp[] = $subj === '__there__' ? $np[0] : preg_replace('/^একটা /u', $np[2] ? 'একজন ' : '', $np[0]);
        elseif ($vinfo && ($vinfo['obj'] ?? '') === 'with') $places[] = en_bn_objgen($np[0]) . ' সাথে';
        elseif ($vinfo && ($vinfo['obj'] ?? '') === 'for') $places[] = en_bn_objgen($np[0]) . ' জন্য';
        elseif ($verb !== null && in_array($verb, ['go', 'come', 'return', 'reach', 'arrive', 'visit', 'leave'], true) && !$np[2] && !en_bn_is_pron($np[0])) $places[] = $verb === 'visit' || $verb === 'leave' ? $np[0] : en_bn_loc($np[0]);
        elseif ($np[2] && !en_bn_is_pron($np[0]) && $verb !== null) $objs[] = $np[0] . 'কে';
        else $objs[] = $np[0];
    }
    // ---- build the Bangla sentence: subject, time, question word, place/recipient, object, manner, verb
    $whB = null;
    if ($wh !== null && !$whSubj) $whB = $copula && $wh === 'how' ? 'কেমন' : (str_starts_with($wh, 'how ') ? 'কত' : EN_BN_WH[$wh]);
    $out = [];
    $verbB = '';
    $np_ = $neg;
    if ($subj === '__there__') {        // there is a shop near my house -> আমার বাড়ির কাছে একটা দোকান আছে
        $out = array_merge($times, $places, $comp ?: $objs);
        $out[] = $past ? 'ছিল' : ($neg ? 'নেই' : 'আছে'); if ($past && $neg) $out[] = 'না';
        return ['text' => implode(' ', $out), 'box' => $box, 'q' => $q];
    }
    if ($possess || $needN) {           // I have a book -> আমার একটা বই আছে ; I need water -> আমার পানি দরকার
        $neg = $neg || in_array('not', $aux, true);
        $out[] = $sgen; $out = array_merge($out, $times);
        if ($q && !$whB) $out[] = 'কি'; if ($whB) $out[] = $whB;
        $out = array_merge($out, $places, $objs);
        if ($possess) { $out[] = $isFut ? 'থাকবে' : ($past ? 'ছিল' : ($neg ? 'নেই' : 'আছে')); if (($past || $isFut) && $neg) $out[] = 'না'; }
        else { $out[] = 'দরকার'; if ($past) $out[] = 'ছিল'; if ($neg) $out[] = $past ? 'না' : 'নেই'; }
        if (isset($vi)) $box[$vi] = $possess ? 'আছে' : 'দরকার';
        return ['text' => implode(' ', array_filter($out, fn($x) => $x !== '' && $x !== null)), 'box' => $box, 'q' => $q];
    }
    if ($copula) {                      // I am happy -> আমি খুশি ; I was at home -> আমি বাড়িতে ছিলাম
        $out[] = $subj;
        if ($q && !$whB) $out[] = 'কি';
        $out = array_merge($out, $times);
        $out = array_merge($out, $advs);
        $loc = !$comp && $places;
        $out = array_merge($out, $places, $comp);
        if ($whB) $out[] = $whB;
        $wasP = array_intersect($auxs, ['was', 'were']); $beFut = $isFut;
        if ($comp === ['দেরি']) return ['text' => $sgen . ' দেরি ' . ($beFut ? 'হবে' : ($wasP ? 'হয়েছিল' : 'হয়েছে')) . ($neg ? ' না' : ''), 'box' => $box, 'q' => $q];
        if ($beFut) $out[] = ['হবো', 'হবে', 'হবে'][$p] . ($neg ? ' না' : '');
        elseif ($wasP) $out[] = ['ছিলাম', 'ছিলে', 'ছিল'][$p] . ($neg ? ' না' : '');
        elseif ($loc || ($whB === 'কোথায়')) $out[] = $neg ? 'নেই' : ($whB === 'কোথায়' && !$loc ? '' : ['আছি', 'আছো', 'আছে'][$p]);
        elseif ($neg) $out[] = ['নই', 'নও', 'নয়'][$p];
        elseif ($whB === 'কেমন') $out[] = ['আছি', 'আছো', 'আছে'][$p];
        return ['text' => implode(' ', array_filter($out, fn($x) => $x !== '')), 'box' => $box, 'q' => $q];
    }
    // normal verb
    $imper = $subj === null;
    if (!$imper) $out[] = $subj;
    if ($please && !$imper) $out[] = 'দয়া করে';
    if ($please && $imper) $out[] = 'দয়া করে';
    if ($q && !$whB && !$whSubj) $out[] = 'কি';
    $out = array_merge($out, $times);
    if (in_array('never', $aux, true)) { $out[] = 'কখনো'; $objs = array_map(fn($o) => preg_replace('/^একটা /u', '', $o), $objs); }
    if ($whB) $out[] = $whB;
    $out = array_merge($out, $recipients, $places, $objs, $advs);
    $modalC = array_values(array_intersect($auxs, ['can', 'could', 'should', 'must', 'would']));
    if ($vinfo && isset($vinfo['pre'])) foreach ($objs as $o) if (preg_match('/(^|\s)' . preg_quote($vinfo['pre'], '/') . '$/u', $o)) { unset($vinfo['pre']); if (isset($vinfo['f'])) unset($vinfo['pre']); break; }
    if ($imper) {
        $verbB = en_bn_imp($vinfo, $neg); $neg = false;
    } elseif ($want !== null) {
        $inf = en_bn_inf($vinfo);
        if ($want[0] === 'need' || $want[0] === 'have') {
            $out[0] = $sobj;                                   // I need to go -> আমাকে যেতে হবে
            $verbB = $inf . ' ' . ($tense === 'past' ? 'হয়েছিল' : 'হবে');
        } else {
            $wv = $want[0] === 'like' || $want[0] === 'love' ? $V['love'] : ($want[0] === 'try' ? $V['try'] : ($want[0] === 'start' || $want[0] === 'begin' ? $V['start'] : $V['want']));
            $verbB = $inf . ' ' . en_bn_conj($wv, $tense === 'past' && $neg ? 'neg_past' : $tense, $p);
            if ($tense === 'past' && $neg) $neg = false;
        }
    } elseif ($modalC) {
        $inf = en_bn_inf($vinfo);
        switch ($modalC[0]) {
            case 'can': $verbB = $inf . ' ' . ($isFut ? ['পারবো', 'পারবে', 'পারবে'][$p] : ['পারি', 'পারো', 'পারে'][$p]); break;
            case 'could': $verbB = $inf . ' ' . ['পারতাম', 'পারতে', 'পারতো'][$p]; break;
            case 'would': $verbB = en_bn_conj($vinfo, 'fut', $p); break;
            case 'should':                                       // you should study -> তোমার পড়াশোনা করা উচিত
                $out[0] = $sgen; $verbB = en_bn_vn($vinfo) . ' উচিত' . ($neg ? ' না' : ''); $neg = false; break;
            case 'must':                                         // I must go -> আমাকে যেতেই হবে
                $out[0] = $sobj; $verbB = $inf . ($neg ? ' নেই' : 'ই হবে'); $neg = false; break;
        }
    } else {
        if ($neg && in_array($tense, ['past', 'perf'], true)) { $verbB = en_bn_conj($vinfo, 'neg_past', $p); $neg = false; }   // খাইনি
        else $verbB = en_bn_conj($vinfo, $tense, $p);
    }
    $out[] = $verbB . ($neg ? ' না' : '');
    if (isset($vi)) $box[$vi] = $verbB . ($np_ ? ' না' : '');
    return ['text' => preg_replace('/\s+/u', ' ', trim(implode(' ', array_filter($out, fn($x) => $x !== '' && $x !== null)))), 'box' => $box, 'q' => $q];
}

/** Translate an English sentence. Splits on and/but/because/so between clauses. Returns ['text','box','words'] or null. */
function en_bn_grammar(PDO $pdo, array $words): ?array {
    $lw = array_map(fn($x) => strtolower($x), $words);
    $GLOBALS['EN_BN_ORIG'] = [];
    foreach ($words as $x) $GLOBALS['EN_BN_ORIG'][strtolower($x)] = $x;
    $n = count($lw);
    if ($n < 2) return null;
    $one = en_bn_clause($pdo, $lw);
    if ($one) { ksort($one['box']); return ['text' => $one['text'], 'box' => en_bn_fill_box($lw, $one['box'])]; }
    // two clauses joined by a conjunction
    $join = ['and' => 'এবং', 'but' => 'কিন্তু', 'because' => 'কারণ', 'so' => 'তাই', 'or' => 'অথবা'];
    for ($k = 1; $k < $n - 1; $k++) {
        if (!isset($join[$lw[$k]])) continue;
        $a = en_bn_clause($pdo, array_slice($lw, 0, $k));
        $right = array_slice($lw, $k + 1);
        // "I eat rice and drink water": the second clause has no subject of its own
        $b = ($a && en_bn_lemma($right[0]) !== null && !isset(EN_BN_SUBJ[$right[0]])) ? null : en_bn_clause($pdo, $right);
        if (!$b && $a && en_bn_lemma($right[0]) !== null) {
            $b = en_bn_clause($pdo, array_merge([$lw[0]], $right));
            if ($b) { $b['text'] = preg_replace('/^\S+\s/u', '', $b['text'], 1); $bb = []; foreach ($b['box'] as $ix => $v) if ($ix > 0) $bb[$ix - 1] = $v; $b['box'] = $bb; }
        }
        if ($a && $b) {
            $box = $a['box']; $box[$k] = $join[$lw[$k]];
            foreach ($b['box'] as $ix => $v) $box[$ix + $k + 1] = $v;
            ksort($box);
            return ['text' => $a['text'] . ' ' . $join[$lw[$k]] . ' ' . $b['text'], 'box' => en_bn_fill_box($lw, $box)];
        }
    }
    return null;
}

/** Word boxes for English helper words, which have no Bangla word of their own. */
function en_bn_fill_box(array $w, array $box): array {
    $hint = ['will' => '(ভবিষ্যৎ কাল: -বো / -বে)', 'shall' => '(ভবিষ্যৎ কাল)', 'do' => '(প্রশ্ন / না বোঝায়)', 'does' => '(প্রশ্ন / না বোঝায়)', 'did' => '(অতীত কাল)',
        'am' => '(চলমান / আছি)', 'is' => '(চলমান / আছে)', 'are' => '(চলমান / আছো)', 'was' => '(ছিল)', 'were' => '(ছিল)', 'be' => '(হওয়া)', 'been' => '(ছিল)',
        'have' => '(সম্পন্ন: -ছি)', 'has' => '(সম্পন্ন: -ছে)', 'had' => '(সম্পন্ন অতীত)', 'can' => 'পারা', 'could' => 'পারতাম', 'should' => 'উচিত', 'must' => 'হবেই',
        'to' => '(-তে / -এ)', 'the' => '(নির্দিষ্ট)', 'going' => '(ভবিষ্যৎ)', 'not' => 'না'];
    foreach ($w as $k => $x) if (($box[$k] ?? '') === '' || ($box[$k] ?? '') === '–') $box[$k] = $hint[$x] ?? ($box[$k] ?? '');
    return $box;
}
