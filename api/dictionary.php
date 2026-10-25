<?php
// V3: dictionary lists one-word attested meanings; statistical word-alignment guesses
// from lexicon no longer appear as if verified.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/matching_engine.php';
require_once __DIR__ . '/word_evidence.php';
$dialect = trim($_GET['dialect'] ?? 'Chattogram');
$dictionaryLanguages = array_merge(DIALECTS, array_keys(VS_NATIVE_LANGUAGES), ['Dhaka','Khulna']);
if (!in_array($dialect, $dictionaryLanguages, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported dictionary language. Please choose an available language.']);
    exit;
}
$q = mb_substr(trim($_GET['q'] ?? ''),0,60);
$page = max(1, (int) ($_GET['page'] ?? 1));
$per=30;
$includeCandidates=true; // show every word; unreviewed ones are only flagged in the tooltip
try {
    $db=get_db();
    $where='language=?';$args=[$dialect]; // strictly selected-language records
    if (!$includeCandidates) $where.=" AND meaning_status IN ('publication_reviewed_not_reaudited','existing_curated_needs_recheck','community_verified','native_speaker_reported')";
    if ($q!=='') {
        $normalized=bn_normalize($q);
        $escaped=str_replace(['|','%','_'],['||','|%','|_'],$normalized);
        $where.=" AND (source_word LIKE ? ESCAPE '|' OR standard_word LIKE ? ESCAPE '|')";
        $args[]=$escaped.'%';$args[]=$escaped.'%';
    }
    $st=$db->prepare("SELECT COUNT(*) FROM word_meanings WHERE $where");$st->execute($args);$total=(int)$st->fetchColumn();
    $st=$db->prepare("SELECT source_word,standard_word,meaning_status,source_dataset,evidence_count FROM word_meanings WHERE $where ORDER BY CASE WHEN meaning_status='community_verified' THEN 0 WHEN meaning_status='existing_curated_needs_recheck' THEN 1 ELSE 2 END, evidence_count DESC, source_word LIMIT $per OFFSET ".(($page-1)*$per));
    $st->execute($args);
    $rows=[];
    foreach ($st->fetchAll() as $r) $rows[]=['dialect_word'=>$r['source_word'],'standard_word'=>$r['standard_word'],'roman'=>bn_romanize($r['source_word']),
      'count'=>(int)$r['evidence_count'],'verified'=>$r['meaning_status']==='community_verified'?1:0,
      'conf'=>null,'review_status'=>$r['meaning_status'],'source_dataset'=>$r['source_dataset']];
    // Nothing reviewed yet for this language: show the confident learned pairs, clearly labelled unverified.
    if ($total===0) {
        $sc=showcase_rows($dialect)?:[];
        if ($q!=='') $sc=array_values(array_filter($sc,fn($x)=>mb_strpos($x['dialect_word'],$q)===0||mb_strpos($x['standard_meaning'],$q)===0));
        $total=count($sc);
        foreach (array_slice($sc,($page-1)*$per,$per) as $x) $rows[]=['dialect_word'=>$x['dialect_word'],'standard_word'=>$x['standard_meaning'],'roman'=>bn_romanize($x['dialect_word']),
          'count'=>0,'verified'=>0,'conf'=>null,'review_status'=>'learned_not_verified','source_dataset'=>'Learned from datasets (not verified)'];
    }
    // Indigenous languages: also list the phrases/sentences (MELD), after the words.
    if (isset(VS_NATIVE_LANGUAGES[$dialect])) {
        $w=[];
        $sql="SELECT source_text,standard_bangla,review_status,source_dataset FROM parallel_sentences WHERE language=?";$pa=[$dialect];
        if ($q!=='') { $sql.=" AND (source_key LIKE ? ESCAPE '|' OR standard_key LIKE ? ESCAPE '|')"; $pa[]=$escaped.'%'; $pa[]=$escaped.'%'; }
        $st=$db->prepare($sql." ORDER BY CHAR_LENGTH(source_key), source_key");$st->execute($pa);
        $phr=$st->fetchAll();
        $wordCount=$total; $total=$wordCount+count($phr);
        $off=($page-1)*$per;
        if ($off>=$wordCount) $rows=[]; // word page already handled by LIMIT/OFFSET above
        if (count($rows)<$per) {
            $need=$per-count($rows); $start=max(0,$off-$wordCount);
            foreach (array_slice($phr,$start,$need) as $x) $rows[]=['dialect_word'=>$x['source_text'],'standard_word'=>$x['standard_bangla'],'roman'=>bn_romanize($x['source_text']),
              'count'=>1,'verified'=>0,'conf'=>null,'review_status'=>$x['review_status'],'source_dataset'=>$x['source_dataset'],'phrase'=>1];
        }
    }
    $totals=[];
    $st=$db->query("SELECT language,COUNT(*) n FROM word_meanings WHERE meaning_status IN ('publication_reviewed_not_reaudited','existing_curated_needs_recheck','community_verified','native_speaker_reported') GROUP BY language");
    foreach ($st->fetchAll() as $r) $totals[$r['language']]=(int)$r['n'];
    echo json_encode(['success'=>true,'dialect'=>$dialect,'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/$per)), 'rows'=>$rows,'dialect_totals'=>$totals,
        'note'=>'Research-reviewed is not independent native-speaker verification. Add include_candidates=1 to inspect unreviewed items.'],JSON_UNESCAPED_UNICODE);
} catch(Throwable $e){http_response_code(500);echo json_encode(['error'=>'Dictionary unavailable. Run setup.php to load V3 tables.']);}
