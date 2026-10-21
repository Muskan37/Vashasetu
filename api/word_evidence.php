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
    // Approximate word-by-word translation for the six Bangla regional varieties. It uses ONLY the selected variety's
    // learned lexicon (no pooling across dialects), picks the word sense from the neighbouring words, and is labelled
    // as unverified. Indigenous languages have no such lexicon and are never approximated this way.
    $approxRows = null;
    if (!$hit && $out === '' && $approx) {
        $bnTok = bn_tokens($text);
        if ($bnTok && in_array($src, DIALECTS, true) && $tgt === 'standard') {
            $approxRows = dialect_to_standard($pdo, $src, $bnTok, null);
            $out = implode(' ', array_column($approxRows, 'std'));
        } elseif ($bnTok && $src === 'standard' && in_array($tgt, DIALECTS, true)) {
            $approxRows = standard_to_dialect($pdo, $tgt, $bnTok);
            $out = implode(' ', array_column($approxRows, 'tgt'));
        }
        if ($approxRows !== null && $out !== '') {
            $quality = 'approximate_word_by_word';
            $unsure = [];
            foreach ($approxRows as $ar) if (!empty($ar['matched']) && isset($ar['conf']) && $ar['conf'] < 0.6) $unsure[] = $src === 'standard' ? $ar['tgt'] : $ar['src'];
            $notes[] = 'Approximate word-by-word translation using only the ' . ($src === 'standard' ? $tgt : $src) . ' data. It is not a checked sentence translation and may be wrong. Please double-check anything important.';
            if ($unsure) $notes[] = 'Less sure about: ' . implode(', ', array_slice(array_values(array_unique($unsure)), 0, 6)) . '.';
        } else { $approxRows = null; $out = ''; }
    }
    if ($out === '') $notes[] = 'No unambiguous eligible sentence match in the selected language. Other language datasets were not consulted.';
    return ['success'=>true,'original_text'=>$text,'source'=>$src,'target'=>$tgt,
        'translated_text'=>$out,'romanization'=>($tgt==='standard' && $out!=='')?bn_romanize($out):null,
        'bangla_input'=>null,'transliteration'=>[],'from_memory'=>(bool)$hit,'matched'=>(bool)$hit,
        'matched_words'=>0,'notes'=>$notes,'translation_quality'=>$quality,
        'sentence_source'=>$source,'dataset_scope'=>$sourceLang,'datasets_used'=>array_values(array_unique($path)),'approx_rows'=>$approxRows,
        'detected'=>['script'=>'explicit','variety'=>$src,'label'=>VS_NATIVE_LANGUAGES[$src]??(VARIETY_LABEL[$src]??$src),
        'confidence'=>1,'scores'=>[],'was_auto'=>false]];
}

/**
 * Automatic recognition checks exact language-scoped phrase evidence first.
 * Statistical classifier probabilities are not calibrated, so never force a
 * short or conflicting expression into a regional corpus.
 * Detection is the ONLY operation allowed to compare different languages.
 */
