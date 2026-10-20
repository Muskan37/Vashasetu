<?php
/** VashaSetu V3: exact-sentence translation != word-level alignment.
 * Read only individually attested words for word cards. Unattested words
 * are explicitly unknown; never pretend position i in a sentence = position i
 * in the Standard Bangla translation. No cross-language lookup pooling.
 */
const VS_NATIVE_LANGUAGES = ['chakma'=>'Chakma','marma'=>'Marma','garo'=>'Garo', 'tripura'=>'Tripura / Kokborok','sadri'=>'Sadri','santali'=>'Santali','khasi'=>'Khasi','mro'=>'Mro','tanchangya'=>'Tanchangya','bawm'=>'Bawm','khumi'=>'Khumi','rakhine'=>'Rakhine','meitei'=>'Meitei'];

function vs_exact_parallel(PDO $pdo, string $language, string $needle, bool $reverse = false): ?array {
    $needle = bn_normalize($needle);
    if ($needle === '') return null;
    $col = $reverse ? 'standard_key' : 'source_key';
    $sql = "SELECT source_text, standard_bangla, review_status, source_dataset FROM parallel_sentences WHERE language = ? AND $col = ? AND eligible_exact = 1 LIMIT 25";
    $st = $pdo->prepare($sql);
    $st->execute([$language,$needle]);
    $found = $st->fetchAll();
    if (!$found) return null;
    $possible = [];
    foreach ($found as $p) {
        $target = $reverse ? $p['source_text'] : $p['standard_bangla'];
        $possible[$target] = true;
    }
    if (count($possible) !== 1) return null; // ambiguous phrase: refuse to make up a translation
    $best = $found[0];
    return ['text' => array_key_first($possible), 'status'=>$best['review_status'], 'source'=>$best['source_dataset']];
}

/** Per-occurrence meanings, even for phrases whose grammar reorganizes words. */
function vs_word_evidence(PDO $pdo, string $original, string $language, string $target): array {
    $words = $language === 'english'
        ? (preg_split('/[^A-Za-z\x{2019}\x{0027}]+/u', trim($original), -1, PREG_SPLIT_NO_EMPTY) ?: [])
        : bn_tokens($original);
    $words = array_slice($words, 0, 160);
    $read = $pdo->prepare('SELECT standard_word, meaning_status, source_dataset FROM word_meanings WHERE language = ? AND source_word = ? LIMIT 20');
    $reverse = $pdo->prepare('SELECT source_word, meaning_status, source_dataset FROM word_meanings WHERE language = ? AND standard_word = ? LIMIT 20');
    $core = core_bn_en();
    $out = [];
    foreach ($words as $position => $word) {
        $std = null; $evidence = ''; $dataset = ''; $status = 'not_verified'; $candidate = null;
        if ($language === 'standard') {
            $std = $word;
            if ($target === 'english') {
                $eng = bangla_to_english($pdo, [$word])[0];
                if (!empty($eng['matched'])) { $evidence = $eng['tgt']; $status = 'dictionary_reference'; }
            } elseif ($target !== 'standard' && !isset(VS_NATIVE_LANGUAGES[$target])) {
                $reverse->execute([$target,$word]);
                $rows=$reverse->fetchAll();
                $forms=array_unique(array_column($rows,'source_word'));
                if (count($forms)===1) {
                    $status=$rows[0]['meaning_status'];$dataset=$rows[0]['source_dataset'];
                    if ($status === 'needs_native_review') { $candidate=$forms[0]; $status='unreviewed_candidate'; }
                    else $evidence=$forms[0];
                }
            } elseif ($target === 'standard') { $evidence=$word;$status='standard_bangla_identity'; }
            else { $status='not_verified'; }
        } elseif ($language === 'english') {
            $eng=english_to_bangla($pdo,[mb_strtolower($word)])[0];
            if (!empty($eng['matched'])) {
                $std=$eng['std']; $evidence=$std; $status='dictionary_reference';
            }
        } else {
            $read->execute([$language,$word]);
            $rows=$read->fetchAll();
            $possibilities=array_unique(array_column($rows,'standard_word'));
            if (count($possibilities)===1) {
                $dataset=$rows[0]['source_dataset'];$status=$rows[0]['meaning_status'];
                if ($status==='needs_native_review') { $candidate=$possibilities[0]; $status='unreviewed_candidate'; }
                else { $std=$possibilities[0];$evidence=$std; }
            }
            // Do not fall back to statistical alignment: it can assign incorrect meanings.
        }
        $en = ($std!==null && isset($core[$std])) ? $core[$std] : null;
        // Semantic categories are not proof of verification. Keep explicit provenance.
        $out[] = ['position'=>$position, 'word'=>$word, 'standard_meaning'=>$std,
            'target_word'=>($language==='standard' && $target!=='standard')?$evidence:null,
            'english_hint'=>$en, 'status'=>$status, 'dataset'=>$dataset,
            'known'=>($evidence!==''), 'display_meaning'=>$evidence!==''?$evidence:null, 'candidate_meaning'=>$candidate];
    }
    return $out;
}

