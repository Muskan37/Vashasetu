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

/** Words of an indigenous language written in Bangla letters: ' and • are part of the word (চা'আ, হি•গুব্বা). */
function vs_native_tokens(string $s): array {
    $s = bn_native_normalize($s);
    return $s === '' ? [] : preg_split('/\s+/u', $s);
}
/** Per-occurrence meanings, even for phrases whose grammar reorganizes words. */
function vs_word_evidence(PDO $pdo, string $original, string $language, string $target): array {
    $words = $language === 'english'
        ? (preg_split('/[^A-Za-z\x{2019}\x{0027}]+/u', trim($original), -1, PREG_SPLIT_NO_EMPTY) ?: [])
        : (isset(VS_NATIVE_LANGUAGES[$language]) ? vs_native_tokens($original) : bn_tokens($original));
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
            else {
                // indigenous language: only words a native speaker reviewed (e.g. the Garo list); the main form wins
                $reverse->execute([$target,$word]);
                $rows=array_values(array_filter($reverse->fetchAll(), static fn($r) => in_array($r['meaning_status'], ['native_speaker_reported','native_speaker_alternative'], true)));
                usort($rows, static fn($a, $b) => ($a['meaning_status'] === 'native_speaker_alternative') <=> ($b['meaning_status'] === 'native_speaker_alternative'));
                $main=array_unique(array_column(array_filter($rows, static fn($r) => $r['meaning_status'] === 'native_speaker_reported'), 'source_word'));
                if ($rows && count($main) <= 1) { $evidence=$rows[0]['source_word']; $status=$rows[0]['meaning_status']; $dataset=$rows[0]['source_dataset']; }
                else $status='not_verified';
            }
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
        if (in_array($hit['status'] ?? '', ['native_speaker_reported', 'community_verified'], true)) {
            $quality = 'native_speaker_sentence';
            $notes[] = 'Exact sentence from a native speaker\'s list.';
        } else {
            $quality = 'research_sentence_candidate_not_reaudited';
            $notes[] = 'Exact source-specific research candidate, not independently reverified by VashaSetu educators.';
        }
    }
    // Optional approximation uses only independently attested one-token meanings from
    // the chosen language. No global lexicon, bigram model or other variety can enter.
    if (!$hit && $out === '' && $approx && (($fromVar && $tgt === 'standard') || ($src === 'standard' && $toVar))) {
        $dictionary = vs_word_evidence($pdo,$text,$src,$tgt);
        if (count($dictionary)>0) {
            $outwords = []; $all = true;
            $native = isset(VS_NATIVE_LANGUAGES[$src]) || isset(VS_NATIVE_LANGUAGES[$tgt]);
            $missing = []; $found = 0;
            foreach ($dictionary as $w) {
                $m = $src === 'standard' ? $w['target_word'] : $w['standard_meaning'];
                if (!$m || $w['status'] === 'unreviewed_candidate') {
                    if (!$native) { $all = false; break; }
                    $missing[] = $w['word']; $outwords[] = $w['word']; continue;
                }
                $outwords[] = $m; $found++;
            }
            if ($native && $found === 0) $all = false;
            if ($all && $missing) $notes[] = 'Not in the native-speaker reviewed ' . (VS_NATIVE_LANGUAGES[$src] ?? VS_NATIVE_LANGUAGES[$tgt]) . ' word list yet, so they are left as they are: ' . implode(', ', $missing) . '.';
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
        } elseif ($bnTok && in_array($src, DIALECTS, true) && in_array($tgt, DIALECTS, true)) {
            // Dialect -> Standard Bangla -> dialect, word by word. Only these two varieties' data is used.
            $first = dialect_to_standard($pdo, $src, $bnTok, null);
            if ($src === 'Noakhali') $first = vs_noakhali_negative_future($first, true);   // খাইতান ন -> খাবো না before the second step
            $stdTok = array_column($first, 'std');
            $second = $stdTok ? standard_to_dialect($pdo, $tgt, $stdTok) : [];
            if (count($first) === count($second)) {
                $approxRows = [];
                foreach ($first as $k => $a) {
                    $b = $second[$k];
                    $approxRows[] = ['src'=>$a['src'] ?? $bnTok[$k], 'std'=>$a['std'], 'tgt'=>$b['tgt'],
                        'matched'=>!empty($a['matched']) || !empty($b['matched']),
                        'conf'=>min($a['conf'] ?? 1, $b['conf'] ?? 1)];
                }
                $out = implode(' ', array_column($approxRows, 'tgt'));
                $path[] = 'standard'; $path[] = $tgt;
            }
        }
        // Noakhali "will not" rule. Dialect -> dialect from Noakhali already applied it before the second step;
        // there the output is the TARGET dialect's words (column tgt), not the Standard Bangla bridge.
        if ($approxRows !== null && $out !== '' && (($src === 'Noakhali' && $tgt === 'standard') || $tgt === 'Noakhali')) {
            $toStd = $src === 'Noakhali' && $tgt === 'standard';
            $approxRows = vs_noakhali_negative_future($approxRows, $toStd);
            $out = implode(' ', array_column($approxRows, $toStd ? 'std' : 'tgt'));
        }
        if ($approxRows !== null && $out !== '') {
            $quality = 'approximate_word_by_word';
            $unsure = [];
            foreach ($approxRows as $ar) if (!empty($ar['matched']) && isset($ar['conf']) && $ar['conf'] < 0.6) $unsure[] = ($src === 'standard' || $tgt !== 'standard') ? $ar['tgt'] : $ar['src'];
            $notes = array_values(array_filter($notes, static fn($n) => strpos($n, 'Dialect-to-dialect') !== 0));
            $notes[] = 'Approximate word-by-word translation using only the ' . (in_array($src, DIALECTS, true) && in_array($tgt, DIALECTS, true) ? $src . ' and ' . $tgt : ($src === 'standard' ? $tgt : $src)) . ' data. It is not a checked sentence translation and may be wrong. Please double-check anything important.';
            if ($unsure) $notes[] = 'Less sure about: ' . implode(', ', array_slice(array_values(array_unique($unsure)), 0, 6)) . '.';
        } else { $approxRows = null; $out = ''; }
    }
    // Exact sentence found: still give every word its own meaning for the word boxes.
    // Same number of words -> pair them by position with the matched sentence; otherwise use the word-by-word engine.
    if ($hit && $out !== '' && $approxRows === null) {
        $inTok = bn_tokens($text); $outTok = bn_tokens($out);
        $dial2std = in_array($src, DIALECTS, true) && $tgt === 'standard';
        $std2dial = $src === 'standard' && in_array($tgt, DIALECTS, true);
        if ($inTok && ($dial2std || $std2dial)) {
            $dia = $dial2std ? $src : $tgt;
            $eng = $dial2std ? dialect_to_standard($pdo, $dia, $inTok, null) : standard_to_dialect($pdo, $dia, $inTok);
            $col = $dial2std ? 'std' : 'tgt';
            // which (dialect word, standard word) pairs the learned lexicon knows, to trust a by-position pairing
            $known = [];
            if (count($inTok) === count($outTok)) {
                $dw = $dial2std ? $inTok : $outTok; $sw = $dial2std ? $outTok : $inTok;
                $q = $pdo->prepare('SELECT dialect_word, standard_word FROM lexicon WHERE dialect = ? AND dialect_word IN (' . in_placeholders($dw) . ') AND standard_word IN (' . in_placeholders($sw) . ')');
                $q->execute(array_merge([$dia], $dw, $sw));
                foreach ($q->fetchAll() as $k) $known[$k['dialect_word'] . '|' . $k['standard_word']] = true;
            }
            $approxRows = [];
            foreach ($inTok as $i => $w) {
                $e = $eng[$i][$col] ?? $w;
                $m = $e;
                if (count($inTok) === count($outTok)) {
                    $p = $outTok[$i];
                    $pair = $dial2std ? ($w . '|' . $p) : ($p . '|' . $w);
                    if ($p === $w || isset($known[$pair]) || ($hit['status'] ?? '') === 'native_speaker_reported') $m = $p;      // the matched sentence's own word at this position
                }
                $approxRows[] = $dial2std ? ['src' => $w, 'std' => $m, 'matched' => $m !== $w, 'conf' => 0.9]
                                          : ['std' => $w, 'tgt' => $m, 'matched' => $m !== $w, 'conf' => 0.9];
            }
        }
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
            $text = vs_bn_fullstops(vs_punctuate($text, $manualBanglish['bangla_text']));
        }
    }
    if ($explicit && $src === 'standard') {
        $counts = script_stats($text);
        if ($counts['latin'] > 0 && $counts['bangla'] === 0) {
            $manualBanglish = detect_text($pdo,$text,'banglish');
            $text = vs_bn_fullstops(vs_punctuate($text, $manualBanglish['bangla_text']));
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
        $text=($autoDet['det']['script'] ?? '') === 'banglish' ? vs_bn_fullstops(vs_punctuate($text, $autoDet['text'])) : $autoDet['text'];
    }
    $fromVar = in_array($src,DIALECTS,true) || isset(VS_NATIVE_LANGUAGES[$src]);
    $toVar = in_array($tgt,DIALECTS,true) || isset(VS_NATIVE_LANGUAGES[$tgt]);
    $r = vs_translate_by_sentence($pdo, $text, $src, $tgt, $allowApproximate, $fromVar || $toVar);
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
    // English sentence read with grammar rules: each English word box shows what it became in this sentence (will + eat -> খাবো)
    $eb = $r['en_breakdown'] ?? ($r['breakdown'] ?? []);
    if ($r['source'] === 'english' && $eb && array_filter($eb, static fn($b) => ($b['kind'] ?? '') === 'grammar') && count($eb) === count($r['word_meanings'])) {
        foreach ($r['word_meanings'] as $k => &$wm) {
            $m = trim((string) ($eb[$k]['std'] ?? ''));
            if ($m === '' || $m === '–') { $m = '(grammar word)'; }
            $wm['standard_meaning'] = $m; $wm['display_meaning'] = $m; $wm['known'] = true; $wm['status'] = 'dictionary_reference';
            $wm['dataset'] = 'English grammar rules + dictionary';
        }
        unset($wm);
    }
    unset($r['en_breakdown']);
    $r['breakdown']=array_map(static function($w) {return ['src'=>$w['word'],'std'=>$w['standard_meaning']??'',
          'tgt'=>$w['target_word']??null,'matched'=>$w['known'],'kind'=>$w['status'],'conf'=>null,
          'source_dataset'=>$w['dataset']];},$r['word_meanings']);
    $nativeSentence = ($r['translation_quality'] ?? '') === 'native_speaker_sentence';   // a native speaker's sentence: its own words win in the boxes
    // Words with no individually attested meaning get the engine's learned (unverified) meaning when one exists.
    if (!empty($r['approx_rows']) && count($r['approx_rows']) === count($r['breakdown'])) {
        foreach ($r['breakdown'] as $i => &$bw) {
            $ar = $r['approx_rows'][$i];
            if ((empty($bw['matched']) || $nativeSentence || !empty($ar['rule'])) && !empty($ar['matched'])) {
                if ($src === 'standard') $bw['tgt'] = $ar['tgt']; else $bw['std'] = $ar['std'];
                $bw['matched'] = true; $bw['kind'] = 'learned_unverified'; $bw['conf'] = $ar['conf'] ?? null;
            }
        }
        unset($bw);
    }
    // Fill every remaining word with the best learned or candidate meaning (shown as a normal meaning).
    foreach ($r['word_meanings'] as $i => &$wm) {
        if (!empty($wm['known']) && !$nativeSentence && empty($r['approx_rows'][$i]['rule'])) continue;
        $bw = $r['breakdown'][$i] ?? null;
        $m = null;
        if ($bw && ($bw['kind'] ?? '') === 'learned_unverified') $m = ($src === 'standard') ? ($bw['tgt'] ?? null) : ($bw['std'] ?? null);
        if (($m === null || $m === '') && !empty($r['approx_rows']) && count($r['approx_rows']) === count($r['word_meanings'])) {
            // engine kept the word as it is (same form in both varieties): prefer that over an unreliable pair
            $ar = $r['approx_rows'][$i]; $m = ($src === 'standard') ? ($ar['tgt'] ?? null) : ($ar['std'] ?? null);
        }
        if (($m === null || $m === '') && !empty($wm['candidate_meaning'])) $m = $wm['candidate_meaning'];
        if ($m === null || $m === '') continue;
        if ($src === 'standard' && $tgt !== 'standard') $wm['target_word'] = $m; else $wm['standard_meaning'] = $m;
        $wm['display_meaning'] = $m; $wm['known'] = true; $wm['status'] = $nativeSentence ? 'native_speaker_reported' : 'learned';
    }
    unset($wm);
    // Dialect to dialect (e.g. Noakhali → Chattogram): the boxes and the bridge must show the TARGET dialect word,
    // not the Standard Bangla word used in between. Same number of words: take them from the translated sentence
    // in order (Bangla varieties keep the word order); otherwise translate each Standard word on its own.
    if ($src !== 'standard' && $tgt !== 'standard' && $tgt !== 'english' && $src !== 'english' && !empty($r['word_meanings'])) {
        $outTok = array_values(array_filter(array_map(static fn($t) => preg_replace('/^[\s?!,.;:।"\'()\[\]‘’“”—-]+|[\s?!,.;:।"\'()\[\]‘’“”—-]+$/u', '', $t),
            preg_split('/\s+/u', trim((string) ($r['translated_text'] ?? '')))), static fn($t) => $t !== ''));
        $same = count($outTok) === count($r['word_meanings']);
        foreach ($r['word_meanings'] as $i => &$wm) {
            $std = trim((string) ($wm['standard_meaning'] ?? ''));
            if ($same) $t = $outTok[$i];
            elseif ($std !== '' && !empty($wm['known'])) {
                $one = vs_translate_with_evidence($pdo, $std, 'standard', $tgt, $allowApproximate);
                $t = trim((string) ($one['translated_text'] ?? ''));
            } else $t = '';
            if ($t === '') continue;
            $wm['target_word'] = $t; $wm['display_meaning'] = $t;
            if (isset($r['breakdown'][$i])) $r['breakdown'][$i]['tgt'] = $t;
        }
        unset($wm);
    }
    unset($r['approx_rows']);
    $r['matched_words']=count(array_filter($r['word_meanings'],static fn($w)=>$w['known']));
    $r['approximate_allowed']=$allowApproximate;
    $r['requires_source_confirmation']=false;
    $r['source_candidates']=[];
    return $r;
}