function vs_auto_source(PDO $pdo, string $input, string $hint): array {
    $det = detect_text($pdo, $input, $hint === 'banglish' ? 'banglish' : 'auto');
    $script = $det['script'];
    $text = $script === 'banglish' ? $det['bangla_text'] : $input;
    if ($script === 'english') return ['source'=>'english','text'=>$input,'det'=>$det,'candidates'=>[], 'reason'=>''];
    if ($script === 'other') return ['source'=>null,'text'=>$input,'det'=>$det,'candidates'=>[], 'reason'=>'The input does not contain enough recognizable language text.'];
    $key=bn_normalize($text);
    $st=$pdo->prepare('SELECT DISTINCT language FROM parallel_sentences WHERE source_key=? AND eligible_exact=1 LIMIT 50');
    $st->execute([$key]);
    $exact=array_values(array_unique($st->fetchAll(PDO::FETCH_COLUMN)));
    $st=$pdo->prepare('SELECT 1 FROM parallel_sentences WHERE standard_key=? AND eligible_exact=1 LIMIT 1');
    $st->execute([$key]);
    $standardMatch=(bool)$st->fetchColumn();
    // Exact one-word dictionary evidence may also identify regional candidates,
    // but a word can belong to multiple regions: only treat it as an ambiguity hint.
    $tokens=bn_tokens($text);
    $wordCandidates=[];
    if (count($tokens) === 1 && !$exact) {
        $st=$pdo->prepare("SELECT DISTINCT language FROM word_meanings WHERE source_word=? AND meaning_status IN ('community_verified','publication_reviewed_not_reaudited','existing_curated_needs_recheck') LIMIT 50");
        $st->execute([$tokens[0]]);
        $wordCandidates=$st->fetchAll(PDO::FETCH_COLUMN);
    }
    if (count($exact)===1 && !$standardMatch) return ['source'=>$exact[0], 'text'=>$text,'det'=>$det,'candidates'=>[],'reason'=>''];
    if (!$exact && $standardMatch && !$wordCandidates) return ['source'=>'standard','text'=>$text,'det'=>$det,'candidates'=>[],'reason'=>''];
    $candidates=[];
    foreach (array_merge($exact,$wordCandidates,$standardMatch?['standard']:[]) as $id) {
        if (!isset($candidates[$id])) $candidates[$id]=['id'=>$id];
    }
    $known=(int)($det['known']??0);
    $prob=(float)($det['confidence']??0);
    $ranked=$det['scores']??[];
    // With no exact evidence, permit a statistical dialect classification only
    // when multiple words support it and there is a large relative separation.
    // A softmax value is not itself an accuracy guarantee.
    $runner=(float)($ranked[1]['p']??0);
    if (!$exact && !$wordCandidates && !$standardMatch && count($tokens)>=4 && $known>=3 && $prob>=0.98 && $prob-$runner>=0.85 && isset($det['variety'])) {
        return ['source'=>$det['variety'],'text'=>$text,'det'=>$det,'candidates'=>[],
            'reason'=>'High-margin statistical classification; not verified by a native speaker.'];
    }
    // Plain Standard Bangla is the most common input. When nothing in any dialect dataset matches and the classifier
    // clearly prefers Standard Bangla, treat it as Standard instead of stopping to ask.
    if (!$exact && !$wordCandidates && ($det['variety'] ?? '') === 'standard' && $prob >= 0.7) {
        return ['source'=>'standard','text'=>$text,'det'=>$det,'candidates'=>[],'reason'=>'Looks like Standard Bangla (statistical guess, not verified).'];
    }
    if (!$candidates) foreach (array_slice($ranked,0,4) as $r) {
        if (!empty($r['variety'])) $candidates[$r['variety']]=['id'=>$r['variety']];
    }
    $reason = count($exact)>1 || (count($exact)>0 && $standardMatch)
        ? 'This exact phrase appears in more than one language dataset or also matches Standard Bangla. Select the intended source.'
        : (count($wordCandidates)>0 ? 'This word occurs in one or more dialect dictionaries, but one word is not enough to identify a dialect safely.'
        : 'The input does not have enough unique, reliable dialect evidence to identify its region.');
    return ['source'=>null,'text'=>$text,'det'=>$det,'candidates'=>array_values($candidates),'reason'=>$reason];
}

