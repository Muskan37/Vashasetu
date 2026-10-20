<?php
// V3: dictionary lists one-word attested meanings; statistical word-alignment guesses
// from lexicon no longer appear as if verified.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/matching_engine.php';
$dialect = trim($_GET['dialect'] ?? 'Chattogram');
if (!in_array($dialect, DIALECTS, true)) $dialect = 'Chattogram';
$q = mb_substr(trim($_GET['q'] ?? ''),0,60);
$page = max(1, (int) ($_GET['page'] ?? 1));
$per=30;
$includeCandidates=!empty($_GET['include_candidates']);
try {
    $db=get_db();
    $where='language=? AND source_word <> standard_word';$args=[$dialect];
    if (!$includeCandidates) $where.=" AND meaning_status IN ('publication_reviewed_not_reaudited','existing_curated_needs_recheck','community_verified')";
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
    $totals=[];
    $st=$db->query("SELECT language,COUNT(*) n FROM word_meanings WHERE meaning_status IN ('publication_reviewed_not_reaudited','existing_curated_needs_recheck','community_verified') AND source_word <> standard_word GROUP BY language");
    foreach ($st->fetchAll() as $r) $totals[$r['language']]=(int)$r['n'];
    echo json_encode(['success'=>true,'dialect'=>$dialect,'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/$per)), 'rows'=>$rows,'dialect_totals'=>$totals,
        'note'=>'Research-reviewed is not independent native-speaker verification. Add include_candidates=1 to inspect unreviewed items.'],JSON_UNESCAPED_UNICODE);
} catch(Throwable $e){http_response_code(500);echo json_encode(['error'=>'Dictionary unavailable. Run setup.php to load V3 tables.']);}
