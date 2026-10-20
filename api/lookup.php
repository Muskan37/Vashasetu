<?php
// V3 dictionary card. Source-attributed meanings only, no positional or raw statistical guesses.
require_once __DIR__.'/config.php';
require_once __DIR__.'/matching_engine.php';
require_once __DIR__.'/word_evidence.php';
$word=trim($_GET['word']??'');
$language=trim($_GET['language']??'Chattogram');
if (!in_array($language,array_merge(DIALECTS,array_keys(VS_NATIVE_LANGUAGES)),true)) { http_response_code(400); echo json_encode(['error'=>'Choose one supported dictionary language']);exit; }
if($word===''){http_response_code(400);echo json_encode(['error'=>'word required']);exit;}
$word=mb_substr(preg_split('/\s+/u',$word)[0],0,40);
try {
 $db=get_db();$bn=bn_normalize($word);$english=null;$alts=[];
 if(script_stats($bn)['bangla']===0 && !isset(VS_NATIVE_LANGUAGES[$language])){
  $lw=mb_strtolower($bn);$eng=english_to_bangla($db,[$lw])[0];
  $b=banglish_word($db,$lw);
  if(!empty($eng['matched']) && !($b['best']!==null && $b['rel']<=.1 && $b['freq']>5)){$bn=bn_normalize($eng['std']);$english=$lw;$bn=preg_split('/\s+/u',$bn)[0];}
  elseif($b['best']!==null && empty($b['guess']) && $b['rel']<=.2){$bn=$b['best'];$alts=$b['alts'];}
  else{echo json_encode(['success'=>true,'found'=>false,'word'=>$word],JSON_UNESCAPED_UNICODE);exit;}
 }
 $st=$db->prepare('SELECT language, standard_word, meaning_status, source_dataset FROM word_meanings WHERE source_word=? AND language=? ORDER BY FIELD(meaning_status,\'community_verified\',\'existing_curated_needs_recheck\',\'publication_reviewed_not_reaudited\',\'needs_native_review\') LIMIT 80');
 $st->execute([$bn,$language]);$means=[];
 foreach($st->fetchAll() as $r){
  $means[]=['dialect'=>$r['language'],'standard'=>$r['standard_word'],'conf'=>null,'verified'=>($r['meaning_status']==='community_verified')?1:0,'status'=>$r['meaning_status'],'dataset'=>$r['source_dataset']];
 }
 $st=$db->prepare('SELECT language,source_word,meaning_status,source_dataset FROM word_meanings WHERE standard_word=? AND language=? LIMIT 100');$st->execute([$bn,$language]);$dialects=[];
 foreach($st->fetchAll() as $r){$dialects[]=['dialect'=>$r['language'],'word'=>$r['source_word'],'roman'=>bn_romanize($r['source_word']), 'conf'=>null,'status'=>$r['meaning_status'],'dataset'=>$r['source_dataset']];}
 $ex=[];
 $st=$db->prepare('SELECT language,source_text,standard_bangla,source_dataset,review_status FROM parallel_sentences WHERE language=? AND (source_text LIKE ? OR standard_bangla LIKE ?) AND eligible_exact=1 LIMIT 6');
 $pat='%'.str_replace(['|','%','_'],['||','|%','|_'],$bn).'%';$st->execute([$language,$pat,$pat]);
 foreach($st->fetchAll() as $r){$ex[]=['variety'=>$r['language'],'label'=>$r['language'],'text'=>$r['source_text'],'standard'=>$r['standard_bangla'],'source'=>$r['source_dataset'],'status'=>$r['review_status']];}
 $gloss=bangla_to_english($db,[$bn])[0];
 echo json_encode(['success'=>true,'found'=>true,'language'=>$language,'word'=>$bn,'typed'=>$word,'english_input'=>$english,'alternatives'=>$alts,'roman'=>bn_romanize($bn),
  'english'=>!empty($gloss['matched'])?$gloss['tgt']:null,'means'=>$means,'in_dialects'=>$dialects,'examples'=>$ex,
  'note'=>'Not all candidate mappings are verified. Meaning status and original dataset are shown for review.'],JSON_UNESCAPED_UNICODE);
} catch(Throwable $e){http_response_code(500);echo json_encode(['error'=>'Dictionary lookup unavailable. Run setup.php to install V3 tables.']);}