function vs_translate_with_evidence(PDO $pdo, string $text, string $src, string $tgt, bool $allowApproximate = false): array {
    // Reject unsupported IDs; NEVER treat them as Standard Bangla or silently search all languages.
    $allowed = array_merge(['auto','standard','english','banglish'], DIALECTS, array_keys(VS_NATIVE_LANGUAGES));
    if (!in_array($src,$allowed,true) || !in_array($tgt,array_merge(['standard','english'],DIALECTS,array_keys(VS_NATIVE_LANGUAGES)),true)) {
        throw new InvalidArgumentException('Unsupported source or target language ID');
    }
    $requestedSource = $src;
    $inputOriginal = $text;
    $explicit = !in_array($src,['auto','banglish'],true);
    $autoDet = null;
    $manualBanglish = null;
    // A user confirming a Bangla regional dialect may have typed it in Roman
    // letters. Convert the spelling only for known Bangla regional varieties;
    // Indigenous Latin-script languages are NEVER implicitly transliterated.
    if ($explicit && in_array($src,DIALECTS,true)) {
        $counts = script_stats($text);
        if ($counts['latin'] > 0 && $counts['bangla'] === 0) {
            $manualBanglish = detect_text($pdo,$text,'banglish');
            $text = $manualBanglish['bangla_text'];
        }
    }
    if (!$explicit) {
        $autoDet = vs_auto_source($pdo,$text,$src);
        if ($autoDet['source'] === null) {
            return ['success'=>true,'original_text'=>$text,'source'=>null,'target'=>$tgt,
              'translated_text'=>'','romanization'=>null,'bangla_input'=>null,'transliteration'=>[],
              'from_memory'=>false,'matched'=>false,'matched_words'=>0,
              'notes'=>['Choose the input language before translating. No other dialect was used as a fallback.'],
              'translation_quality'=>'source_confirmation_required',
              'sentence_source'=>null,'dataset_scope'=>null,'datasets_used'=>[],
              'detected'=>['script'=>$autoDet['det']['script'],'variety'=>'undetermined','label'=>'Input not confirmed',
                 'confidence'=>$autoDet['det']['confidence']??0,'scores'=>$autoDet['det']['scores']??[], 'was_auto'=>true],
              'requires_source_confirmation'=>true,
              'source_candidates'=>$autoDet['candidates'],'detection_reason'=>$autoDet['reason'],
              'word_meanings'=>[],'breakdown'=>[]];
        }
        $src=$autoDet['source'];
        $text=$autoDet['text'];
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
        $r['detected']['script'] = $autoDet['det']['script'];
        $r['detected']['confidence'] = (float)($autoDet['det']['confidence'] ?? 0);
        $r['detected']['scores'] = $autoDet['det']['scores'] ?? [];
        $r['detected']['method'] = ($autoDet['reason'] === '' && $src !== 'english') ? 'single_dataset_phrase_match' : ($src === 'english' ? 'script_heuristic' : 'statistical');
        if ($autoDet['det']['script'] === 'banglish') {
            $r['bangla_input'] = $autoDet['text'];
            $r['transliteration'] = $autoDet['det']['transliteration'] ?? [];
        }
        $r['original_text'] = $requestedSource === 'auto' || $requestedSource === 'banglish' ? $inputOriginal : $text;
        $r['notes'][] = 'Input language was inferred from research evidence or statistical scoring; it is not native-speaker verification.';
        if ($autoDet['reason'] !== '') $r['notes'][] = $autoDet['reason'];
    }
    if ($manualBanglish !== null) {
        $r['original_text'] = $inputOriginal;
        $r['bangla_input'] = $text;
        $r['transliteration'] = $manualBanglish['transliteration'] ?? [];
        $r['detected']['script'] = 'banglish';
        $r['notes'][] = 'Roman-script input transliterated for the explicitly chosen Bangla regional variety; review the spelling.';
    }
    $r['word_meanings']=vs_word_evidence($pdo,$text,$r['source'],$tgt);
    $r['breakdown']=array_map(static function($w) {return ['src'=>$w['word'],'std'=>$w['standard_meaning']??'',
          'tgt'=>$w['target_word']??null,'matched'=>$w['known'],'kind'=>$w['status'],'conf'=>null,
          'source_dataset'=>$w['dataset']];},$r['word_meanings']);
    // Words with no individually attested meaning get the engine's learned (unverified) meaning when one exists.
    if (!empty($r['approx_rows']) && count($r['approx_rows']) === count($r['breakdown'])) {
        foreach ($r['breakdown'] as $i => &$bw) {
            $ar = $r['approx_rows'][$i];
            if (empty($bw['matched']) && !empty($ar['matched'])) {
                if ($src === 'standard') $bw['tgt'] = $ar['tgt']; else $bw['std'] = $ar['std'];
                $bw['matched'] = true; $bw['kind'] = 'learned_unverified'; $bw['conf'] = $ar['conf'] ?? null;
            }
        }
        unset($bw);
    }
    unset($r['approx_rows']);
    $r['matched_words']=count(array_filter($r['word_meanings'],static fn($w)=>$w['known']));
    $r['approximate_allowed']=$allowApproximate;
    $r['requires_source_confirmation']=false;
    $r['source_candidates']=[];
    return $r;
}