/** Translate one sentence (or the whole text when it has a single sentence) with the right engine. */
function vs_translate_one(PDO $pdo, string $text, string $src, string $tgt, bool $approx, bool $scoped): array {
    if ($src === 'english' && $tgt !== 'standard' && $tgt !== 'english') {
        // English -> a dialect or language: first English -> Standard Bangla (with grammar), then Standard Bangla -> the chosen one
        $first = vs_translate_one($pdo, $text, 'english', 'standard', $approx, false);
        $std = trim((string) ($first['translated_text'] ?? ''));
        $r = $std !== '' ? vs_scoped_translate($pdo, $std, 'standard', $tgt, $approx) : $first;
        $r['source'] = 'english'; $r['original_text'] = $text; $r['bridge_standard'] = $std;
        $r['datasets_used'] = ['english', 'standard', $tgt];
        $r['notes'] = array_values(array_unique(array_merge($first['notes'] ?? [], $r['notes'] ?? [], $std !== '' ? ['Translated English → Standard Bangla (' . $std . ') → ' . (VARIETY_LABEL[$tgt] ?? $tgt) . '.'] : [])));
        $r['en_breakdown'] = $first['breakdown'] ?? [];
        unset($r['approx_rows']);
        return $r;
    }
    if ($scoped) return vs_scoped_translate($pdo, $text, $src, $tgt, $approx);
    $r = translate_full($pdo, $text, $src, $tgt);
    $r['translation_quality'] = $r['from_memory'] ? 'phrase_or_dictionary' : 'dictionary_word_level_unverified';
    $r['sentence_source'] = null;
    $r['dataset_scope'] = 'standard';
    $r['datasets_used'] = array_values(array_unique([$src, 'standard', $tgt]));
    return $r;
}