/** Directly translate from only the chosen language's sentence evidence, without any
 *  cross-region statistical candidate pooling or implicit language re-detection. */
function vs_scoped_translate(PDO $pdo, string $text, string $src, string $tgt, bool $approx): array {
    $fromNative = isset(VS_NATIVE_LANGUAGES[$src]);
    $toNative = isset(VS_NATIVE_LANGUAGES[$tgt]);
    $known = array_merge(DIALECTS, array_keys(VS_NATIVE_LANGUAGES));
    $fromVar = in_array($src, $known, true);
    $toVar = in_array($tgt, $known, true);
    $sourceLang = $fromVar ? $src : ($toVar ? $tgt : null);
    $path = [$src];
    $hit = null;
    $out = '';
    $quality = 'unavailable';
    $notes = [];
    $source = null;
    if ($src === $tgt) {
        $out = $text; $quality = 'identity';
        $notes[] = 'The selected source and target are identical.';
    } elseif ($fromVar && $tgt === 'standard') {
        $hit = vs_exact_parallel($pdo, $src, $text, false);
        if ($hit) $out = $hit['text'];
        $path[] = 'standard';
    } elseif ($src === 'standard' && $toVar) {
        $hit = vs_exact_parallel($pdo, $tgt, $text, true);
        if ($hit) $out = $hit['text'];
        $path[] = $tgt;
    } elseif ($fromVar && $toVar) {
        // Only the two explicitly selected sets and Standard Bangla are queried.
        $p = vs_exact_parallel($pdo, $src, $text, false);
        $path[] = 'standard'; $path[] = $tgt;
        if ($p) {
            $hit = vs_exact_parallel($pdo, $tgt, $p['text'], true);
            if ($hit) $out = $hit['text'];
        }
        $notes[] = 'Dialect-to-dialect translation requires two supported, unambiguous exact sentence matches.';
    } else {
        $notes[] = 'Only selected language → Standard Bangla, Standard Bangla → selected language, or two selected languages via Standard Bangla are supported.';
    }
    if ($hit) {
        $source = $hit['source'];
        $quality = 'research_sentence_candidate_not_reaudited';
        $notes[] = 'Exact source-specific research candidate, not independently reverified by VashaSetu educators.';
    }
    // Optional approximation uses only independently attested one-token meanings from
    // the chosen language. No global lexicon, bigram model or other variety can enter.
    if (!$hit && $out === '' && $approx && (($fromVar && $tgt === 'standard') || ($src === 'standard' && $toVar))) {
        $dictionary = vs_word_evidence($pdo,$text,$src,$tgt);
        if (count($dictionary)>0) {
            $outwords = []; $all = true;
            foreach ($dictionary as $w) {
                $m = $src === 'standard' ? $w['target_word'] : $w['standard_meaning'];
                if (!$m || $w['status'] === 'unreviewed_candidate') { $all = false; break; }
                $outwords[] = $m;
            }
            if ($all) {
                $out=implode(' ',$outwords); $quality='approximate_isolated_word_gloss';
                $notes[]='Unreliable word-for-word gloss, not a grammatical sentence translation. Contains only the selected language single-word records.';
            }
        }
    }
    if ($out === '') $notes[] = 'No unambiguous eligible sentence match in the selected language. Other language datasets were not consulted.';
    return ['success'=>true,'original_text'=>$text,'source'=>$src,'target'=>$tgt,
        'translated_text'=>$out,'romanization'=>($tgt==='standard' && $out!=='')?bn_romanize($out):null,
        'bangla_input'=>null,'transliteration'=>[],'from_memory'=>(bool)$hit,'matched'=>(bool)$hit,
        'matched_words'=>0,'notes'=>$notes,'translation_quality'=>$quality,
        'sentence_source'=>$source,'dataset_scope'=>$sourceLang,'datasets_used'=>array_values(array_unique($path)),
        'detected'=>['script'=>'explicit','variety'=>$src,'label'=>VS_NATIVE_LANGUAGES[$src]??(VARIETY_LABEL[$src]??$src),
        'confidence'=>1,'scores'=>[],'was_auto'=>false]];
}

