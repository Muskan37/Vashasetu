<?php
/** VashaSetu V4 – source -> target courses with strict pair-specific material and progress.
 * Only community_verified pair items may become graded learning exercises.
 * Research corpora are exposed separately as clearly labeled UNGRADED previews.
 */
require_once __DIR__ . '/config.php';
$pdo = get_db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?: []) : $_GET;
$action = (string) ($input['action'] ?? 'languages');
function respond(array $value, int $code = 200): void {
    http_response_code($code);
    echo json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function safe_id($x): string {
    if (!is_string($x) || !preg_match('/^[a-z0-9-]{1,60}$/D', $x)) respond(['error'=>'Invalid language or unit ID'],400);
    return $x;
}
function course_pair(PDO $pdo, array $input): array {
    $source = safe_id($input['source'] ?? '');
    $target = safe_id($input['target'] ?? '');
    if ($source === $target) respond(['error'=>'Choose two different languages'],400);
    $q = $pdo->prepare('SELECT language_id, display_name, existing_app_key, app_status FROM language_catalog WHERE language_id IN (?,?)');
    $q->execute([$source, $target]);
    $found = [];
    foreach ($q->fetchAll() as $r) $found[$r['language_id']] = $r;
    if (!isset($found[$source],$found[$target])) respond(['error'=>'Unsupported language selection'],400);
    return [$source,$target,$found[$source],$found[$target]];
}
function course_stages(): array {
    return [
      ['unit_key'=>'sounds','stage'=>'sounds','title_en'=>'Sounds','title_bn'=>'ধ্বনি','description_en'=>'Hear and recognize authentic sounds.'],
      ['unit_key'=>'letters','stage'=>'letters','title_en'=>'Letters & script','title_bn'=>'বর্ণ ও লিপি','description_en'=>'Connect the written form with the sound.'],
      ['unit_key'=>'phonetics','stage'=>'phonetics','title_en'=>'Phonetics','title_bn'=>'উচ্চারণ','description_en'=>'Practice syllables and pronunciation.'],
      ['unit_key'=>'words','stage'=>'words','title_en'=>'Words','title_bn'=>'শব্দ','description_en'=>'Learn individual words with their separate meanings.'],
      ['unit_key'=>'sentences','stage'=>'sentences','title_en'=>'Sentences','title_bn'=>'বাক্য','description_en'=>'Learn expressions and context.'],
      ['unit_key'=>'conversations','stage'=>'conversations','title_en'=>'Conversations','title_bn'=>'কথোপকথন','description_en'=>'Practice short dialogues.']
    ];
}
function core_units(PDO $pdo, string $src, string $tgt): array {
    $uid = current_user_id();
    // Course progress is keyed by both the learner's language and target.
    $sql = 'SELECT u.unit_key,u.sort_order,u.stage,u.title_en,u.title_bn,u.description_en,COUNT(i.id) total_items, ' .
           ($uid ? 'COUNT(CASE WHEN p.correct_count>0 THEN 1 END)' : '0') . ' mastered ' .
           'FROM learning_units u LEFT JOIN learning_items i ON i.unit_key=u.unit_key ' .
           ($uid ? 'LEFT JOIN course_progress p ON p.user_id=? AND p.source_language=? AND p.target_language=? AND p.item_kind=? AND p.item_id=i.id ' : '') .
           'GROUP BY u.id ORDER BY u.sort_order';
    $st=$pdo->prepare($sql);
    $st->execute($uid?[$uid,$src,$tgt,'core']:[]);
    $out=$st->fetchAll();
    foreach($out as &$u){$u['status']='starter_review';$u['preview_available']=false;}
    return $out;
}
function other_units(PDO $pdo,string $src,string $tgt): array {
    $uid=current_user_id();
    $sql='SELECT c.unit_key,c.stage,COUNT(*) total_items,' .
         ($uid?'COUNT(CASE WHEN p.correct_count>0 THEN 1 END)':'0').' mastered FROM pair_course_items c ' .
         ($uid?'LEFT JOIN course_progress p ON p.user_id=? AND p.source_language=? AND p.target_language=? AND p.item_kind=? AND p.item_id=c.id ':'') .
         'WHERE c.source_language=? AND c.target_language=? AND c.review_status=? GROUP BY c.unit_key,c.stage';
    $st=$pdo->prepare($sql);
    $st->execute($uid?[$uid,$src,$tgt,'pair',$src,$tgt,'community_verified']:[$src,$tgt,'community_verified']);
    $ready=[];
    foreach ($st->fetchAll() as $r) $ready[$r['unit_key']]=$r;
    $out=[];
    foreach(course_stages() as $idx=>$u){
        $key=$u['unit_key'];$a=$ready[$key]??null;
        $u['sort_order']=$idx+1;
        $u['total_items']=$a?(int)$a['total_items']:0;
        $u['mastered']=$a?(int)$a['mastered']:0;
        $u['status']=$a?'verified_course':'awaiting_verified_lessons';
        $u['preview_available']=in_array($key,['words','sentences'],true);
        $out[]=$u;
    }
    return $out;
}
function unit_items(PDO $pdo,array $input): void {
    [$src,$tgt]=course_pair($pdo,$input);
    $unit=safe_id($input['unit']??'');
    if($tgt==='standard'){
        $st=$pdo->prepare('SELECT i.id,i.item_order,i.exercise_type,i.prompt,i.ipa,i.romanization,i.options_json,i.gloss_en,i.speech_bn,loc.prompt_native,loc.hint_native FROM learning_items i LEFT JOIN learning_localizations loc ON loc.language_id=? AND loc.unit_key=i.unit_key AND loc.item_order=i.item_order AND loc.review_status=? WHERE i.unit_key=? AND i.review_status=? ORDER BY i.item_order');
        $st->execute([$src,'community_verified',$unit,'starter_content_needs_educator_review']);
        $items=$st->fetchAll();
        foreach($items as &$r){$r['options']=json_decode($r['options_json'],true)?:[];unset($r['options_json']);$r['item_kind']='core';$r['speech_text']=$r['speech_bn'];$r['audio_path']=null;}
        respond(['success'=>true,'items'=>$items,'source'=>$src,'target'=>$tgt,'quality'=>'Standard Bangla starter exercises; educator review pending.']);
    }
    $st=$pdo->prepare('SELECT id,item_order,exercise_type,prompt_native AS prompt, target_text, ipa, romanization,options_json,gloss_native AS gloss_en,audio_path,stage FROM pair_course_items WHERE source_language=? AND target_language=? AND unit_key=? AND review_status=? ORDER BY item_order');
    $st->execute([$src,$tgt,$unit,'community_verified']);
    $items=$st->fetchAll();
    foreach($items as &$r){$r['options']=json_decode($r['options_json'],true)?:[];unset($r['options_json']);$r['item_kind']='pair';$r['speech_text']=$r['target_text'];}
    respond(['success'=>true,'items'=>$items,'source'=>$src,'target'=>$tgt,'quality'=>'Community-verified pair-specific lessons']);
}
function judge_answer(PDO $pdo,array $input): void {
    [$src,$tgt]=course_pair($pdo,$input);
    $id=filter_var($input['item_id']??null,FILTER_VALIDATE_INT);
    $kind=$input['item_kind']??null;
    $guess=$input['answer']??null;
    if(!$id||!in_array($kind,['core','pair'],true)||!is_string($guess)||mb_strlen($guess)>300) respond(['error'=>'Invalid answer'],400);
    if($kind==='core' && $tgt==='standard'){
        $st=$pdo->prepare('SELECT correct_answer,options_json,gloss_en,display_bn AS display_text FROM learning_items WHERE id=? AND review_status=?');
        $st->execute([$id,'starter_content_needs_educator_review']);
    }elseif($kind==='pair'&&$tgt!=='standard'){
        $st=$pdo->prepare('SELECT correct_answer,options_json,gloss_native AS gloss_en,target_text AS display_text FROM pair_course_items WHERE id=? AND source_language=? AND target_language=? AND review_status=?');
        $st->execute([$id,$src,$tgt,'community_verified']);
    }else respond(['error'=>'Invalid item for the selected course'],400);
    $r=$st->fetch();if(!$r)respond(['error'=>'Question unavailable for this course'],404);
    $opts=json_decode($r['options_json'],true)?:[];
    if(!in_array($guess,$opts,true))respond(['error'=>'Answer must be one of the displayed options'],400);
    $ok=hash_equals((string)$r['correct_answer'],$guess);
    $uid=current_user_id();
    if($uid){
        $st=$pdo->prepare('INSERT INTO course_progress (user_id,source_language,target_language,item_kind,item_id,attempts,correct_count) VALUES(?,?,?,?,?,1,?) ON DUPLICATE KEY UPDATE attempts=attempts+1,correct_count=correct_count+VALUES(correct_count)');
        $st->execute([$uid,$src,$tgt,$kind,$id,$ok?1:0]);
    }
    respond(['success'=>true,'correct'=>$ok,'correct_answer'=>$r['correct_answer'],'display_text'=>$r['display_text'],'gloss_en'=>$r['gloss_en'],'saved'=>(bool)$uid]);
}
/** Candidates deliberately remain ungraded; only two selected varieties are queried.
 * Reference Standard Bangla is a shared intermediate, NEVER a third regional dataset.
 */
function sample_preview(PDO $pdo,array $input):void{
    [$src,$tgt,$from,$to]=course_pair($pdo,$input);
    $stage=safe_id($input['stage']??'');
    if(!in_array($stage,['words','sentences'],true))respond(['error'=>'Only word and sentence research previews are available'],400);
    if($tgt==='standard')respond(['success'=>true,'samples'=>[],'verified'=>false]);
    $targetKey=$to['existing_app_key']!==''?$to['existing_app_key']:$tgt;
    $sourceKey=$from['existing_app_key']!==''?$from['existing_app_key']:$src;
    $samples=[];
    if($stage==='words'){
        $st=$pdo->prepare('SELECT source_word,standard_word,meaning_status,source_dataset FROM word_meanings WHERE language=? ORDER BY evidence_count DESC,source_word LIMIT 700');
        $st->execute([$targetKey]);$rows=$st->fetchAll();
        $sourceMap=[];
        if($src!=='standard'){
            $q=$pdo->prepare('SELECT standard_word,source_word FROM word_meanings WHERE language=? LIMIT 9000');
            $q->execute([$sourceKey]);
            $multi=[];
            foreach($q->fetchAll() as $r){$multi[$r['standard_word']][$r['source_word']]=true;}
            foreach($multi as $k=>$values)if(count($values)===1)$sourceMap[$k]=array_key_first($values);
        }
        // Prefer pairs which have a separately attested expression in the user's own
        // chosen teaching language; never use a third dialect to fill an empty gloss.
        usort($rows,static function($a,$b)use($sourceMap,$src){
            $ra=$src==='standard'||isset($sourceMap[$a['standard_word']]);
            $rb=$src==='standard'||isset($sourceMap[$b['standard_word']]);
            if($ra!==$rb)return $ra?-1:1;
            $aReviewed=str_starts_with($a['meaning_status'],'publication_reviewed');
            $bReviewed=str_starts_with($b['meaning_status'],'publication_reviewed');
            if($aReviewed!==$bReviewed)return $aReviewed?-1:1;
            return mb_strlen($a['source_word'])<=>mb_strlen($b['source_word']);
        });
        $seen=[];
        foreach($rows as $r){
            $std=$r['standard_word'];$target=$r['source_word'];
            if(isset($seen[$target])||mb_strlen($target)<2||mb_strlen($target)>24)continue;
            $seen[$target]=true;
            $own=$src==='standard'?$std:($sourceMap[$std]??null);
            $samples[]=['target_text'=>$target,'source_text'=>$own,'standard_bangla'=>$std,
                'source_fallback'=>$own===null,'source_dataset'=>$r['source_dataset'],
                'review_status'=>$r['meaning_status'],'script'=>$to['display_name']];
            if(count($samples)>=16)break;
        }
    }else{
        $st=$pdo->prepare("SELECT source_text,standard_bangla,standard_key,review_status,source_dataset,script FROM parallel_sentences WHERE language=? AND split_name='train' AND eligible_exact=1 AND CHAR_LENGTH(source_text) BETWEEN 5 AND 140 ORDER BY CHAR_LENGTH(source_text),pair_hash LIMIT 50");
        $st->execute([$targetKey]);$rows=$st->fetchAll();
        $sourceMap=[];
        if($src!=='standard' && $rows){
            $q=$pdo->prepare("SELECT standard_key,source_text FROM parallel_sentences WHERE language=? AND split_name='train' AND eligible_exact=1 AND standard_key=? LIMIT 20");
            foreach($rows as $r){
                $q->execute([$sourceKey,$r['standard_key']]);$vals=array_unique(array_column($q->fetchAll(),'source_text'));
                if(count($vals)===1)$sourceMap[$r['standard_key']]=$vals[0];
            }
        }
        usort($rows,static function($a,$b)use($sourceMap,$src){
            $av=$src==='standard'||isset($sourceMap[$a['standard_key']]);
            $bv=$src==='standard'||isset($sourceMap[$b['standard_key']]);
            if($av!==$bv)return $av?-1:1;
            return mb_strlen($a['source_text'])<=>mb_strlen($b['source_text']);
        });
        foreach($rows as $r){$own=$src==='standard'?$r['standard_bangla']:($sourceMap[$r['standard_key']]??null);
            $samples[]=['target_text'=>$r['source_text'],'source_text'=>$own,
                'standard_bangla'=>$r['standard_bangla'],'source_fallback'=>$own===null,
                'source_dataset'=>$r['source_dataset'],'review_status'=>$r['review_status'],'script'=>$r['script']];
            if(count($samples)>=16)break;
        }
    }
    respond(['success'=>true,'samples'=>$samples,'verified'=>false,'source'=>$src,'target'=>$tgt,
      'warning'=>'Research candidates have NOT been checked by VashaSetu native-speaker educators. Do not treat these flashcards as verified translations or pronunciation lessons. When the source-language equivalent is unavailable, Standard Bangla is shown as a reference. No third dialect data is used.']);
}
try{
    if($method==='GET' && $action==='languages'){
        respond(['success'=>true,'languages'=>$pdo->query('SELECT language_id,display_name,language_type,scripts,app_status FROM language_catalog ORDER BY language_type,display_name')->fetchAll()]);
    }
    if($method==='GET' && $action==='course_units'){
        [$src,$tgt,$from,$to]=course_pair($pdo,$input);
        $units=$tgt==='standard'?core_units($pdo,$src,$tgt):other_units($pdo,$src,$tgt);
        respond(['success'=>true,'source'=>$src,'target'=>$tgt,'units'=>$units,'guest'=>!current_user_id(),
          'course_status'=>$tgt==='standard'?'starter_core':(array_sum(array_column($units,'total_items'))?'some_verified_lessons':'research_previews_only')]);
    }
    if($method==='GET'&&$action==='course_items')unit_items($pdo,$input);
    if($method==='GET'&&$action==='course_preview')sample_preview($pdo,$input);
    if($method==='POST'&&$action==='course_answer'){require_login();judge_answer($pdo,$input);}
    respond(['error'=>'Unsupported learning endpoint'],405);
}catch(Throwable $e){
    error_log('VashaSetu learning API: '.$e->getMessage());
    respond(['error'=>'Learning data unavailable. Check MySQL and run setup.php to install the V4 tables.'],500);
}