/** Split text into sentences, keeping each sentence's own end mark (। ? ! . or a line break) and the space after it. */
/** Banglish typed with Roman full stops, now in Bangla letters: a sentence-ending "." becomes the Bangla দাঁড়ি (।).
 *  "..." and decimal numbers (2.5) are left alone. */
function vs_bn_fullstops(string $s): string {
    return preg_replace('/(?<![.\d])\.(?![.\d])(?=\s|$)/u', '।', $s);
}

function vs_split_sentences(string $text): array {
    preg_match_all('/[^।?!\n]+?(?:[।?!]+|\.(?=\s|$)|(?=\n)|$)\s*|[।?!\n]+\s*/u', $text, $m);
    $out = array_values(array_filter($m[0], static fn($x) => $x !== ''));
    // A full stop after a title (Mr. Dr.) or a list number (১. 2.) does not end the sentence: join it to the next part.
    $joined = [];
    foreach ($out as $part) {
        $k = count($joined) - 1;
        if ($k >= 0 && preg_match('/(?:^|\s)(?:mr|mrs|ms|dr|prof|st|jr|sr|vs|no|e\.g|i\.e|[\d০-৯]{1,3})\.\s*$/iu', $joined[$k])) $joined[$k] .= $part;
        else $joined[] = $part;
    }
    return $joined ?: [$text];
}