function vs_translate_with_evidence(PDO $pdo, string $text, string $src, string $tgt, bool $allowApproximate = false): array {
    // Reject unsupported IDs; NEVER treat them as Standard Bangla or silently search all languages.
    $allowed = array_merge(['auto','standard','english','banglish'], DIALECTS, array_keys(VS_NATIVE_LANGUAGES));
    if (!in_array($src,$allowed,true) || !in_array($tgt,array_merge(['standard','english'],DIALECTS,array_keys(VS_NATIVE_LANGUAGES)),true)) {
        throw new InvalidArgumentException('Unsupported source or target language ID');
    }
    $explicit = !in_array($src,['auto','banglish'],true);
    if (!$explicit) {
        // Automatic classification intentionally examines dialects to infer a label;
        // once classified, translation uses only that single detected language.
        $det = detect_text($pdo,$text,$src==='banglish'?'banglish':'auto');
        $src = $det['variety'];
        if ($det['script']==='english') $src='english';
        if ($det['script']==='banglish') {
            // Banglish conversion is shared Standard Bangla preprocessing, not a dialect corpus.
            $text = $det['bangla_text'];
        }
    }
    $fromVar = in_array($src,DIALECTS,true) || isset(VS_NATIVE_LANGUAGES[$src]);
    $toVar = in_array($tgt,DIALECTS,true) || isset(VS_NATIVE_LANGUAGES[$tgt]);
    if ($fromVar || $toVar) {
        $r = vs_scoped_translate($pdo,$text,$src,$tgt,$allowApproximate);
    } else {
        $r = translate_full($pdo,$text,$src,$tgt);
        $r['translation_quality'] = $r['from_memory'] ? 'phrase_or_dictionary' : 'dictionary_word_level_unverified';
        $r['sentence_source'] = null;
        $r['dataset_scope'] = 'standard';
        $r['datasets_used'] = array_values(array_unique([$src,'standard',$tgt]));
    }
    if (!$explicit) {
        $r['detected']['was_auto'] = true;
        $r['notes'][] = 'Automatically detected language. Choose it explicitly for a strictly fixed source language.';
    }
    $r['word_meanings']=vs_word_evidence($pdo,$text,$r['source'],$tgt);
    $r['breakdown']=array_map(static function($w) {return ['src'=>$w['word'],'std'=>$w['standard_meaning']??'',
          'tgt'=>$w['target_word']??null,'matched'=>$w['known'],'kind'=>$w['status'],'conf'=>null,
          'source_dataset'=>$w['dataset']];},$r['word_meanings']);
    $r['matched_words']=count(array_filter($r['word_meanings'],static fn($w)=>$w['known']));
    $r['approximate_allowed']=$allowApproximate;
    return $r;
}
