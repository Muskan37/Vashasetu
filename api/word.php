<?php
// V3 word of the day: attested one-word examples, never raw statistical alignment.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/text.php';
if ($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);echo json_encode(['error'=>'GET only']);exit;}
$dialect=trim($_GET['dialect']??'Chattogram');
if ($dialect==='any' || $dialect==='') $dialect='Chattogram';$mode=($_GET['mode']??'daily')==='random'?'random':'daily';
$n=(int)($_GET['n']??0);$exclude=(int)($_GET['exclude_id']??0);
try {
 $db=get_db();
 $q="SELECT id,source_word AS dialect_word,standard_word AS standard_meaning,language AS dialect_type,meaning_status AS review_status,source_dataset FROM word_meanings WHERE source_word<>standard_word AND meaning_status IN ('publication_reviewed_not_reaudited','existing_curated_needs_recheck','community_verified','native_speaker_reported')";
 if ($dialect!=='' && $dialect!=='any') {$st=$db->prepare($q.' AND language=? ORDER BY id');$st->execute([$dialect]);}
 else {http_response_code(400);echo json_encode(['error'=>'A specific language is required']);exit;}
 $rows=$st->fetchAll();
 if (count($rows)<4){ $sc=showcase_rows($dialect); if ($sc) { foreach($sc as &$x){$x['review_status']='learned_not_verified';$x['source_dataset']='Learned from datasets (not verified)';} unset($x); $rows=array_merge($rows,$sc);} }
 if (!$rows){echo json_encode(['success'=>true,'word'=>null]);exit;}
 if ($mode==='daily'){$pos=(((int)date('Ymd'))*7+$n)%count($rows);$word=$rows[$pos];}
 else {$pool=array_values(array_filter($rows,fn($r)=>(int)$r['id']!==$exclude));if (!$pool)$pool=$rows;$word=$pool[random_int(0,count($pool)-1)];}
 $word['roman']=bn_romanize($word['dialect_word']);
 echo json_encode(['success'=>true,'word'=>$word],JSON_UNESCAPED_UNICODE);
} catch(Throwable $e){http_response_code(500);echo json_encode(['error'=>'Word list unavailable; run setup.php.']);}