/** Put the input's punctuation back into a translation. When the translation has one word for each input word
 *  (word-by-word results), every mark goes back next to its own word; otherwise only the sentence's opening
 *  and closing marks are kept (an exact sentence translation can change the word order). */
function vs_punctuate(string $chunk, string $out): string {
    $tail = preg_match('/\s+$/u', $chunk, $m) ? (strpos($m[0], "\n") !== false ? "\n" : ' ') : '';
    $out = trim($out);
    if ($out === '') return '';
    $raw = preg_split('/\s+/u', trim($chunk), -1, PREG_SPLIT_NO_EMPTY);
    $pieces = [];                           // [lead, number of words, trail]
    foreach ($raw as $t) {
        preg_match('/^([\p{P}\p{S}]*)(.*?)([\p{P}\p{S}]*)$/us', $t, $q);
        $n = count(bn_tokens($q[2]));
        if ($n === 0) {                     // a mark on its own, e.g. " - " or " — "
            if ($pieces) $pieces[count($pieces) - 1][2] .= ' ' . $t; else $pieces[] = [$t . ' ', 0, '', ''];
            continue;
        }
        $pieces[] = [$q[1], $n, $q[3], $q[2]];
    }
    // words of the translation without any marks of their own (some sentences in the data carry a ।)
    $words = array_values(array_filter(array_map(static fn($w) => preg_replace('/^[\p{P}\p{S}]+|[\p{P}\p{S}]+$/u', '', $w),
        preg_split('/\s+/u', $out, -1, PREG_SPLIT_NO_EMPTY)), static fn($w) => $w !== ''));
    $need = array_sum(array_column($pieces, 1));
    if ($pieces && $need === count($words)) {
        $res = []; $i = 0;
        foreach ($pieces as [$lead, $n, $trail, $core]) {
            $part = array_slice($words, $i, $n);
            if ($n > 1) {                   // a word joined by a mark, e.g. ইন্দো-আর্য: keep the mark between the parts
                $seps = preg_split('/[^\p{P}\p{S}]+/u', $core, -1, PREG_SPLIT_NO_EMPTY);
                $join = '';
                foreach ($part as $k => $w) $join .= ($k > 0 ? ($seps[$k - 1] ?? ' ') : '') . $w;
                $res[] = $lead . $join . $trail;
            } else $res[] = $lead . implode(' ', $part) . $trail;
            $i += $n;
        }
        return implode(' ', array_filter($res, static fn($x) => $x !== '')) . $tail;
    }
    $lead = $pieces[0][0] ?? '';
    $end = $pieces ? $pieces[count($pieces) - 1][2] : '';
    $out = preg_replace('/[\p{P}\p{S}]+$/u', '', $out);
    if ($lead !== '') $out = preg_replace('/^[\p{P}\p{S}]+/u', '', $out);
    return $lead . $out . $end . $tail;
}

