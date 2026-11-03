(() => {
'use strict';
const $=id=>document.getElementById(id);
const K='vashasetu-v4-courses';
const state={languages:[],src:'',tgt:'',units:[],items:[],current:null,step:0,answered:false,guestDone:{},guest:true,previews:[],previewIndex:0};
try {state.guestDone=JSON.parse(localStorage.getItem(K)||'{}')||{};}catch(e){state.guestDone={};}
const ICONS={sounds:'🔊',letters:'অ',phonetics:'🔤',words:'📖',sentences:'💬',conversations:'👥'};
// Instruction text in the learner's own language ("I speak"), served by api/learn.php?action=ui from database/learn_ui.csv.
// Bangla varieties get Standard Bangla; other languages get their reviewed text, otherwise pictures and symbols (never English).
const UI={mode:'bn',strings:{},names:{}};
const T=(k,v={})=>String(UI.strings[k]??'').replace(/\{(\w)\}/g,(m,x)=>v[x]??'');
// Pictures for the meaning of the starter words and sentences, so a learner who cannot read yet still knows what is asked.
const PIC={'mother':'👩','father':'👨','brother':'👦','sister':'👧','name':'🪪','book':'📖','water':'💧','cooked rice':'🍚','home/room':'🏠','hand':'✋','flower':'🌸','tea':'🍵',
 'I am well.':'🙂👍','This is my book.':'👉📖','My name is Robi.':'🙋 🪪','This is water.':'👉💧','I eat rice.':'🙋🍚','How are you?':'👋🙂❓','What is your name?':'👉🪪❓','I am learning Bangla.':'🙋📚 অ আ','Thank you.':'🙏','I want water.':'🙋💧'};
const hasLatin=t=>/[A-Za-z]/.test(t||'');
// the feedback line after an answer: translated note, or the symbols-only part, never English
const glossText=g=>{if(!g)return'';const t=UI.strings['g_'+g];if(t)return t;if(PIC[g])return PIC[g];return hasLatin(g)?'':g;};
async function loadUi(lang){try{const r=await fetch('api/learn.php?action=ui&lang='+encodeURIComponent(lang),{cache:'no-store'});const b=await r.json();if(b.success){UI.mode=b.mode;UI.strings=b.strings;UI.names=b.names||{};}}catch(e){}
 document.documentElement.lang=UI.mode==='bn'?'bn':(lang||'bn');
 for(const n of document.querySelectorAll('[data-t]')){const v=T(n.dataset.t);n.textContent=v;n.hidden=v==='';}
 for(const n of document.querySelectorAll('[data-t-label]')){const v=T(n.dataset.tLabel);n.setAttribute('aria-label',v);n.title=v;}
 for(const id of ['sourceLang','targetLang'])for(const o of $(id).options)o.textContent=name(o.value);
 const pk=$('pairPicker');$('changePair').textContent=pk.hidden?T('change'):T('done');}
const safeId=x=>typeof x==='string'&&/^[a-z0-9-]{1,60}$/.test(x)?x:'';
const name=id=>UI.names[id]||state.languages.find(x=>x.language_id===id)?.display_name||id;
const el=(tag,cls,txt)=>{const n=document.createElement(tag);if(cls)n.className=cls;if(txt!==undefined)n.textContent=txt;return n;};
const pairKey=()=>state.src+'__'+state.tgt;
const getDone=u=>state.guest?(state.guestDone[pairKey()]?.[u.unit_key]||[]).length:Number(u.mastered)||0;
const setDone=(u,item)=>{const key=pairKey();state.guestDone[key]??={};let values=new Set(state.guestDone[key][u.unit_key]||[]);values.add(item.item_kind+':'+item.id);state.guestDone[key][u.unit_key]=[...values];try{localStorage.setItem(K,JSON.stringify(state.guestDone))}catch(e){}};
async function api(data={},post=false){const url='api/learn.php'+(post?'':'?'+new URLSearchParams(data));const r=await fetch(url,post?{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)}:{cache:'no-store'});const b=await r.json();if(!r.ok||!b.success)throw new Error(b.error||'Request failed');return b;}
const params=(more={})=>({source:state.src,target:state.tgt,...more});
// Problems are shown as a line on the page instead of a pop-up box that blocks the phone screen.
function showMsg(msg){showOnly('unitsView');let n=$('learnMsg');if(!n){n=el('p','notice','');n.id='learnMsg';n.setAttribute('role','status');$('unitList').before(n);}n.textContent=msg;n.scrollIntoView({behavior:'smooth',block:'center'});}
function showOnly(which){for(const id of ['unitsView','exerciseView','previewView'])$(id).hidden=id!==which;}
function selectionValid(){return !!(state.src&&state.tgt&&state.src!==state.tgt);}
async function init(){try{
 const data=await api({action:'languages'});state.languages=data.languages;
 let savedSrc='',savedTgt='';try{savedSrc=safeId(localStorage.getItem('vashasetu-v4-source')||'');savedTgt=safeId(localStorage.getItem('vashasetu-v4-target')||'');}catch(e){}
 for(const id of ['sourceLang','targetLang']){const s=$(id);s.replaceChildren();for(const l of state.languages){let opt=el('option','',name(l.language_id));opt.value=l.language_id;s.add(opt);}}
 const initialSource=safeId(new URLSearchParams(location.search).get('source')||'');
 state.src=state.languages.some(x=>x.language_id===savedSrc)?savedSrc:(state.languages.some(x=>x.language_id===initialSource)?initialSource:'noakhali-bn');
 state.tgt=state.languages.some(x=>x.language_id===savedTgt)?savedTgt:'standard';
 if(state.src===state.tgt)state.tgt=state.src==='standard'?'noakhali-bn':'standard';
 $('sourceLang').value=state.src;$('targetLang').value=state.tgt;
 $('sourceLang').onchange=async()=>{state.src=$('sourceLang').value;await selectionChanged()};
 $('targetLang').onchange=async()=>{state.tgt=$('targetLang').value;await selectionChanged()};
 $('changePair').onclick=()=>{const pk=$('pairPicker');pk.hidden=!pk.hidden;$('changePair').textContent=pk.hidden?T('change'):T('done');$('changePair').setAttribute('aria-expanded',String(!pk.hidden));};
 $('swapLang').onclick=async()=>{[state.src,state.tgt]=[state.tgt,state.src];$('sourceLang').value=state.src;$('targetLang').value=state.tgt;await selectionChanged()};
 await selectionChanged();
 }catch(e){if(!UI.strings.err_languages)await loadUi('standard');$('unitList').textContent=T('err_languages');}}
async function selectionChanged(){await loadUi(state.src);showOnly('unitsView');const lm=$('learnMsg');if(lm)lm.remove();state.current=null;
 if(!selectionValid()){$('unitList').textContent=T('choose_two');$('courseNotice').textContent=T('must_differ');$('courseNotice').hidden=!T('must_differ');$('chosenCourse').textContent='';return;}
 try{localStorage.setItem('vashasetu-v4-source',state.src);localStorage.setItem('vashasetu-v4-target',state.tgt);}catch(e){}
 $('chosenCourse').textContent=name(state.src)+' → '+name(state.tgt);
 $('unitList').textContent=T('loading');
 try{const d=await api(params({action:'course_units'}));state.units=d.units;state.guest=d.guest;
 const nt=T(d.course_status==='script_then_dialect'?'notice_script':d.course_status==='starter_core'?'notice_starter':d.course_status==='some_verified_lessons'?'notice_some':'notice_none',{t:name(state.tgt)});
 $('courseNotice').textContent=nt;$('courseNotice').hidden=!nt;
 renderUnits();
 }catch(e){$('unitList').textContent=T('err_course');}}
function renderUnits(){const root=$('unitList');root.replaceChildren();if(!state.units.length){root.textContent=T('no_stages');return;}
 state.units.forEach((u,idx)=>{const total=Number(u.total_items)||0;const done=getDone(u);
 const prev=state.units[idx-1];const sequential=state.tgt==='standard'||u.status==='shared_bangla_script';const locked=sequential&&idx>0&&getDone(prev)<Number(prev.total_items);
 const card=el('div','unit stageCard');const bubble=el('span','bubble',locked?'🔒':(ICONS[u.stage]||'★'));card.append(bubble);
 const title=el('span','stageDetail');const desc=T('d_'+u.unit_key)||T('d_'+u.stage);title.append(el('strong','',u.title_bn||''));if(desc)title.append(el('small','',desc));card.append(title);
 const actions=el('div','stageActions');
 if(total>0){const btn=el('button','stageBtn',locked?T('locked'):done===total?T('review'):T('start',{d:done,t:total}));btn.type='button';btn.disabled=locked;btn.onclick=()=>openUnit(u);actions.append(btn);}
 else if(!(u.preview_available&&state.tgt!=='standard'))actions.append(el('span','stagePending',T('no_material')));
 if(u.preview_available&&state.tgt!=='standard'){
 const qb=el('button','stageBtn',T('start_quiz'));qb.type='button';qb.onclick=()=>openQuiz(u);actions.append(qb);
 const btn=el('button','previewBtn',T('flashcards'));btn.type='button';btn.onclick=()=>openPreview(u);actions.append(btn);
 }
 card.append(actions);root.append(card);
 });}
function supportsAudio(){return true;}
function speakNote(msg){let n=document.getElementById('speakNote');if(!msg){if(n)n.remove();return;}if(!n){n=el('div','emptyState','');n.id='speakNote';const t=$('listenBtn');t.parentNode.insertBefore(n,t.nextSibling);}n.textContent=msg;}
let lessonAudio=null;
// Sound made by the app's server (api/tts.php) - works on every phone, no voice needs to be installed.
function playServerVoice(text){
 try{if(lessonAudio){lessonAudio.pause();lessonAudio=null;}}catch(e){}
 const a=new Audio('api/tts.php?lang=bn&q='+encodeURIComponent(text));lessonAudio=a;
 const fail=()=>speakNote(T('sound_fail'));
 a.onplaying=()=>speakNote('');a.onerror=fail;const pr=a.play();if(pr&&pr.catch)pr.catch(fail);
}
function speakBangla(text){
 const synth=window.speechSynthesis;
 if(!synth){playServerVoice(text);return;}
 const go=()=>{const voice=synth.getVoices().find(v=>/^bn([-_]|$)/i.test(v.lang));
  if(!voice){playServerVoice(text);return;}            // no Bangla voice on this phone: use the server voice
  synth.cancel();const u=new SpeechSynthesisUtterance(text);u.lang=voice.lang;u.voice=voice;u.rate=.8;
  let started=false;u.onstart=()=>{started=true;speakNote('');};
  u.onerror=(e)=>{if(e&&(e.error==='canceled'||e.error==='interrupted'))return;if(!started)playServerVoice(text);};
  synth.speak(u);
  setTimeout(()=>{if(!started&&!synth.speaking)playServerVoice(text);},1500);};
 // Chrome loads its voice list asynchronously; wait for it once before choosing a voice.
 if(synth.getVoices().length)go();else{let done=false;const run=()=>{if(done)return;done=true;go();};synth.addEventListener('voiceschanged',run,{once:true});setTimeout(run,800);}
}
async function openUnit(u){state.current=u;state.step=0;try{const d=await api(params({action:'course_items',unit:u.unit_key}));state.items=d.items;if(!state.items.length)throw Error('none');showOnly('exerciseView');renderQuestion();window.scrollTo({top:0,behavior:'smooth'});}catch(e){showMsg(T('lesson_cannot_open'));}}
function questionText(q){
 if(q.local)return q.prompt;
 const u=q.unit_key||state.current?.unit_key||'';
 if(q.exercise_type==='word_choice')return T('p_words');
 if(q.exercise_type==='sentence_choice')return T('p_sentences');
 if(q.exercise_type==='letter_choice')return T('p_letters');
 return T('p_'+u)||(hasLatin(q.prompt)?'🔊 → ?':q.prompt);
}
function renderQuestion(){const q=state.items[state.step];if(!q){finish();return;}state.answered=false;
 $('exerciseTitle').textContent=(ICONS[state.current.stage]||'')+' '+(state.current.title_bn||'')+' · '+name(state.tgt);$('counter').textContent=(state.step+1)+' / '+state.items.length;
 $('progressBar').style.width=(state.step*100/state.items.length)+'%';$('prompt').textContent=q.prompt_native||questionText(q);
 const ipaLine=[q.ipa,q.romanization].filter(Boolean).join(' · ');
 // a word or sentence question is answered from the picture: its spelling in Latin letters would give the answer away
 $('soundIpa').textContent=['word_choice','sentence_choice'].includes(q.exercise_type)?'':ipaLine;
 // words and sentences: show the meaning as a picture and, when a native speaker confirmed it, the learner's own word
 const pic=q.local?'':(PIC[q.gloss_en]||'');$('symbol').textContent=pic;
 const hint=$('nativeHint');hint.textContent=q.native_hint||'';hint.hidden=!q.native_hint;
 const audio=$('listenBtn');audio.hidden=true;audio.onclick=null;
 if(q.audio_path&&!q.audio_path.includes('..')&&/^assets\/audio\/[a-zA-Z0-9_\/.\-]+\.(mp3|ogg|wav)$/i.test(q.audio_path)){
  audio.hidden=false;audio.textContent=T('listen_native');audio.onclick=()=>new Audio(q.audio_path).play().catch(()=>speakNote(T('rec_unavailable')));
 }else if((state.tgt==='standard'||q.item_kind==='core')&&q.speech_text&&supportsAudio()){
  audio.hidden=false;audio.textContent=T('listen_bangla');audio.onclick=()=>speakBangla(q.speech_text);
 }
 $('sound').style.display='block';$('feedback').textContent='';$('nextBtn').hidden=true;
 const options=[...q.options];for(let i=options.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[options[i],options[j]]=[options[j],options[i]];}
 const ans=$('answers');ans.replaceChildren();for(const choice of options){const b=el('button','answer',choice);b.type='button';b.onclick=()=>choose(q,b,choice);ans.append(b);}
}
async function openQuiz(u){state.current=u;state.step=0;
 try{const d=await api(params({action:'course_preview',stage:u.stage}));const rows=(d.samples||[]).filter(r=>r.target_text&&(r.source_text||r.standard_bangla));
  if(rows.length<4)throw Error('few');
  const mean=r=>r.source_text||r.standard_bangla;
  const pick=rows.slice().sort(()=>Math.random()-.5).slice(0,10);
  state.items=pick.map((r,i)=>{const wrong=rows.filter(x=>mean(x)!==mean(r)).sort(()=>Math.random()-.5).slice(0,3).map(mean);
   return{id:i,local:true,item_kind:'local',prompt:T('quiz_prompt',{w:r.target_text}),ipa:'',romanization:'',speech_text:'',display_text:r.target_text,correct:mean(r),options:[mean(r),...wrong],gloss_en:T('source_line',{s:r.source_dataset||'—'})};});
  showOnly('exerciseView');renderQuestion();}catch(e){showMsg(T('quiz_unavailable'));}}
async function choose(q,btn,value){if(state.answered)return;state.answered=true;
 if(q.local){const ok=value===q.correct;for(const b of $('answers').children){b.disabled=true;if(b.textContent===q.correct)b.classList.add('correct');if(!ok&&b===btn)b.classList.add('wrong');}
  $('symbol').textContent=q.display_text;$('feedback').textContent=[ok?T('correct'):T('try_again',{a:q.correct}),q.gloss_en].filter(Boolean).join(' ');$('feedback').style.color=ok?'#21734e':'#b44932';
  const next=$('nextBtn');next.hidden=false;next.textContent=ok?(state.step+1===state.items.length?T('finish'):T('continue')):T('retry');next.onclick=()=>{if(ok)state.step++;renderQuestion()};return;}
 let r;try{r=await api(params({action:'course_answer',item_kind:q.item_kind,item_id:q.id,answer:value}),true);}catch(e){state.answered=false;$('feedback').textContent=T('could_not_check');return;}
 for(const b of $('answers').children){b.disabled=true;if(b.textContent===r.correct_answer)b.classList.add('correct');if(!r.correct&&b===btn)b.classList.add('wrong');}
 $('symbol').textContent=r.display_text||'';$('soundIpa').textContent=[q.ipa,q.romanization].filter(Boolean).join(' · ');$('feedback').textContent=[r.correct?T('correct'):T('try_again',{a:r.correct_answer}),glossText(r.gloss_en)].filter(Boolean).join(' ');
 $('feedback').style.color=r.correct?'#21734e':'#b44932';if(r.correct&&!r.saved)setDone(state.current,q);
 const next=$('nextBtn');next.hidden=false;next.textContent=r.correct?(state.step+1===state.items.length?T('finish'):T('continue')):T('retry');next.onclick=()=>{if(r.correct)state.step++;renderQuestion()};
}
async function finish(){showOnly('unitsView');if(!state.guest){try{const d=await api(params({action:'course_units'}));state.units=d.units;}catch(e){}}renderUnits();$('unitsView').scrollIntoView({behavior:'smooth'});}
async function openPreview(u){state.current=u;state.previews=[];state.previewIndex=0;showOnly('previewView');$('previewTitle').textContent=T('flash_title',{u:u.title_bn||''});$('previewWarning').textContent=T('loading');$('previewCards').textContent=T('loading');
 try{const d=await api(params({action:'course_preview',stage:u.stage}));state.previews=d.samples;$('previewWarning').textContent=T('flash_warning');renderPreview();window.scrollTo({top:0,behavior:'smooth'});}catch(e){$('previewCards').textContent=T('preview_unavailable');}}
function renderPreview(){const root=$('previewCards');root.replaceChildren();const rows=state.previews;
 if(!rows.length){root.append(el('p','emptyState',T('no_samples')));$('previewCount').textContent='0 / 0';return;}
 const i=state.previewIndex,row=rows[i];$('previewCount').textContent=(i+1)+' / '+rows.length;
 const card=el('article','card flashcard');card.append(el('small','flashlabel',T('fl_target',{l:name(state.tgt)})));
 card.append(el('h3','targetWord',row.target_text));card.append(el('small','referenceLabel',row.source_text?T('fl_teaching',{l:name(state.src)}):T('fl_std_ref',{l:name(state.src)})));
 card.append(el('p','nativeMeaning',row.source_text||row.standard_bangla));
 if(row.source_text&&row.source_text!==row.standard_bangla)card.append(el('p','minor',T('fl_std_line',{w:row.standard_bangla})));
 {const src=T('source_line',{s:row.source_dataset});const nt=T(row.source_fallback?'fl_note_fallback':'fl_note_aligned');const line=[src,nt].filter(Boolean).join(' · ');if(line)card.append(el('p','minor',line));}
 const nav=el('div','previewNav');const back=el('button','stageBtn',T('prev'));back.disabled=i===0;back.onclick=()=>{state.previewIndex--;renderPreview()};
 const next=el('button','stageBtn',T('next'));next.disabled=i===rows.length-1;next.onclick=()=>{state.previewIndex++;renderPreview()};nav.append(back,next);card.append(nav);root.append(card);
}
$('goBack').onclick=finish;$('previewBack').onclick=()=>showOnly('unitsView');
init();
})();
