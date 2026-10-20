<?php
/** CLI ONLY: import native-speaker-reviewed course content into pair_course_items.
 * Usage: php tools/import_verified_lessons.php database/reviewed/approved_lessons.csv
 * Requires setup.php to have created the V4 tables. Does not import research candidates.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once __DIR__ . '/../api/config.php';
if ($argc !== 2) exit("Usage: php tools/import_verified_lessons.php database/reviewed/approved_lessons.csv\n");
$dir=realpath(__DIR__.'/../database/reviewed');
$file=realpath(__DIR__.'/../'.$argv[1]);
if (!$dir || !$file || !str_starts_with($file,$dir.DIRECTORY_SEPARATOR) || !str_ends_with($file,'.csv')) exit("File must be an existing CSV in database/reviewed/\n");
$pdo=get_db();
$valid=[];
foreach($pdo->query('SELECT language_id FROM language_catalog') as $x)$valid[$x['language_id']]=true;
$f=fopen($file,'r');$columns=fgetcsv($f);
$expected=['source_language','target_language','unit_key','stage','item_order','exercise_type','prompt_native','target_text','ipa','romanization','options_json','correct_answer','gloss_native','audio_path','source_dataset','reviewer','review_status'];
if($columns!==$expected) exit("Header must match verified_pair_lesson_TEMPLATE.csv exactly.\n");
$items=[];$line=1;
while(($v=fgetcsv($f))!==false){$line++;if(count($v)!==count($columns))exit("Incorrect number of fields at line $line\n");$r=array_combine($columns,$v);
  $from=$r['source_language'];$to=$r['target_language'];
  if(!isset($valid[$from],$valid[$to])||$from===$to)exit("Invalid language pair on line $line\n");
  if(!in_array($r['stage'],['sounds','letters','phonetics','words','sentences','conversations'],true) || $r['unit_key']!==$r['stage'])exit("Unit must match one of the six stage names on line $line\n");
  if(!ctype_digit($r['item_order'])||(int)$r['item_order']<1)exit("Bad item_order at line $line\n");
  if($r['review_status']!=='community_verified'||trim($r['reviewer'])===''||trim($r['source_dataset'])==='')exit("Need community_verified status, reviewer and source dataset at line $line\n");
  $opt=json_decode($r['options_json'],true);if(!is_array($opt)||count($opt)<2||count($opt)>8||count($opt)!==count(array_unique($opt))||!in_array($r['correct_answer'],$opt,true))exit("Invalid answer options on line $line\n");
  if(trim($r['prompt_native'])===''||trim($r['target_text'])===''||mb_strlen($r['target_text'])>300||mb_strlen($r['correct_answer'])>300)exit("Missing or long lesson text on line $line\n");
  // Hearing and pronunciation tests must have actual native target recordings.
  if(($r['stage']==='sounds' || str_starts_with($r['exercise_type'],'listen')) && $r['audio_path']==='') exit("Audio required for sounds/listening lesson at line $line\n");
  if($r['audio_path']!==''){
   if(!preg_match('#^assets/audio/[A-Za-z0-9_./-]+\.(mp3|ogg|wav)$#D',$r['audio_path'])||str_contains($r['audio_path'],'..')||!is_file(__DIR__.'/../'.$r['audio_path'])) exit("Missing/unsafe audio path at line $line\n");
  }
  $items[]=$r;
}
fclose($f);
if(!$items)exit("No rows. Nothing was imported.\n");
$st=$pdo->prepare('INSERT INTO pair_course_items (source_language,target_language,unit_key,stage,item_order,exercise_type,prompt_native,target_text,ipa,romanization,options_json,correct_answer,gloss_native,audio_path,source_dataset,reviewer,review_status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE exercise_type=VALUES(exercise_type),prompt_native=VALUES(prompt_native),target_text=VALUES(target_text),ipa=VALUES(ipa),romanization=VALUES(romanization),options_json=VALUES(options_json),correct_answer=VALUES(correct_answer),gloss_native=VALUES(gloss_native),audio_path=VALUES(audio_path),source_dataset=VALUES(source_dataset),reviewer=VALUES(reviewer),review_status=VALUES(review_status)');
$pdo->beginTransaction();
try{foreach($items as $r){$vals=array_values($r);$vals[13]=$vals[13]?:null;$st->execute($vals);}$pdo->commit();}catch(Throwable $e){$pdo->rollBack();throw $e;}
echo count($items)." reviewed exercise rows imported. Restart or refresh learn.php to use them.\n";