/** Translate sentence by sentence so each one can match exactly, and keep the punctuation. */
function vs_translate_by_sentence(PDO $pdo, string $text, string $src, string $tgt, bool $approx, bool $scoped): array {
    $chunks = vs_split_sentences($text);
    if (count($chunks) > 25) $chunks = [$text];
    $results = []; $outs = []; $rows = []; $done = 0; $failed = [];
    foreach ($chunks as $n => $chunk) {
        $tok = bn_tokens($chunk);
        if (!$tok && $src !== 'english') { $outs[] = trim($chunk) . (preg_match('/\n\s*$/u', $chunk) ? "\n" : ' '); continue; }
        $r = vs_translate_one($pdo, trim($chunk), $src, $tgt, $approx, $scoped);
        $results[] = $r;
        $t = trim((string)($r['translated_text'] ?? ''));
        if ($t !== '' && $src === 'english') {
            // English word order differs from Bangla, so only the sentence's end mark is kept (a full stop becomes ।)
            $end = preg_match('/([.?!]+)\s*$/u', $chunk, $em) ? $em[1] : '';
            $t = preg_replace('/[.?!।]+$/u', '', $t);
            $outs[] = $t . ($end === '' ? '' : (strpos($end, '.') !== false && $tgt !== 'english' ? '।' : $end)) . (preg_match('/\n\s*$/u', $chunk) ? "\n" : ' ');
            $done++;
        }
        elseif ($t !== '') { $outs[] = vs_punctuate($chunk, $t); $done++; }
        else { $outs[] = vs_punctuate($chunk, implode(' ', $tok)); $failed[] = $n + 1; }
        $ar = $r['approx_rows'] ?? null;
        if (is_array($ar) && count($ar) === count($tok)) { foreach ($ar as $x) $rows[] = $x; }
        else foreach ($tok as $w) $rows[] = ['src' => $w, 'std' => $w, 'tgt' => $w, 'matched' => false];
    }
    if (!$results) return vs_translate_one($pdo, $text, $src, $tgt, $approx, $scoped);
    if (count($results) === 1 && $done === 1) {          // one sentence: same result as before, now with punctuation
        $r = $results[0];
        $r['translated_text'] = trim(implode('', $outs));
        if (($r['romanization'] ?? null) !== null) $r['romanization'] = bn_romanize($r['translated_text']);
        $r['original_text'] = $text;
        return $r;
    }
    $r = $results[0];
    $r['original_text'] = $text;
    if ($done === 0) {                                   // nothing could be translated: keep the old behaviour (no output)
        $r['translated_text'] = '';
        return $r;
    }
    $r['translated_text'] = trim(preg_replace('/[ \t]+/u', ' ', implode('', $outs)));
    $notes = [];
    foreach ($results as $x) foreach (($x['notes'] ?? []) as $nt) $notes[$nt] = true;
    $notes = array_keys($notes);
    if ($failed) $notes[] = 'Sentence ' . implode(', ', $failed) . ' could not be translated and is shown unchanged.';
    $r['notes'] = $notes;
    // overall quality = the weakest sentence
    $order = ['native_speaker_sentence', 'research_sentence_candidate_not_reaudited', 'phrase_or_dictionary', 'approximate_isolated_word_gloss',
              'approximate_word_by_word', 'dictionary_word_level_unverified', 'identity', 'unavailable'];
    $worst = -1;
    foreach ($results as $x) { $k = array_search($x['translation_quality'] ?? 'unavailable', $order, true); $worst = max($worst, $k === false ? count($order) - 1 : $k); }
    $r['translation_quality'] = $failed ? 'approximate_word_by_word' : $order[$worst];
    $r['from_memory'] = $r['matched'] = !$failed && array_reduce($results, static fn($c, $x) => $c && !empty($x['from_memory']), true);
    $r['datasets_used'] = array_values(array_unique(array_merge(...array_map(static fn($x) => $x['datasets_used'] ?? [], $results))));
    $src_ = array_values(array_unique(array_filter(array_map(static fn($x) => $x['sentence_source'] ?? null, $results))));
    $r['sentence_source'] = $src_ ? implode(', ', $src_) : null;
    if (($r['romanization'] ?? null) !== null || $tgt === 'standard') $r['romanization'] = bn_romanize($r['translated_text']);
    $r['approx_rows'] = $scoped ? $rows : ($r['approx_rows'] ?? null);
    return $r;
}

