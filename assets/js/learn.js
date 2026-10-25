(() => {
'use strict';
const $=id=>document.getElementById(id);
const K='vashasetu-v4-courses';
const state={languages:[],src:'',tgt:'',units:[],items:[],current:null,step:0,answered:false,guestDone:{},guest:true,previews:[],previewIndex:0};
try {state.guestDone=JSON.parse(localStorage.getItem(K)||'{}')||{};}catch(e){state.guestDone={};}
const ICONS={sounds:'🔊',letters:'অ',phonetics:'🔤',words:'📖',sentences:'💬',conversations:'👥'};
const safeId=x=>typeof x==='string'&&/^[a-z0-9-]{1,60}$/.test(x)?x:'';
const name=id=>state.languages.find(x=>x.language_id===id)?.display_name||id;
const el=(tag,cls,txt)=>{const n=document.createElement(tag);if(cls)n.className=cls;if(txt!==undefined)n.textContent=txt;return n;};
const pairKey=()=>state.src+'__'+state.tgt;
const getDone=u=>state.guest?(state.guestDone[pairKey()]?.[u.unit_key]||[]).length:Number(u.mastered)||0;
const setDone=(u,item)=>{const key=pairKey();state.guestDone[key]??={};let values=new Set(state.guestDone[key][u.unit_key]||[]);values.add(item.item_kind+':'+item.id);state.guestDone[key][u.unit_key]=[...values];try{localStorage.setItem(K,JSON.stringify(state.guestDone))}catch(e){}};
async function api(data={},post=false){const url='api/learn.php'+(post?'':'?'+new URLSearchParams(data));const r=await fetch(url,post?{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)}:{cache:'no-store'});const b=await r.json();if(!r.ok||!b.success)throw new Error(b.error||'Request failed');return b;}
const params=(more={})=>({source:state.src,target:state.tgt,...more});
function showOnly(which){for(const id of ['unitsView','exerciseView','previewView'])$(id).hidden=id!==which;}
function selectionValid(){return !!(state.src&&state.tgt&&state.src!==state.tgt);}
async function init(){try{
 const data=await api({action:'languages'});state.languages=data.languages;
 let savedSrc='',savedTgt='';try{savedSrc=safeId(localStorage.getItem('vashasetu-v4-source')||'');savedTgt=safeId(localStorage.getItem('vashasetu-v4-target')||'');}catch(e){}
 for(const id of ['sourceLang','targetLang']){const s=$(id);s.replaceChildren();for(const l of state.languages){let opt=el('option','',l.display_name+' · '+l.language_type.replaceAll('_',' '));opt.value=l.language_id;s.add(opt);}}
 const initialSource=safeId(new URLSearchParams(location.search).get('source')||'');
 state.src=state.languages.some(x=>x.language_id===savedSrc)?savedSrc:(state.languages.some(x=>x.language_id===initialSource)?initialSource:'noakhali-bn');
 state.tgt=state.languages.some(x=>x.language_id===savedTgt)?savedTgt:'standard';
 if(state.src===state.tgt)state.tgt=state.src==='standard'?'noakhali-bn':'standard';
 $('sourceLang').value=state.src;$('targetLang').value=state.tgt;
 $('sourceLang').onchange=async()=>{state.src=$('sourceLang').value;await selectionChanged()};
 $('targetLang').onchange=async()=>{state.tgt=$('targetLang').value;await selectionChanged()};
 $('swapLang').onclick=async()=>{[state.src,state.tgt]=[state.tgt,state.src];$('sourceLang').value=state.src;$('targetLang').value=state.tgt;await selectionChanged()};
 await selectionChanged();
 }catch(e){$('unitList').textContent='Could not load languages: '+e.message+'. Run setup.php.';}}
async function selectionChanged(){showOnly('unitsView');state.current=null;
 if(!selectionValid()){$('unitList').textContent='Choose two different languages to build your learning path.';$('courseNotice').textContent='Source and target languages must be different.';$('chosenCourse').textContent='';return;}
 try{localStorage.setItem('vashasetu-v4-source',state.src);localStorage.setItem('vashasetu-v4-target',state.tgt);}catch(e){}
 $('chosenCourse').textContent=name(state.src)+' → '+name(state.tgt);
 $('unitList').textContent='Loading this language pair…';
 try{const d=await api(params({action:'course_units'}));state.units=d.units;state.guest=d.guest;
 $('courseNotice').textContent=d.course_status==='script_then_dialect'
 ?'First learn the Bangla script (sounds, letters, phonetics) - '+name(state.tgt)+' is written with the same letters. Then practise '+name(state.tgt)+' words and sentences with the quizzes below.'
 :d.course_status==='starter_core'
 ?'Standard Bangla starter lessons are available. Some prompts remain in English until your chosen teaching language has reviewed translations; the starter lessons themselves still need educator review.'
 :d.course_status==='some_verified_lessons'
 ?'Community-verified lessons are available where marked. Other stages have research previews or await authentic recordings and educator review.'
 :'This pair has no community-verified graded course yet. Where research samples exist, browse ungraded word and sentence flashcards while translations, scripts and recordings are reviewed.';
 renderUnits();
 }catch(e){$('unitList').textContent='Could not load course: '+e.message+'. Run setup.php to add the V4 course tables.';}}
function renderUnits(){const root=$('unitList');root.replaceChildren();if(!state.units.length){root.textContent='No learning stages found.';return;}
 state.units.forEach((u,idx)=>{const total=Number(u.total_items)||0;const done=getDone(u);
 const prev=state.units[idx-1];const sequential=state.tgt==='standard'||u.status==='shared_bangla_script';const locked=sequential&&idx>0&&getDone(prev)<Number(prev.total_items);
 const card=el('div','unit stageCard');const bubble=el('span','bubble',locked?'🔒':(ICONS[u.stage]||'★'));card.append(bubble);
 const title=el('span','stageDetail');title.append(el('strong','',u.title_en),el('small','',u.title_bn+' · '+u.description_en));card.append(title);
 const actions=el('div','stageActions');
 if(total>0){const btn=el('button','stageBtn',locked?'Locked':done===total?'Review ✓':`Start · ${done}/${total}`);btn.type='button';btn.disabled=locked;btn.onclick=()=>openUnit(u);actions.append(btn);}
 else if(!(u.preview_available&&state.tgt!=='standard'))actions.append(el('span','stagePending','No lesson material for this language yet'));
 if(u.preview_available&&state.tgt!=='standard'){
 const qb=el('button','stageBtn','Start quiz');qb.type='button';qb.onclick=()=>openQuiz(u);actions.append(qb);
 const btn=el('button','previewBtn','Research flashcards');btn.type='button';btn.onclick=()=>openPreview(u);actions.append(btn);
 }
 card.append(actions);root.append(card);
 });}
function supportsAudio(){return 'speechSynthesis' in window;}
function speakNote(msg){let n=document.getElementById('speakNote');if(!n){n=el('div','emptyState','');n.id='speakNote';const t=$('listenBtn');t.parentNode.insertBefore(n,t.nextSibling);}n.textContent=msg;}
function speakBangla(text){if(!supportsAudio()){speakNote('This browser cannot play speech. Read the letter and choose the answer.');return;}
 const synth=window.speechSynthesis;
 const go=()=>{const voice=synth.getVoices().find(v=>/^bn([-_]|$)/i.test(v.lang));
  synth.cancel();const u=new SpeechSynthesisUtterance(text);u.lang=voice?voice.lang:'bn-BD';if(voice)u.voice=voice;u.rate=.8;
  let started=false;u.onstart=()=>{started=true;speakNote('');};
  u.onerror=()=>speakNote('This phone has no Bangla voice. Install it: Settings → System → Languages → Text-to-speech output → Google → Install voice data → Bengali. The written exercises still work.');
  synth.speak(u);
  setTimeout(()=>{if(!started&&!voice)speakNote('No Bangla voice found. Install it: Settings → System → Languages → Text-to-speech output → Google → Install voice data → Bengali. The written exercises still work.');},1500);};
 // Chrome loads its voice list asynchronously; wait for it once before choosing a voice.
 if(synth.getVoices().length)go();else{let done=false;const run=()=>{if(done)return;done=true;go();};synth.addEventListener('voiceschanged',run,{once:true});setTimeout(run,800);}
}
async function openUnit(u){state.current=u;state.step=0;try{const d=await api(params({action:'course_items',unit:u.unit_key}));state.items=d.items;if(!state.items.length)throw Error('No reviewed exercise items for this pair');showOnly('exerciseView');renderQuestion();window.scrollTo({top:0,behavior:'smooth'});}catch(e){alert(e.message);}}
function renderQuestion(){const q=state.items[state.step];if(!q){finish();return;}state.answered=false;
 $('exerciseTitle').textContent=state.current.title_en+' · '+name(state.tgt);$('counter').textContent=(state.step+1)+' / '+state.items.length;
 $('progressBar').style.width=(state.step*100/state.items.length)+'%';$('prompt').textContent=q.prompt_native||q.prompt;
 $('soundIpa').textContent=[q.ipa,q.romanization].filter(Boolean).join(' · ');$('symbol').textContent='';
 const audio=$('listenBtn');audio.hidden=true;audio.onclick=null;
 if(q.audio_path&&!q.audio_path.includes('..')&&/^assets\/audio\/[a-zA-Z0-9_\/.\-]+\.(mp3|ogg|wav)$/i.test(q.audio_path)){
  audio.hidden=false;audio.textContent='🔊 Listen to native recording';audio.onclick=()=>new Audio(q.audio_path).play().catch(()=>alert('Recording unavailable'));
 }else if((state.tgt==='standard'||q.item_kind==='core')&&q.speech_text&&supportsAudio()){
  audio.hidden=false;audio.textContent='🔊 Listen to Bangla';audio.onclick=()=>speakBangla(q.speech_text);
 }
 $('sound').style.display='block';$('feedback').textContent='';$('nextBtn').hidden=true;
 const options=[...q.options];for(let i=options.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[options[i],options[j]]=[options[j],options[i]];}
 const ans=$('answers');ans.replaceChildren();for(const choice of options){const b=el('button','answer',choice);b.type='button';b.onclick=()=>choose(q,b,choice);ans.append(b);}
}
async function openQuiz(u){state.current=u;state.step=0;
 try{const d=await api(params({action:'course_preview',stage:u.stage}));const rows=(d.samples||[]).filter(r=>r.target_text&&(r.source_text||r.standard_bangla));
  if(rows.length<4)throw Error('Not enough examples for a quiz yet');
  const mean=r=>r.source_text||r.standard_bangla;
  const pick=rows.slice().sort(()=>Math.random()-.5).slice(0,10);
  state.items=pick.map((r,i)=>{const wrong=rows.filter(x=>mean(x)!==mean(r)).sort(()=>Math.random()-.5).slice(0,3).map(mean);
   return{id:i,local:true,item_kind:'local',prompt:'What does “'+r.target_text+'” mean?',ipa:'',romanization:'',speech_text:'',display_text:r.target_text,correct:mean(r),options:[mean(r),...wrong],gloss_en:'Source: '+(r.source_dataset||'research data')};});
  showOnly('exerciseView');renderQuestion();}catch(e){alert('Quiz unavailable: '+e.message);}}
async function choose(q,btn,value){if(state.answered)return;state.answered=true;
 if(q.local){const ok=value===q.correct;for(const b of $('answers').children){b.disabled=true;if(b.textContent===q.correct)b.classList.add('correct');if(!ok&&b===btn)b.classList.add('wrong');}
  $('symbol').textContent=q.display_text;$('feedback').textContent=(ok?'Correct! ':'Try again. Correct answer: '+q.correct+'. ')+q.gloss_en;$('feedback').style.color=ok?'#21734e':'#b44932';
  const next=$('nextBtn');next.hidden=false;next.textContent=ok?(state.step+1===state.items.length?'Finish ✓':'Continue →'):'Retry ↻';next.onclick=()=>{if(ok)state.step++;renderQuestion()};return;}
 let r;try{r=await api(params({action:'course_answer',item_kind:q.item_kind,item_id:q.id,answer:value}),true);}catch(e){state.answered=false;$('feedback').textContent='Could not check: '+e.message;return;}
 for(const b of $('answers').children){b.disabled=true;if(b.textContent===r.correct_answer)b.classList.add('correct');if(!r.correct&&b===btn)b.classList.add('wrong');}
 $('symbol').textContent=r.display_text||'';$('feedback').textContent=(r.correct?'Correct! ':'Try again. Correct answer: '+r.correct_answer+'. ')+(r.gloss_en||'');
 $('feedback').style.color=r.correct?'#21734e':'#b44932';if(r.correct&&!r.saved)setDone(state.current,q);
 const next=$('nextBtn');next.hidden=false;next.textContent=r.correct?(state.step+1===state.items.length?'Finish ✓':'Continue →'):'Retry ↻';next.onclick=()=>{if(r.correct)state.step++;renderQuestion()};
}
async function finish(){showOnly('unitsView');if(!state.guest){try{const d=await api(params({action:'course_units'}));state.units=d.units;}catch(e){}}renderUnits();$('unitsView').scrollIntoView({behavior:'smooth'});}
async function openPreview(u){state.current=u;state.previews=[];state.previewIndex=0;showOnly('previewView');$('previewTitle').textContent='Research flashcards: '+u.title_en;$('previewWarning').textContent='Loading…';$('previewCards').textContent='Loading examples…';
 try{const d=await api(params({action:'course_preview',stage:u.stage}));state.previews=d.samples;$('previewWarning').textContent=d.warning;renderPreview();window.scrollTo({top:0,behavior:'smooth'});}catch(e){$('previewCards').textContent='Preview unavailable: '+e.message;}}
function renderPreview(){const root=$('previewCards');root.replaceChildren();const rows=state.previews;
 if(!rows.length){root.append(el('p','emptyState','There are no research samples for this target language yet. Authentic material is needed before we can teach this stage.'));$('previewCount').textContent='0 / 0';return;}
 const i=state.previewIndex,row=rows[i];$('previewCount').textContent=(i+1)+' / '+rows.length;
 const card=el('article','card flashcard');card.append(el('small','flashlabel','TARGET · '+name(state.tgt)));
 card.append(el('h3','targetWord',row.target_text));card.append(el('small','referenceLabel',row.source_text?'TEACHING LANGUAGE · '+name(state.src):'STANDARD BANGLA REFERENCE · NOT IN '+name(state.src)));
 card.append(el('p','nativeMeaning',row.source_text||row.standard_bangla));
 if(row.source_text&&row.source_text!==row.standard_bangla)card.append(el('p','minor','Standard Bangla reference: '+row.standard_bangla));
 card.append(el('p','minor','Source: '+row.source_dataset+' · '+row.review_status+' · '+(row.source_fallback?'No matching source-language expression available':'Aligned on a shared Standard Bangla gloss; not independently verified')));
 const nav=el('div','previewNav');const back=el('button','stageBtn','← Previous');back.disabled=i===0;back.onclick=()=>{state.previewIndex--;renderPreview()};
 const next=el('button','stageBtn','Next →');next.disabled=i===rows.length-1;next.onclick=()=>{state.previewIndex++;renderPreview()};nav.append(back,next);card.append(nav);root.append(card);
}
$('goBack').onclick=finish;$('previewBack').onclick=()=>showOnly('unitsView');
init();
})();