/**
 * Noakhali "will not": the verb takes -ইতান and না becomes ন, after it (native speaker: আমি ভাত খাবো না = আইঁ ভাত খাইতান ন).
 *   খাবো না -> খাইতান ন   যাবো না -> যাইতান ন   করবো না -> কইরতান ন   দেখবো না -> দেইখতান ন
 * Only regular stems are changed (a vowel-ending stem like খা/যা, or consonant-vowel-consonant like কর/দেখ).
 */
function vs_noakhali_negative_future(array $rows, bool $toStandard): array {
    $n = count($rows);
    for ($i = 0; $i + 1 < $n; $i++) {
        if ($toStandard) {
            $w = $rows[$i]['src'] ?? ''; $next = $rows[$i + 1]['src'] ?? '';
            if ($next !== 'ন' || !preg_match('/^(.+)তান$/u', $w, $m)) continue;
            $x = $m[1];
            if (preg_match('/^(.+[া])ই$/u', $x, $q)) $stem = $q[1];                         // খাই -> খা
            elseif (preg_match('/^([\x{0995}-\x{09B9}\x{09DC}\x{09DD}\x{09DF}]\x{09BC}?)ই([\x{0995}-\x{09B9}\x{09DC}\x{09DD}\x{09DF}]\x{09BC}?)$/u', $x, $q)) $stem = $q[1] . $q[2];               // কইর -> কর
            elseif (preg_match('/^([\x{0995}-\x{09B9}\x{09DC}\x{09DD}\x{09DF}]\x{09BC}?)েই([\x{0995}-\x{09B9}\x{09DC}\x{09DD}\x{09DF}]\x{09BC}?)$/u', $x, $q)) $stem = $q[1] . 'ে' . $q[2];       // দেইখ -> দেখ
            else continue;
            $rows[$i]['std'] = $stem . 'বো'; $rows[$i]['matched'] = true; $rows[$i]['rule'] = true;
            $rows[$i + 1]['std'] = 'না'; $rows[$i + 1]['matched'] = true; $rows[$i + 1]['rule'] = true;
        } else {
            $w = $rows[$i]['std'] ?? ''; $next = $rows[$i + 1]['std'] ?? '';
            if ($next !== 'না' || !preg_match('/^(.+?)বো?$/u', $w, $m)) continue;
            $stem = $m[1];
            // prefer the dialect's own verb root (Noakhali কমু -> ক, করমু -> কর) over the Standard one
            $t = $rows[$i]['tgt'] ?? '';
            if (preg_match('/^(.+)মু$/u', $t, $q) || preg_match('/^(.+?)(?:িয়ুম|ইয়ুম|য়ুম|উম)$/u', $t, $q)) $stem = $q[1];
            $C = '[\x{0995}-\x{09B9}\x{09DC}\x{09DD}\x{09DF}]\x{09BC}?';
            if (preg_match('/^' . $C . '(?:ই|েই)' . $C . '$/u', $stem)) { $form = $stem . 'তান'; }   // the dialect root already has the -ই- (কইর, দেইখ)
            else
            if (preg_match('/^([\x{0995}-\x{09B9}\x{09DC}\x{09DD}\x{09DF}]\x{09BC}?)$/u', $stem)) { $form = $stem . 'ইতান'; }                     // ক -> কইতান
            else
            if (preg_match('/^.+[াই]$/u', $stem)) $form = preg_replace('/ই$/u', '', $stem) . 'ইতান';                             // খা -> খাইতান
            elseif (preg_match('/^([\x{0995}-\x{09B9}\x{09DC}\x{09DD}\x{09DF}]\x{09BC}?)([\x{0995}-\x{09B9}\x{09DC}\x{09DD}\x{09DF}]\x{09BC}?)$/u', $stem, $q)) $form = $q[1] . 'ই' . $q[2] . 'তান';   // কর -> কইরতান
            elseif (preg_match('/^([\x{0995}-\x{09B9}\x{09DC}\x{09DD}\x{09DF}]\x{09BC}?)ে([\x{0995}-\x{09B9}\x{09DC}\x{09DD}\x{09DF}]\x{09BC}?)$/u', $stem, $q)) $form = $q[1] . 'েই' . $q[2] . 'তান'; // দেখ -> দেইখতান
            else continue;
            $rows[$i]['tgt'] = $form; $rows[$i]['matched'] = true; $rows[$i]['rule'] = true;
            $rows[$i + 1]['tgt'] = 'ন'; $rows[$i + 1]['matched'] = true; $rows[$i + 1]['rule'] = true;
        }
    }
    return $rows;
}
