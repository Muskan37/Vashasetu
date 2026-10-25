/* ============================================================
   Vashasetu — app logic
   ============================================================ */
(() => {
'use strict';

const API = 'api';
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/* Dialects. `on` = the dictionary has words for it today. Others show as "coming soon". */
const DIALECTS = {
  Chattogram: { bn: 'চট্টগ্রাম',  c: '#E2556B', on: true,  lab: { dx: -11, dy: 4,   a: 'end' } },
  Sylheti:    { bn: 'সিলেট',      c: '#2F6FE0', on: true,  lab: { dx: 0,   dy: -12, a: 'middle' } },
  Noakhali:   { bn: 'নোয়াখালী',   c: '#E8960F', on: true,  lab: { dx: 0,   dy: -12, a: 'middle' } },
  Barisal:    { bn: 'বরিশাল',     c: '#8B5CF6', on: true,  lab: { dx: 0,   dy: 20,  a: 'middle' } },
  Dhaka:      { bn: 'ঢাকা',       c: '#8A9A98', on: false, lab: { dx: -11, dy: 8,   a: 'end' } },
  Mymensingh: { bn: 'ময়মনসিংহ',  c: '#0E9F8E', on: true,  lab: { dx: 11,  dy: 4,   a: 'start' } },
  Rajshahi:   { bn: 'রাজশাহী',    c: '#C2410C', on: true,  lab: { dx: 11,  dy: 4,   a: 'start' } },
  Rangpur:    { bn: 'রংপুর',      c: '#4D7C0F', on: true,  lab: { dx: 11,  dy: 4,   a: 'start' } },
  Khulna:     { bn: 'খুলনা',      c: '#8A9A98', on: false, lab: { dx: -11, dy: 4,   a: 'end' } },
  Narail:     { bn: 'নড়াইল',      c: '#B45309', on: true,  lab: { dx: 11,  dy: 4,   a: 'start' } },
  Kishoreganj:{ bn: 'কিশোরগঞ্জ',  c: '#0369A1', on: true,  lab: { dx: 11,  dy: 4,   a: 'start' } },
  Narsingdi:  { bn: 'নরসিংদী',    c: '#9D174D', on: true,  lab: { dx: 11,  dy: -6,  a: 'start' } },
  Tangail:    { bn: 'টাঙ্গাইল',     c: '#4338CA', on: true,  lab: { dx: 11,  dy: 4,   a: 'start' } },
};
const SUPPORTED = Object.keys(DIALECTS).filter((k) => DIALECTS[k].on);
const fromApiName = (k) => (k === 'Barishal' ? 'Barisal' : k);          // map uses "Barisal", the dictionary uses "Barishal"
const toApiName = (k) => (k === 'Barisal' ? 'Barishal' : k);
const colorOf = (d) => { const k = fromApiName(String(d || '').split(/[\s→]/)[0]); return (DIALECTS[k] && DIALECTS[k].c) || '#8A9A98'; };

/* Languages the translator understands. `from` also has Detect + Banglish. */
const LANGS = [
  { id: 'auto',       name: 'Detect language', sub: 'Guesses dialect, Banglish or English', from: true },
  { id: 'standard',   name: 'Standard Bangla', bn: 'প্রমিত বাংলা', from: true, to: true },
  { id: 'Chattogram', name: 'Chattogram',  bn: 'চট্টগ্রাম',  from: true, to: true, c: '#E2556B' },
  { id: 'Sylheti',    name: 'Sylheti',     bn: 'সিলেট',      from: true, to: true, c: '#2F6FE0' },
  { id: 'Noakhali',   name: 'Noakhali',    bn: 'নোয়াখালী',   from: true, to: true, c: '#E8960F' },
  { id: 'Barishal',   name: 'Barishal',    bn: 'বরিশাল',     from: true, to: true, c: '#8B5CF6' },
  { id: 'Mymensingh', name: 'Mymensingh',  bn: 'ময়মনসিংহ',  from: true, to: true, c: '#0E9F8E' },
  { id: 'Rajshahi',   name: 'Rajshahi',    bn: 'রাজশাহী',    from: true, to: true, c: '#C2410C' },
  { id: 'Rangpur',    name: 'Rangpur',     bn: 'রংপুর',      sub: 'Smaller dataset (Dwadash)', from: true, to: true, c: '#4D7C0F' },
  { id: 'Narail',     name: 'Narail',      bn: 'নড়াইল',      sub: 'Small dataset (Dwadash)', from: true, to: true, c: '#B45309' },
  { id: 'Kishoreganj',name: 'Kishoreganj', bn: 'কিশোরগঞ্জ',  sub: 'Small dataset (Dwadash)', from: true, to: true, c: '#0369A1' },
  { id: 'Narsingdi',  name: 'Narsingdi',   bn: 'নরসিংদী',    sub: 'Small dataset (Dwadash)', from: true, to: true, c: '#9D174D' },
  { id: 'Tangail',    name: 'Tangail',     bn: 'টাঙ্গাইল',     sub: 'Small dataset (Dwadash)', from: true, to: true, c: '#4338CA' },
  { id: 'chakma',     name: 'Chakma',     bn: 'চাকমা',     sub: 'Research phrase matches only · Bengali transliteration', from: true, to: true, c:'#488A7B' },
  { id: 'marma',      name: 'Marma',      bn: 'মারমা',      sub: 'Research phrase matches only · Bengali transliteration', from: true, to: true, c:'#A56C42' },
  { id: 'garo',       name: 'Garo / A.chik', bn: 'গারো',    sub: 'Research phrase matches only · Bengali transliteration', from: true, to: true, c:'#8067B1' },
  { id: 'banglish',   name: 'Banglish',    sub: 'Bangla typed in English letters', from: true },
  { id: 'english',    name: 'English',     sub: 'Word-by-word from a dictionary', from: true, to: true },
];
const langName = (id) => (LANGS.find((l) => l.id === id) || { name: id }).name;
/* Dictionary language state is intentionally independent of translator source/target. */
const ADDITIONAL_DICTIONARY_LANGS = [
  { id:'Dhaka', name:'Dhaka · dataset pending' }, { id:'Khulna', name:'Khulna · dataset pending' },
  { id:'tripura', name:'Tripura / Kokborok' }, { id:'sadri', name:'Sadri' },
  { id:'santali', name:'Santali' }, { id:'khasi', name:'Khasi' },
  { id:'mro', name:'Mro' }, { id:'tanchangya', name:'Tanchangya' },
  { id:'bawm', name:'Bawm' }, { id:'khumi', name:'Khumi' },
  { id:'rakhine', name:'Rakhine' }, { id:'meitei', name:'Meitei' },
];
const DICTIONARY_LANGS = LANGS.filter(l=>l.to && !['standard','english'].includes(l.id)).concat(ADDITIONAL_DICTIONARY_LANGS);
const DICT_IDS = new Set(DICTIONARY_LANGS.map(l=>l.id));

const S = { user: null, dialect: 'Chattogram', from: 'auto', to: (function(){try { const t=JSON.parse(localStorage.getItem("vs_translate_target")); return LANGS.some(l=>l.id===t&&l.to)?t:"standard"; } catch(e){ return "standard"; } })(), last: null, breakdown: [], result: '', wod: null, history: [], mapSel: null, mapBuilt: false };

/* ---------- small helpers ---------- */
const store = {
  get(k, d) { try { const v = localStorage.getItem(k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
  set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* private mode */ } },
};
/* Bangladesh's 64 districts are administrative locations, not language/ethnicity labels. */
let DISTRICTS = [];
let PROFILE_LANGUAGES = [];
let afterLoginDestination = null;
function selectedDistrict() { return S.user ? (S.user.current_district || '') : store.get('vs_guest_district',''); }
function districtById(id) { return DISTRICTS.find(d=>d.id===id); }
function districtSelectOptions(value) {
  const divisions = [...new Set(DISTRICTS.map(d=>d.division))];
  let html = '<option value="">Choose district (optional)</option>';
  for (const div of divisions) {
    html += '<optgroup label="'+esc(div)+' Division">';
    for (const d of DISTRICTS.filter(d=>d.division===div)) html += '<option value="'+esc(d.id)+'"'+(d.id===value?' selected':'')+'>'+esc(d.name)+' · '+esc(d.name_bn)+'</option>';
    html += '</optgroup>';
  }
  return html;
}
function spokenSelectOptions(value) {
  let html='<option value="">Choose a language (optional)</option>';
  for (const l of PROFILE_LANGUAGES) html += '<option value="'+esc(l.id)+'"'+(l.id===value?' selected':'')+'>'+esc(l.name)+'</option>';
  return html;
}
function districtHint(id) {
  const district=districtById(id);
  if (!district) return 'Your district is not your native language. Choose your spoken language separately.';
  const candidates=(district.suggested_languages||[]).map(k=>PROFILE_LANGUAGES.find(l=>l.id===k)?.name).filter(Boolean);
  return '<b>'+esc(district.name)+' · '+esc(district.division)+' Division</b><div class="district-candidates">'+candidates.map(x=>'<span>'+esc(x)+'</span>').join('')+'</div><small>These are possible languages in or near the area, not verified district-wide assignments. You choose what you speak or learn.</small>';
}
function refreshDistrictUI() {
  const d=selectedDistrict();
  if ($('#home-district')) $('#home-district').innerHTML=districtSelectOptions(d);
  if ($('#home-district-hint')) $('#home-district-hint').innerHTML=districtHint(d);
  if ($('#register-district')) {
    const selected=$('#register-district').value;
    $('#register-district').innerHTML=districtSelectOptions(selected);
  }
  if ($('#register-spoken')) {
    const selected=$('#register-spoken').value;
    $('#register-spoken').innerHTML=spokenSelectOptions(selected);
  }
  if ($('#register-district-hint')) $('#register-district-hint').innerHTML=districtHint($('#register-district')?.value||'');
  if (document.body.dataset.screen==='profile') loadProfile();
}
async function loadDistricts() {
  try {
    const res=await api('districts.php');
    if (!res.success||!Array.isArray(res.districts)||res.districts.length!==64) throw Error('District list unavailable');
    DISTRICTS=res.districts; PROFILE_LANGUAGES=res.languages||[];
    refreshDistrictUI();
  } catch(e) { if ($('#home-district-hint')) $('#home-district-hint').textContent='Could not load districts. Check application files.'; }
}
function openLearning() {
  const frame=$('#learn-frame');
  if (!frame||!S.user) return;
  // Shared same-origin PHP session: scored course progress belongs to the logged-in user.
  const src=S.user.spoken_language || '';
  const url='learn.php?embedded=1'+(src?'&source='+encodeURIComponent(src):'');
  if (frame.dataset.account!==String(S.user.id)) {
    frame.src=url;
    frame.dataset.account=String(S.user.id);
  }
}
const vib = (p = 12) => { try { navigator.vibrate && navigator.vibrate(p); } catch (e) { /* ignore */ } };
let toastTimer = null;
function toast(msg, ms = 2600) {
  const t = $('#toast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), ms);
}
function confetti(n = 36) {
  const box = $('#confetti');
  const colors = ['#F2A93B', '#E2556B', '#2F6FE0', '#3FB26B', '#1F9186'];
  for (let i = 0; i < n; i++) {
    const p = document.createElement('i');
    p.style.left = Math.random() * 100 + '%';
    p.style.background = colors[i % colors.length];
    p.style.setProperty('--dx', (Math.random() * 220 - 110) + 'px');
    p.style.setProperty('--rot', (Math.random() * 720 - 360) + 'deg');
    p.style.animationDelay = Math.random() * 0.25 + 's';
    p.style.animationDuration = (1.1 + Math.random() * 0.9) + 's';
    box.appendChild(p);
    setTimeout(() => p.remove(), 2600);
  }
}
async function api(path, opts) {
  const res = await fetch(`${API}/${path}`, opts);
  let data = {};
  try { data = await res.json(); } catch (e) { /* non-JSON */ }
  return data;
}
const post = (path, body, extra) => api(path, Object.assign({ method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }, extra || {}));
const icon = (id, size) => `<svg class="ic"${size ? ` style="width:${size}px;height:${size}px"` : ''}><use href="#i-${id}"/></svg>`;

/* ---------- clock / greeting / streak ---------- */
function tickClock() {
  const d = new Date();
  $('#clock').textContent = `${d.getHours() % 12 || 12}:${String(d.getMinutes()).padStart(2, '0')}`;
}
function renderGreeting() {
  const h = new Date().getHours();
  const g = h < 5 ? 'শুভ রাত্রি' : h < 12 ? 'শুভ সকাল' : h < 15 ? 'শুভ দুপুর' : h < 18 ? 'শুভ বিকেল' : h < 20 ? 'শুভ সন্ধ্যা' : 'শুভ রাত্রি';
  $('#greet-bn').textContent = g;
  $('#greet-name').textContent = S.user ? S.user.name.trim().split(/\s+/)[0] : 'Welcome';
}
function touchStreak() {
  const today = new Date().toLocaleDateString('en-CA');
  const y = new Date(Date.now() - 864e5).toLocaleDateString('en-CA');
  const s = store.get('vs_streak', { last: '', count: 0 });
  if (s.last !== today) {
    s.count = s.last === y ? s.count + 1 : 1;
    s.last = today;
    store.set('vs_streak', s);
  }
  $('#streak-n').textContent = s.count;
  return s.count;
}

/* ============================================================
   ROUTER
   ============================================================ */
const SCREENS = ['splash', 'choice', 'home', 'translate', 'breakdown', 'history', 'contribute', 'profile', 'login', 'register', 'quiz', 'dictionary', 'learn'];
const NAV_ON = new Set(['home', 'translate', 'breakdown', 'history', 'contribute', 'profile', 'dictionary', 'learn']);
const TAB_OF = { breakdown: 'translate', contribute: 'dictionary' };

function goTo(name) {
  if (!SCREENS.includes(name)) return;
  if (name === 'learn' && !S.user) { afterLoginDestination = 'learn'; name = 'login'; toast('Log in or create an account to save your learning progress.'); }
  if (name === 'choice' && S.user) name = 'home';      // already signed in: no need to ask again
  $$('.screen').forEach((s) => s.classList.toggle('active', s.id === 'screen-' + name));
  document.body.dataset.screen = name;
  document.body.dataset.nav = NAV_ON.has(name) ? '1' : '0';
  const tab = TAB_OF[name] || name;
  $$('#nav button').forEach((b) => b.classList.toggle('active', b.dataset.tab === tab));
  const sc = $(`#screen-${name} .scroll`);
  if (sc) sc.scrollTop = 0;
  const hooks = { home: onHome, translate: onTranslate, breakdown: renderBridge, history: loadHistory, profile: loadProfile, quiz: startQuiz, dictionary: onDict, learn: openLearning };
  if (hooks[name]) hooks[name]();
}
window.goTo = goTo;

/* ============================================================
   HOME  (map, word of the day, play)
   ============================================================ */
function onHome() {
  renderGreeting();
  refreshDistrictUI();
  if (!S.mapBuilt) buildMap();
  selectPin(S.dialect, true);
  if (!S.wod) loadWod();
  const best = store.get('vs_best', null);
  $('#play-sub').textContent = best === null ? 'Test what you know — keep your streak alive' : `Your best: ${best}/10 — can you beat it?`;
}

function buildMap() {
  const { w, h } = BD_VIEW;
  let s = `<svg class="map-svg" viewBox="0 0 ${w} ${h}" role="img" aria-label="Map of Bangladesh. Tap a region to hear its dialect.">
    <defs><linearGradient id="sea" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#E2F2F7"/><stop offset="1" stop-color="#B9DCEB"/></linearGradient></defs>
    <rect width="${w}" height="${h}" fill="url(#sea)"/>
    <path d="${BD_NEIGHBORS}" fill="#F1EADA" stroke="#DDD1B6" stroke-width=".8" fill-rule="evenodd"/>
    <path d="${BD_COUNTRY}" fill="#C4E6D7" stroke="#0F5C56" stroke-width="1.5" stroke-linejoin="round" fill-rule="evenodd"/>
    <text x="${w / 2}" y="${h - 12}" text-anchor="middle" font-size="11" font-style="italic" fill="#6FA3BA" font-family="Hind Siliguri, sans-serif">Bay of Bengal · বঙ্গোপসাগর</text>`;
  Object.keys(DIALECTS).forEach((k) => {
    const d = DIALECTS[k], p = BD_PINS[k];
    if (!p) return;
    s += `<g class="pin ${d.on ? '' : 'off'}" data-d="${k}" transform="translate(${p[0]} ${p[1]})" tabindex="0" role="button" aria-label="${k} dialect">
      <circle class="hit" r="17" fill="transparent"/>
      <circle class="halo" r="7" fill="${d.c}" opacity=".5"/>
      ${d.on ? '' : '<circle r="10" fill="none" stroke="#9AA9A6" stroke-width="1.2" stroke-dasharray="3 3"/>'}
      <circle class="ring" r="13" fill="none" stroke="#F2A93B" stroke-width="3"/>
      <circle class="core" r="${d.on ? 6.5 : 5}" fill="${d.c}" stroke="#fff" stroke-width="2.2"/>
      <text x="${d.lab.dx}" y="${d.lab.dy}" text-anchor="${d.lab.a}">${d.bn}</text></g>`;
  });
  s += '</svg>';
  $('#map-home').innerHTML = s;
  S.mapBuilt = true;
}

let lastSampleId = 0;
function selectPin(k, quiet) {
  if (!DIALECTS[k]) return;
  S.mapSel = k;
  $$('#map-home .pin').forEach((p) => p.classList.toggle('sel', p.dataset.d === k));
  if (DIALECTS[k].on && !quiet) setDialect(k);
  renderBubble(k);
  if (!quiet) vib(10);
}

function renderBubble(k) {
  const d = DIALECTS[k], box = $('#bubble');
  const title = `<b>${esc(k)} <span class="bn" style="font-weight:600;color:var(--ink-soft);font-size:15px">${d.bn}</span></b>`;
  if (d.on) {
    box.innerHTML = `<div class="bubble-head">${title}<span class="chip"><span class="dot" style="--c:${d.c}"></span>In the dictionary</span></div>
      <div id="sample-slot"><div class="bubble-note">Loading a word…</div></div>
      <div class="bubble-actions"><button class="btn btn-teal btn-sm" data-act="translateIn" data-d="${k}">Translate in ${esc(k)}</button><button class="link" data-act="anotherSample" data-d="${k}">Another word ↻</button></div>`;
    loadSample(k);
  } else {
    box.innerHTML = `<div class="bubble-head">${title}<span class="chip" style="background:var(--gold-100);color:#7A5410">Coming soon</span></div>
      <p class="bubble-note">Vashasetu doesn’t know any ${esc(k)} words yet. If you speak it, you could be the first to teach it!</p>
      <div class="bubble-actions"><button class="btn btn-gold btn-sm" data-act="contribFor" data-d="${k}">Add a ${esc(k)} word</button></div>`;
  }
}

async function loadSample(k) {
  const slot = $('#sample-slot');
  if (!slot) return;
  try {
    const data = await api(`word.php?dialect=${encodeURIComponent(k)}&mode=random&exclude_id=${lastSampleId}`);
    if (S.mapSel !== k) return;
    if (!data.word) { slot.innerHTML = '<div class="bubble-note">No words here yet.</div>'; return; }
    lastSampleId = data.word.id;
    slot.innerHTML = `<div class="sample"><span class="a">${esc(data.word.dialect_word)}</span><span class="arrow">${icon('arrow', 20)}</span><span class="b">${esc(data.word.standard_meaning)}</span></div>`;
  } catch (e) {
    slot.innerHTML = '<div class="bubble-note">Could not load a word. Check your connection.</div>';
  }
}

async function loadWod(randomExcludeId) {
  try {
    const lang = ['auto','english','standard','banglish'].includes(S.from) ? toApiName(S.dialect) : S.from;
    const q = (randomExcludeId ? `mode=random&exclude_id=${randomExcludeId}` : 'mode=daily') + '&dialect=' + encodeURIComponent(lang);
    const data = await api(`word.php?${q}`);
    S.wod = data.word || null;
  } catch (e) { S.wod = null; }
  renderWod();
}
function renderWod() {
  const card = $('#wod-card'), mini = $('#tr-wod-text'), w = S.wod;
  if (!w) {
    card.innerHTML = '<div class="wod-empty">No word to show yet.</div>';
    mini.textContent = 'Tap to see today’s word';
    return;
  }
  card.innerHTML = `<span class="chip"><span class="dot" style="--c:${colorOf(w.dialect_type)}"></span>${esc(w.dialect_type)}</span>
    <div class="wod-word">${esc(w.dialect_word)}</div>
    <div class="wod-mean">${icon('arrow', 20)} ${esc(w.standard_meaning)}<small style="display:block;font-size:11px;opacity:.7">Research candidate · speaker review needed</small></div>
    <div class="wod-actions"><button class="btn btn-teal btn-sm" data-act="tryWod">Try it</button><button class="btn btn-line btn-sm" data-act="nextWod">Another ↻</button></div>`;
  mini.textContent = `${w.dialect_word} → ${w.standard_meaning}`;
}

/* ============================================================
   TRANSLATE
   ============================================================ */
function setDialect(k) {            // used by the map, profile preference, "word of the day"
  const api = toApiName(k);
  if (!LANGS.some((l) => l.id === api && l.to)) return;
  S.dialect = fromApiName(api);
  // Region map/profile changes only the regional preference; never silently changes translation direction.
  renderLangBar();
}
function renderLangBar() {
  const f = $('#from-label'), t = $('#to-label');
  if (!f) return;
  let ftxt = S.from === 'auto' ? 'Detect automatically' : langName(S.from);
  let fsmall = S.from === 'auto' ? 'Input · Auto detect' : 'Input · Manual';
  if (S.from === 'auto' && S.last && S.last.detected && !S.last.requires_source_confirmation) {
    ftxt = S.last.detected.label; fsmall = 'Input · Detected';
  }
  $('#from-btn small').textContent = fsmall;
  $('#to-btn small').textContent = 'Output · Selected';
  f.textContent = ftxt;
  t.textContent = langName(S.to);
  $('#result-label').textContent = S.to === 'standard' ? 'Standard Bangla · প্রমিত বাংলা' : langName(S.to);
}
function onTranslate() {
  renderLangBar();
  if (!S.wod) loadWod(); else renderWod();
}
function setInput(text) {
  const el = $('#dialect-input');
  el.value = text;
  $('#clear-btn').classList.toggle('show', !!text);
  scheduleLive();
}

/* ---------- sheets (language picker, word card) ---------- */
function openSheet(html) {
  $('#sheet-body').innerHTML = html;
  const sh = $('#sheet'); sh.classList.add('open'); sh.setAttribute('aria-hidden', 'false');
  $('.sheet-panel', sh).scrollTop = 0;
}
function closeSheet() { const sh = $('#sheet'); sh.classList.remove('open'); sh.setAttribute('aria-hidden', 'true'); }
function pickLang(side) {
  const rows = LANGS.filter((l) => l[side === 'from' ? 'from' : 'to']);
  const cur = side === 'from' ? S.from : S.to;
  openSheet(`<h3>${side === 'from' ? 'Input language · automatic or manual' : 'Output language · your choice'}</h3>` + rows.map((l) =>
    `<button class="lang-row ${l.id === cur ? 'on' : ''}" data-act="chooseLang" data-side="${side}" data-id="${l.id}">
      <span class="dot" style="--c:${l.c || '#8A9A98'}"></span><span>${esc(l.name)}${l.bn ? ` <span class="bn" style="font-weight:600;color:var(--ink-soft);font-size:15px">${l.bn}</span>` : ''}${l.sub ? `<small>${esc(l.sub)}</small>` : ''}</span>
      <svg class="ic tick" style="width:20px;height:20px"><use href="#i-check"/></svg></button>`).join(''));
}
function chooseLang(side, id) {
  if (side === 'from') S.from = id;
  else { S.to = id; store.set('vs_translate_target', id); }
  // Never change the dictionary language when changing translation direction.
  S.last = null;
  closeSheet(); renderLangBar(); renderDetect(null);
  if ($('#dialect-input').value.trim()) doTranslate({ live: true });
  vib(8);
}
function swapLang() {
  if ((S.from === 'auto' || S.from === 'banglish') && (!S.last || S.last.requires_source_confirmation || !S.last.source)) {
    toast('Detect or manually choose an input language before swapping.');
    pickLang('from'); return;
  }
  let newTo = S.from;
  if (S.from === 'auto' || S.from === 'banglish') newTo = (S.last && S.last.detected && S.last.detected.variety !== 'english') ? S.last.detected.variety : 'standard';
  let newFrom = S.to;
  if (!LANGS.find((l) => l.id === newTo && l.to)) newTo = 'standard';
  if (newFrom === newTo) {
    // e.g. Detect→Standard with Standard Bangla typed: "swap" should mean Standard → the person's dialect
    if (newFrom === 'standard') newTo = toApiName(S.dialect) !== 'standard' ? toApiName(S.dialect) : 'Chattogram';
    else newFrom = 'auto';
  }
  S.from = newFrom; S.to = newTo; store.set('vs_translate_target', newTo);
  const res = (S.result || '').trim();
  if (res && res !== $('#dialect-input').value.trim() && !(S.last && S.last.detected && S.last.detected.variety === S.last.target)) setInput(res);
  S.last = null;
  const b = $('.swap-btn'); b.classList.toggle('flip'); vib(10);
  renderLangBar(); renderDetect(null);
  if ($('#dialect-input').value.trim()) doTranslate({ live: true });
}

/* ---------- translate (live as you type; the button also saves to history) ---------- */
let txSeq = 0, liveTimer = null, liveCtl = null;
const txCache = new Map();                          // recent results, so repeated text is instant
function renderDetect(data) {
  const el = $('#detect-line'), tl = $('#translit');
  if (!data) { el.innerHTML = ''; tl.classList.remove('show'); tl.innerHTML = ''; return; }
  const d = data.detected;
  let h = '';
  if (data.requires_source_confirmation) {
    const candidates = (data.source_candidates || []).filter(c=>LANGS.some(l=>l.id===c.id&&l.from));
    h += `<div class="vs-source-warning"><b>Input dialect not certain</b><p>${esc(data.detection_reason || 'The words may belong to more than one region. Choose the input language to prevent wrong meanings.')}</p><button class="btn btn-gold btn-sm" data-act="pickLang" data-side="from">Choose input language</button>` +
      (candidates.length ? `<div class="vs-source-suggestions">${candidates.slice(0,6).map(c=>`<button class="chip" data-act="chooseLang" data-side="from" data-id="${esc(c.id)}">${esc(langName(c.id))}</button>`).join('')}</div>` : '') + `</div>`;
    el.innerHTML = h; tl.classList.remove('show'); tl.innerHTML = ''; return;
  }
  if (d.was_auto || d.script === 'banglish') {
    const pct = Math.round((d.confidence || 0) * 100);
    const weak = d.script !== 'english' && d.method !== 'single_dataset_phrase_match' && pct < 55;
    h += `<span class="chip ${weak ? 'warn' : ''}"><svg class="ic" style="width:14px;height:14px"><use href="#i-eye"/></svg> ${d.script === 'banglish' ? 'Banglish · ' : ''}${esc(d.label)}</span>`;
    if (d.script !== 'english') h += `<span class="conf">${d.method === 'single_dataset_phrase_match' ? 'Exact source-labeled phrase · research data, not independently verified' : `Classifier score ${pct}% · not a certainty measure${weak ? ' · short text can be ambiguous' : ''}`}</span>`;
  }
  if (d.variety === data.target && d.script !== 'english' && data.target === 'standard') {
    h += `<div class="samehint">Already Standard Bangla — convert it to: ` + ['Chattogram','Sylheti','Noakhali','Barishal','Mymensingh','Rajshahi','Rangpur','Narail','Kishoreganj','Narsingdi','Tangail'].map((k) => `<button class="chip" data-act="chooseLang" data-side="to" data-id="${k}">${k}</button>`).join(' ') + '</div>';
  }
  el.innerHTML = h;
  if (data.bangla_input) {
    const toks = (data.transliteration || []);
    tl.innerHTML = `<small>Understood as Bangla${toks.some((t) => t.alts && t.alts.length) ? ' · tap a word to try another spelling' : ''}</small><b>` +
      toks.map((t, i) => `<button class="tw" data-act="cycleAlt" data-i="${i}">${esc(t.bn)}</button>`).join(' ') + '</b>';
    tl.classList.add('show');
  } else { tl.classList.remove('show'); tl.innerHTML = ''; }
}

async function doTranslate(opts = {}) {
  const live = !!(opts && opts.live);
  const text = $('#dialect-input').value.trim();
  const btn = $('#translate-btn');
  if (!text) {
    if (!live) { toast('Type, speak, or scan something first'); vib([20, 30, 20]); }
    $('#result-card').classList.remove('show'); renderDetect(null);
    return;
  }
  const seq = ++txSeq;
  const original = btn.innerHTML;
  if (!live) { btn.disabled = true; btn.innerHTML = '<span class="spin"></span> Crossing the bridge…'; }
  try {
    const doLog = !live && text !== lastLogged;
    const approx = !!$('#allow-approximate')?.checked;
    const ckey = S.from + '|' + S.to + '|' + (approx ? 'approx|' : 'strict|') + text;
    let data = !doLog && txCache.get(ckey);
    if (!data) {
      if (liveCtl) { try { liveCtl.abort(); } catch (e) { /* ignore */ } }
      liveCtl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
      data = await post('translate.php', { text, source: S.from, target: S.to, log: doLog, allow_approximate: approx }, liveCtl ? { signal: liveCtl.signal } : null);
      if (data && !data.error && data.translated_text !== undefined) { txCache.set(ckey, data); if (txCache.size > 60) txCache.delete(txCache.keys().next().value); }
    }
    if (seq !== txSeq) return;                       // a newer request replaced this one
    if (data.error) { if (!live) toast(data.error); return; }
    S.last = data;
    S.breakdown = data.breakdown || [];
    S.result = data.translated_text;
    if (doLog && data.translated_text) lastLogged = text;
    renderLangBar(); renderDetect(data); renderResult(data);
    if (!live) { vib(15); setTimeout(() => $('#result-card').scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 120); }
  } catch (e) {
    if (e && e.name === 'AbortError') return;        // replaced by a newer request
    if (!live) toast('Could not reach the server. Check your connection.');
  } finally {
    if (!live) { btn.disabled = false; btn.innerHTML = original; }
  }
}
let lastLogged = '';
function scheduleLive() {
  clearTimeout(liveTimer);
  const text = $('#dialect-input').value.trim();
  if (!text) { txSeq++; $('#result-card').classList.remove('show'); renderDetect(null); S.result = ''; return; }
  liveTimer = setTimeout(() => doTranslate({ live: true }), 600);
}
function logCurrent() {                              // save to history when the person finishes typing (leaves the box)
  const text = $('#dialect-input').value.trim();
  if (text && text !== lastLogged && S.result) { lastLogged = text; post('translate.php', { text, source: S.from, target: S.to, log: true, allow_approximate: !!$('#allow-approximate')?.checked }).catch(() => {}); }
}

/* One chip per source token. Sentence translations NEVER imply positional word alignment. */
function renderResult(data) {
  const card = $('#result-card');
  $('#result-text').textContent = data.requires_source_confirmation ? 'Choose the input dialect to translate accurately.' : (data.translated_text || 'Translation not available for this sentence.');
  $('#result-roman').textContent = data.romanization && data.target !== 'english' ? data.romanization : '';
  const words = data.word_meanings || [];
  $('#result-pairs').innerHTML = words.map((w, i) => {
    const meaning = data.target !== 'standard' && data.source === 'standard' ? w.target_word : w.standard_meaning;
    const has = meaning != null && meaning !== '';
    const label = has ? esc(meaning) : 'No meaning found';
    const status = w.status || 'not_verified';
    const suffix = (w.english_hint && data.target !== 'english') ? `<span class="via">${esc(w.english_hint)}</span>` : '';
    const tip = (w.dataset || 'No independently validated word-level evidence');
    return `<div class="pair ${has ? '' : 'miss'}" style="--i:${i}" data-act="wordEvidence" data-idx="${i}" role="button" tabindex="0" title="${esc(tip)}">
      <small>${esc(w.word)}</small><b>${label}</b>${suffix}
      <span class="vs-evidence-status">${status === 'standard_bangla_identity' ? 'Standard Bangla' : status === 'native_speaker_reported' ? 'Native speaker' : status === 'learned' ? 'Learned' : status === 'not_verified' ? 'No match' : status === 'dictionary_reference' ? 'Dictionary' : 'Source attested · review needed'}</span>
    </div>`;
  }).join('');
  const unknown = words.filter((w) => !w.known).length;
  const note = $('#no-match-note');
  note.textContent = `${unknown} word${unknown===1?'':'s'} not found in the dictionary yet. The rest of the translation is shown above.`;
  note.classList.toggle('show', unknown > 0);
  const quality = data.requires_source_confirmation ? 'Automatic detection was not confident enough. No translation or word meaning was guessed.' :
    data.translation_quality === 'native_speaker_sentence' ? 'Exact sentence from a native speaker\'s list.' :
    data.translation_quality === 'research_sentence_candidate_not_reaudited' || data.translation_quality === 'research_candidate_not_reaudited'
    ? 'Exact sentence match from a research dataset; not independently checked for this app.'
    : ['unavailable','unavailable_without_approximation'].includes(data.translation_quality) ? 'No source-supported sentence translation is available. The original text is not returned as a translation.'
    : 'Word-level translation may be unreliable; verify with a fluent speaker.';
  $('#result-notes').innerHTML = `<p>ⓘ Selected datasets: ${esc((data.datasets_used || []).join(' → ') || data.dataset_scope || 'not available')}</p><p>ⓘ ${esc(quality)}</p>` + (data.notes || []).map((n) => `<p>ⓘ ${esc(n)}</p>`).join('');
  card.classList.remove('show'); void card.offsetWidth; card.classList.add('show');
}
function showWordEvidence(index) {
  const w = S.last && S.last.word_meanings && S.last.word_meanings[index];
  if (!w) return;
  const meaning = w.standard_meaning ? `<div class="wc-row"><span>Standard Bangla meaning</span><b>${esc(w.standard_meaning)}</b></div>` : `<p>Individual word meaning has not been reliably established.</p>${w.candidate_meaning?`<p><b>Unreviewed candidate only:</b> ${esc(w.candidate_meaning)} (do not rely on this until checked with a speaker)</p>`:''}`;
  openSheet(`<h3>${esc(w.word)}</h3>${meaning}${w.english_hint?`<div class="wc-row"><span>English hint</span><b>${esc(w.english_hint)}</b></div>`:''}
    <p style="color:var(--ink-soft)"><b>Evidence:</b> ${esc(w.status.replace(/_/g,' '))}</p><p style="color:var(--ink-soft)"><b>Source:</b> ${esc(w.dataset || 'No reviewed source')}</p>
    <p style="color:var(--ink-soft)">Words in a translated sentence can move, change form, combine or be omitted. This definition is never guessed from word position.</p>`);
}

/* ---------- word card ---------- */
async function lookupWord(w, sourceLanguage = null) {
  openSheet('<div class="empty"><b>Looking up…</b></div>');
  try {
    const lang = sourceLanguage && DICT_IDS.has(sourceLanguage) ? sourceLanguage : D.dialect;
    const d = await api('lookup.php?language=' + encodeURIComponent(lang) + '&word=' + encodeURIComponent(w));
    if (!d.found) { openSheet(`<h3>${esc(w)}</h3><p style="color:var(--ink-soft)">Not in the dictionary yet.</p><button class="btn btn-gold btn-block" data-act="teachWord" data-w="${esc(w)}">Teach it to Vashasetu</button>`); return; }
    const means = (d.means || []).map((m) => `<div class="wc-row"><span class="dot" style="--c:${colorOf(m.dialect)}"></span><span><small>${esc(m.dialect)} word</small><br>means</span><span class="r"><b>${esc(m.standard)}</b>${m.verified ? '<br><small>✓ community verified</small>' : `<br><small>${esc((m.status || 'needs_review').replace(/_/g,' '))}</small>`}</span></div>`).join('');
    const ins = (d.in_dialects || []).map((m) => `<div class="wc-row"><span class="dot" style="--c:${colorOf(m.dialect)}"></span><span><small>In ${esc(m.dialect)}</small></span><span class="r"><b>${esc(m.word)}</b><br><small>${esc(m.roman)}</small><br><small>${esc((m.status||'needs_review').replace(/_/g,' '))}</small></span></div>`).join('');
    const ex = (d.examples || []).map((x) => `<div class="wc-ex"><small>${esc(x.label)}</small><div>${esc(x.text)}</div>${x.variety !== 'standard' ? `<i>= ${esc(x.standard)}</i><small> · ${esc(x.status||'source candidate')}</small>` : ''}</div>`).join('');
    openSheet(`<div class="wc-top"><div><div class="wc-word">${esc(d.word)}</div><div class="wc-roman">${esc(d.roman)}</div></div>
        <button class="round-btn speaker-btn" data-act="speakText" data-t="${esc(d.word)}" aria-label="Listen"><svg class="ic" style="width:19px;height:19px"><use href="#i-speaker"/></svg></button></div>
      <div class="wc-chips">${d.english ? `<span class="chip">English: ${esc(d.english)}</span>` : ''}${d.typed && d.typed !== d.word ? `<span class="chip" style="background:var(--blue-100);color:#1B3F86">you typed “${esc(d.typed)}”</span>` : ''}</div>
      ${means ? `<div class="sheet-sub">As a dialect word</div>${means}` : ''}
      ${ins ? `<div class="sheet-sub">How people say it</div>${ins}` : ''}
      ${ex ? `<div class="sheet-sub">Examples</div>${ex}` : ''}
      <div style="margin-top:12px"><button class="btn btn-line btn-block btn-sm" data-act="teachWord" data-w="${esc(d.word)}">Suggest a better meaning</button></div>`);
  } catch (e) { openSheet('<div class="empty"><b>Could not load the word</b>Check your connection and try again.</div>'); }
}

/* ---------- dictionary browser: completely independent of the translator ---------- */
const savedDict = store.get('vs_dictionary_language', 'Chattogram');
const D = { dialect: DICT_IDS.has(savedDict)?savedDict:'Chattogram', q: '', page: 1, pages: 1, busy: false, seq:0, t: null };
function onDict() {
  const select = $('#dict-language');
  select.innerHTML = DICTIONARY_LANGS.map(l=>`<option value="${esc(l.id)}">${esc(l.name)}</option>`).join('');
  select.value = D.dialect;
  const popular = ['Chattogram','Sylheti','Noakhali','Barishal','Mymensingh','Rajshahi','Rangpur','Narail','Kishoreganj','Narsingdi','Tangail'];
  const visible = popular.includes(D.dialect) ? popular : [D.dialect,...popular];
  $('#dict-chips').innerHTML = visible.map(id=> {
    const l=DICTIONARY_LANGS.find(x=>x.id===id);
    return `<button class="dchip ${id === D.dialect ? 'on' : ''}" data-act="dictDialect" data-id="${esc(id)}"><span class="dot" style="--c:${l.c||colorOf(id)}"></span>${esc(l.name)}</button>`;
  }).join('');
  loadDict(true);
}
function changeDictionaryLanguage(id) {
  if (!DICT_IDS.has(id)) return;
  D.dialect=id;
  store.set('vs_dictionary_language',id);
  onDict();
}
async function loadDict(reset) {
  if (D.busy && !reset) return;
  if (reset) { D.page = 1; D.pages=1; $('#dict-list').innerHTML = ''; }
  const seq=++D.seq, dialect=D.dialect, page=D.page;
  D.busy = true;
  try {
    const d = await api(`dictionary.php?dialect=${encodeURIComponent(dialect)}&q=${encodeURIComponent(D.q)}&page=${page}`);
    if (seq !== D.seq || dialect !== D.dialect) return;
    if (d.error) throw Error(d.error);
    D.pages = d.pages || 1;
    $('#dict-sub').textContent = `Browsing ${langName(dialect) === dialect ? (DICTIONARY_LANGS.find(l=>l.id===dialect)||{}).name||dialect : langName(dialect)} · separate dictionary · sources require verification`;
    $('#dict-count').textContent = `${(d.total||0).toLocaleString()} ${D.q ? 'matches' : 'available word meanings'} in this language`;
    const rows = (d.rows || []).map(r=>`<div class="drow-wrap"><button class="drow ${r.verified ? 'v' : ''}" data-act="lookup" data-lang="${esc(dialect)}" data-w="${esc(r.dialect_word)}" title="${esc(r.review_status||'')} · ${esc(r.source_dataset||'')}">
      <span class="a">${esc(r.dialect_word)}<small>${esc(r.roman)}</small></span><svg class="ic arrow" style="width:18px;height:18px"><use href="#i-arrow"/></svg><span class="b">${esc(r.standard_word)}</span></button><button class="dedit" data-act="editWord" data-lang="${esc(dialect)}" data-w="${esc(r.dialect_word)}" data-m="${esc(r.standard_word)}" data-p="${r.phrase?1:''}" aria-label="Edit this word" title="Edit">✎</button></div>`).join('');
    if (!d.rows.length && D.page === 1) $('#dict-list').innerHTML = `<div class="card empty"><b>${D.q?'No matching words':'No published word meanings yet'}</b>Only ${esc(dialect)} records were checked. Try another spelling or select a different language.</div>`;
    else $('#dict-list').insertAdjacentHTML('beforeend', rows);
    $('#dict-more').style.display = D.page < D.pages ? '' : 'none';
  } catch (e) { if(seq===D.seq) { toast('Could not load the dictionary'); $('#dict-list').innerHTML='<div class="card empty">Database unavailable. Check MySQL and setup.php.</div>'; } }
  finally { if(seq===D.seq) D.busy = false; }
}

async function copyResult() {
  const text = S.result || $('#result-text').textContent;
  if (!text) return;
  try {
    await navigator.clipboard.writeText(text);
  } catch (e) {
    const ta = document.createElement('textarea');
    ta.value = text; document.body.appendChild(ta); ta.select();
    try { document.execCommand('copy'); } catch (err) { /* ignore */ }
    ta.remove();
  }
  toast('Copied ✓');
}

/* ---------- Voice input ---------- */
let recognizer = null, voiceLang = 'bn-BD', voiceRetried = false;
function setListening(on, label) {
  $('#mic-btn').classList.toggle('listening', on);
  $('#compose').classList.toggle('listening', on);
  if (label) $('#listen-label').textContent = label;
}
function openWordForm(lang, word, meaning, phrase) {
  openSheet(`<div class="wordform" data-lang="${esc(lang)}" data-old="${esc(word)}" data-p="${phrase ? 1 : ''}">
    <h3>${word ? 'Edit word' : 'Add a word'} · ${esc(langName(lang))}</h3>
    <label class="vs-dict-language-label" for="wf-word">${esc(langName(lang))} word (in Bangla letters)</label>
    <input class="input" id="wf-word" value="${esc(word)}" autocomplete="off">
    <label class="vs-dict-language-label" for="wf-mean" style="margin-top:10px;display:block">Standard Bangla meaning</label>
    <input class="input" id="wf-mean" value="${esc(meaning)}" autocomplete="off">
    <div style="display:flex;gap:8px;margin-top:14px">
      <button class="btn btn-sm" data-act="saveWordForm" style="flex:1">Save</button>
      ${word ? '<button class="btn btn-line btn-sm" data-act="deleteWordForm">Delete</button>' : ''}
    </div></div>`);
}
function stopMic() { if (recognizer) { try { recognizer.stop(); } catch (e) { /* ignore */ } } }
function toggleMic() {
  const input = $('#dialect-input');
  if (recognizer) { try { recognizer.stop(); } catch (e) { /* ignore */ } return; }
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SR) { toast('Voice input needs Chrome or Edge. You can type or scan an image instead.', 3600); return; }
  if (!window.isSecureContext) { toast('Voice needs a secure page: open the app from localhost or https://', 3800); return; }

  const rec = new SR();
  rec.lang = voiceLang;
  rec.interimResults = true;
  rec.continuous = true;   // keep listening until the person taps Stop
  rec.maxAlternatives = 1;
  let gotText = false, failed = false, retrying = false;
  const before = input.value;

  rec.onstart = () => { setListening(true, 'Listening… speak now, then tap Stop'); vib(15); };
  rec.onresult = (e) => {
    let t = '';
    for (let i = 0; i < e.results.length; i++) t += e.results[i][0].transcript;
    setInput(t);
    if (t.trim()) { gotText = true; $('#listen-label').textContent = 'Heard: ' + t.slice(-40); }
  };
  rec.onerror = (e) => {
    failed = true;
    switch (e.error) {
      case 'not-allowed':
      case 'service-not-allowed': toast('Microphone is blocked. Tap the lock icon by the address bar and allow Microphone.', 4200); break;
      case 'no-speech': toast('No speech heard. Tap the mic and speak closer to it.'); break;
      case 'audio-capture': toast('No microphone found on this device.'); break;
      case 'network': toast('Voice recognition needs internet (Chrome sends audio to Google). Check your connection.', 4200); break;
      case 'language-not-supported':
        if (!voiceRetried) { voiceRetried = true; voiceLang = 'bn-IN'; retrying = true; setTimeout(toggleMic, 150); }
        else toast('This browser has no Bengali speech recognition. Please type or scan an image.', 3800);
        break;
      case 'aborted': break;
      default: toast('Voice error: ' + e.error);
    }
  };
  rec.onend = () => {
    setListening(false);
    recognizer = null;
    if (gotText) setTimeout(logCurrent, 1200);
    if (!gotText && !failed && !retrying) {
      setInput(before);
      if (!voiceRetried) { voiceRetried = true; voiceLang = voiceLang === 'bn-BD' ? 'bn-IN' : 'bn-BD'; toast('Trying another Bangla voice setting…'); setTimeout(toggleMic, 250); }
      else toast('Nothing was heard. Check the microphone permission, or use the microphone on your keyboard instead.', 4200);
    }
  };
  recognizer = rec;
  try { rec.start(); } catch (err) { recognizer = null; toast('Could not start the microphone. Please try again.'); }
}

/* ---------- Voice output ---------- */
function pickBengaliVoice() {
  const voices = window.speechSynthesis ? speechSynthesis.getVoices() : [];
  return voices.find((v) => /^bn([-_]|$)/i.test(v.lang)) || voices.find((v) => /bengali|bangla/i.test(v.name)) || null;
}
let onlineAudio = null;
function playOnlineVoice(text, btn, lang) {
  // Fallback when the device has no Bengali voice: an online text-to-speech clip (needs internet).
  try { if (onlineAudio) { onlineAudio.pause(); onlineAudio = null; } } catch (e) { /* ignore */ }
  const clip = text.length > 180 ? text.slice(0, 180) : text;
  const url = 'https://translate.google.com/translate_tts?ie=UTF-8&client=tw-ob&tl=' + encodeURIComponent(lang) + '&q=' + encodeURIComponent(clip);
  const a = new Audio(url);
  onlineAudio = a;
  a.onplay = () => btn.classList.add('speaking');
  a.onended = a.onpause = () => btn.classList.remove('speaking');
  a.onerror = () => { btn.classList.remove('speaking'); toast('Could not play the voice. Check your internet connection, or install a Bengali voice in your device’s Speech settings.', 4200); };
  const pr = a.play();
  if (pr && pr.catch) pr.catch(() => { btn.classList.remove('speaking'); toast('Could not play the voice. Check your internet connection, or install a Bengali voice in your device’s Speech settings.', 4200); });
}
function playAudio(el, ev, override) {
  const text = (typeof override === 'string' ? override : $('#result-text').textContent).trim();
  if (!text) { toast('Translate something first, then tap the speaker'); return; }
  const english = typeof override !== 'string' && S.to === 'english';
  const btn = $('#speak-btn');
  if (!('speechSynthesis' in window)) { playOnlineVoice(text, btn, english ? 'en' : 'bn'); return; }
  const speak = () => {
    const voice = english ? null : pickBengaliVoice();
    if (!voice && !english) { playOnlineVoice(text, btn, 'bn'); return; }   // no local Bengali voice
    speechSynthesis.cancel();
    const u = new SpeechSynthesisUtterance(text);
    u.lang = english ? 'en-US' : voice.lang;
    if (voice) u.voice = voice;
    let started = false;
    u.onstart = () => { started = true; btn.classList.add('speaking'); };
    u.onend = () => btn.classList.remove('speaking');
    u.onerror = (e) => {
      btn.classList.remove('speaking');
      if (e.error === 'canceled' || e.error === 'interrupted') return;
      playOnlineVoice(text, btn, english ? 'en' : 'bn');
    };
    speechSynthesis.speak(u);
    // some browsers accept the voice but never produce sound
    setTimeout(() => { if (!started && !speechSynthesis.speaking) playOnlineVoice(text, btn, english ? 'en' : 'bn'); }, 1500);
  };
  if (speechSynthesis.getVoices().length) { speak(); return; }
  let done = false;
  const once = () => { if (done) return; done = true; speak(); };
  speechSynthesis.addEventListener('voiceschanged', once, { once: true });
  setTimeout(once, 700);
}

/* ---------- Image → text (OCR, runs in the browser; nothing is uploaded) ---------- */
let ocrWorkerPromise = null, scanBusy = false, scanObjectUrl = null;

function loadScript(src) {
  return new Promise((resolve, reject) => {
    const el = document.createElement('script');
    el.src = src; el.onload = resolve;
    el.onerror = () => reject(new Error('missing-file'));
    document.head.appendChild(el);
  });
}
function setScanStatus(text, pct) {
  $('#scan-status').textContent = text;
  if (typeof pct === 'number') $('#scan-bar-fill').style.width = Math.max(4, Math.min(100, pct)) + '%';
}
function onOcrProgress(m) {
  if (!scanBusy) return;
  if (m.status === 'recognizing text') setScanStatus('Reading text… ' + Math.round(m.progress * 100) + '%', 40 + m.progress * 60);
  else if (/language/.test(m.status)) setScanStatus('Loading Bengali data…', 25);
  else if (/core|initializing/.test(m.status)) setScanStatus('Starting image reader…', 12);
}
function getOcrWorker() {
  if (!ocrWorkerPromise) {
    const abs = (p) => new URL(p, location.href).href;
    ocrWorkerPromise = (async () => {
      if (!window.Tesseract) await loadScript('assets/ocr/tesseract.min.js');
      return await Tesseract.createWorker('ben', 1, {
        workerPath: abs('assets/ocr/worker.min.js'),
        corePath: abs('assets/ocr'),
        langPath: abs('assets/ocr/lang'),
        gzip: false,
        logger: onOcrProgress,
      });
    })().catch((e) => { ocrWorkerPromise = null; throw e; });
  }
  return ocrWorkerPromise;
}
function loadImageFromFile(file) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => resolve({ img, url });
    img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('bad-image')); };
    img.src = url;
  });
}
function prepareForOcr(img) {
  const longest = Math.max(img.naturalWidth, img.naturalHeight);
  let scale = 1;
  if (longest > 2000) scale = 2000 / longest; else if (longest < 900) scale = 2;
  const w = Math.round(img.naturalWidth * scale), h = Math.round(img.naturalHeight * scale);
  const canvas = document.createElement('canvas');
  canvas.width = w; canvas.height = h;
  const ctx = canvas.getContext('2d', { willReadFrequently: true });
  ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, w, h);
  ctx.drawImage(img, 0, 0, w, h);
  const data = ctx.getImageData(0, 0, w, h), px = data.data, hist = new Uint32Array(256);
  for (let i = 0; i < px.length; i += 4) {
    const g = Math.round(0.299 * px[i] + 0.587 * px[i + 1] + 0.114 * px[i + 2]);
    px[i] = px[i + 1] = px[i + 2] = g; hist[g]++;
  }
  const total = w * h; let acc = 0, lo = 0, hi = 255;
  for (let v = 0; v < 256; v++) { acc += hist[v]; if (acc >= total * 0.02) { lo = v; break; } }
  acc = 0;
  for (let v = 255; v >= 0; v--) { acc += hist[v]; if (acc >= total * 0.02) { hi = v; break; } }
  if (hi - lo > 20) {
    const k = 255 / (hi - lo);
    for (let i = 0; i < px.length; i += 4) { const g = Math.max(0, Math.min(255, (px[i] - lo) * k)); px[i] = px[i + 1] = px[i + 2] = g; }
  }
  ctx.putImageData(data, 0, 0);
  return canvas;
}
const cleanOcrText = (t) => {
  let x = t.replace(/[|_~]/g, ' ').replace(/\s*\n\s*/g, ' ').replace(/\s{2,}/g, ' ').trim();
  const toks = x.split(' ');
  const bn = (w) => /[\u0980-\u09FF]{2,}/.test(w);
  // when most words are Bangla, drop stray noise tokens (lone digits/symbols/1-char bits)
  if (toks.filter(bn).length >= 2 && toks.filter(bn).length * 2 >= toks.length) {
    x = toks.filter((w) => bn(w) || /^[\u0980-\u09FF]{1}[\u0980-\u09FF]*$/.test(w) && w.length > 1 || /^[।?!,.]$/.test(w) ? true : false).join(' ');
    x = x.replace(/\s+([।?!,.])/g, '$1');
  }
  return x.trim();
};

function clearScan() {
  if (scanBusy) return;
  $('#scan-row').classList.remove('show');
  if (scanObjectUrl) { URL.revokeObjectURL(scanObjectUrl); scanObjectUrl = null; }
  $('#image-input').value = '';
}
async function handleImage(inputEl) {
  const file = inputEl.files && inputEl.files[0];
  if (!file || scanBusy) return;
  if (!file.type.startsWith('image/')) { toast('Please choose an image file'); inputEl.value = ''; return; }
  const camBtn = $('#cam-btn');
  scanBusy = true; camBtn.disabled = true;
  $('#scan-row').classList.add('show');
  setScanStatus('Preparing image…', 6);
  try {
    const { img, url } = await loadImageFromFile(file);
    if (scanObjectUrl) URL.revokeObjectURL(scanObjectUrl);
    scanObjectUrl = url;
    $('#scan-thumb').src = url;
    const canvas = prepareForOcr(img);
    setScanStatus('Starting image reader…', 10);
    const worker = await getOcrWorker();
    const { data } = await worker.recognize(canvas);
    const text = cleanOcrText(data.text || '');
    if (text.length < 2) {
      setScanStatus('No readable text found', 100);
      toast('Could not find text. Try a sharper, well-lit photo of printed text.', 3600);
    } else {
      setInput(text);
      $('#dialect-input').focus();
      setScanStatus('Text found ✓', 100);
      toast(data.confidence < 55 ? 'Text read, but unsure. Please check it before translating.' : 'Text read from image. Press Translate.', 3200);
      vib(15);
    }
  } catch (err) {
    if (err && err.message === 'bad-image') toast('That file could not be opened as an image.');
    else toast('Image reader could not start. Make sure the assets/ocr folder was uploaded.', 3800);
    setScanStatus('Could not read image', 100);
  } finally {
    scanBusy = false; camBtn.disabled = false;
    inputEl.value = '';
  }
}

/* ============================================================
   BRIDGE (word breakdown)
   ============================================================ */
const bridgeIllustration = () => `<svg viewBox="0 0 140 90" aria-hidden="true"><rect x="18" y="46" width="104" height="7" rx="3.5" fill="#F2A93B"/><g stroke="#F2A93B" stroke-width="5" stroke-linecap="round"><line x1="34" y1="46" x2="34" y2="66"/><line x1="52" y1="46" x2="52" y2="66"/><line x1="70" y1="46" x2="70" y2="66"/><line x1="88" y1="46" x2="88" y2="66"/><line x1="106" y1="46" x2="106" y2="66"/></g><rect x="14" y="24" width="9" height="52" rx="4.5" fill="#BFE3D4"/><rect x="117" y="24" width="9" height="52" rx="4.5" fill="#BFE3D4"/><circle cx="18.5" cy="17" r="12" fill="#2F6FE0" stroke="#fff" stroke-width="3"/><circle cx="121.5" cy="17" r="12" fill="#E2556B" stroke="#fff" stroke-width="3"/></svg>`;

function renderBridge() {
  const box = $('#bridge-card'), rows = S.breakdown;
  if (!rows.length) {
    box.innerHTML = `<div class="empty">${bridgeIllustration()}<b>No bridge yet</b>Translate something first, then come back to watch each word cross over.<div style="margin-top:16px"><button class="btn btn-gold btn-sm" data-go="translate">Go translate</button></div></div>`;
    return;
  }
  const fromL = S.last ? S.last.detected.label : langName(S.from), toL = langName(S.to);
  $('#bridge-sub').textContent = `${fromL} → ${toL}`;
  box.innerHTML = `<div class="bridge" id="bridge"><svg class="bridge-svg" id="bridge-svg"></svg>
    <div class="bridge-head"><span>${esc(fromL)}</span><span>${esc(toL)}</span></div>
    ${rows.map((r, i) => { const unk = r.kind === 'unknown'; return `<div class="brow ${unk ? 'miss' : ''}" style="--i:${i}">
      <div class="bpill l" ${unk ? `data-act="teachWord" data-w="${esc(r.src)}"` : `data-act="lookup" data-w="${esc(r.src)}"`}>${esc(r.src)}</div>
      <div class="bpill r">${unk ? '?' : esc(pairShown(r))}</div></div>`; }).join('')}</div>
    <div class="bridge-legend"><span><i class="lg"></i> matched</span><span><i class="lg miss"></i> not in dictionary — tap to add</span></div>`;
  requestAnimationFrame(() => requestAnimationFrame(drawBridge));
}
function drawBridge() {
  const wrap = $('#bridge'), svg = $('#bridge-svg');
  if (!wrap || !svg) return;
  const wr = wrap.getBoundingClientRect();
  svg.setAttribute('width', wr.width); svg.setAttribute('height', wr.height);
  svg.setAttribute('viewBox', `0 0 ${wr.width} ${wr.height}`);
  let out = '';
  $$('.brow', wrap).forEach((row, i) => {
    const l = $('.bpill.l', row).getBoundingClientRect(), r = $('.bpill.r', row).getBoundingClientRect();
    const x1 = l.right - wr.left, x2 = r.left - wr.left, y = (l.top + l.bottom) / 2 - wr.top, mx = (x1 + x2) / 2;
    const miss = row.classList.contains('miss');
    out += `<path class="arc ${miss ? 'miss' : ''}" style="--i:${i}" pathLength="1" d="M${x1} ${y} Q${mx} ${y - 46} ${x2} ${y}"/>`;
    out += `<circle class="pier ${miss ? 'miss' : ''}" style="--i:${i}" cx="${x1}" cy="${y}" r="5"/><circle class="pier ${miss ? 'miss' : ''}" style="--i:${i}" cx="${x2}" cy="${y}" r="5"/>`;
    if (miss) out += `<text class="qmark" style="--i:${i}" x="${mx}" y="${y - 32}">?</text>`;
  });
  svg.innerHTML = out;
}

/* ============================================================
   HISTORY
   ============================================================ */
const fmtDate = (s) => {
  const d = new Date(String(s).replace(' ', 'T'));
  return isNaN(d) ? '' : d.toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' });
};
async function loadHistory() {
  const box = $('#history-list');
  box.innerHTML = '<div class="empty"><b>Loading…</b></div>';
  try {
    const data = await api('history.php');
    S.history = data.history || [];
  } catch (e) {
    box.innerHTML = '<div class="empty"><b>Could not load history</b>Check your connection and try again.</div>';
    return;
  }
  renderHistory();
}
function renderHistory() {
  const box = $('#history-list');
  if (!S.history.length) {
    box.innerHTML = `<div class="card empty">${bridgeIllustration()}<b>No translations yet</b>Your translations will appear here, ready to use again.<div style="margin-top:16px"><button class="btn btn-gold btn-sm" data-go="translate">Translate something</button></div></div>`;
    return;
  }
  box.innerHTML = S.history.map((h, i) => `<div class="hcard" data-act="reuse" data-i="${i}" style="--c:${colorOf(h.dialect_type)}">
      <div class="hstripe"></div>
      <div class="hbody"><div class="hmeta"><span class="dot" style="--c:${colorOf(h.dialect_type)}"></span>${esc(h.dialect_type || '')} · ${esc(fmtDate(h.created_at))}</div>
        <div class="horig">${esc(h.original_text)}</div><div class="htrans">${esc(h.translated_text)}</div></div>
      <button class="hdel" data-act="delHistory" data-id="${h.id}" aria-label="Delete this translation">${icon('trash', 20)}</button></div>`).join('');
}
async function deleteHistory(id, card) {
  try {
    await api('history.php', { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: Number(id) }) });
    card.classList.add('removing');
    setTimeout(() => { S.history = S.history.filter((h) => String(h.id) !== String(id)); renderHistory(); }, 250);
  } catch (e) { toast('Could not delete this entry'); }
}

/* ============================================================
   CONTRIBUTE
   ============================================================ */
function openContribute(pre = {}) {
  goTo('contribute');
  if (pre.word !== undefined) $('#contrib-word').value = pre.word;
  if (pre.dialect) $('#contrib-dialect').value = pre.dialect;
  if (pre.word) $('#contrib-meaning').focus();
}
async function submitContribution() {
  const word = $('#contrib-word').value.trim(), meaning = $('#contrib-meaning').value.trim(), dialect = $('#contrib-dialect').value;
  const btn = $('#submit-btn');
  if (!word || !meaning) { toast('Please fill in both the word and its meaning'); vib([20, 30, 20]); return; }
  btn.disabled = true;
  try {
    const data = await post('contribute.php', { dialect_word: word, meaning, dialect_type: dialect });
    if (data.error) { toast(data.error); return; }
    $('#contrib-word').value = ''; $('#contrib-meaning').value = '';
    toast('Thank you! Your word is waiting for review ✓', 3400);
    confetti(30); vib([20, 40, 20]);
  } catch (e) { toast('Could not reach the server'); }
  finally { btn.disabled = false; }
}

/* ============================================================
   AUTH + PROFILE
   ============================================================ */
async function checkAuth() {
  try {
    const data = await api('auth.php?action=me');
    S.user = data.user || null;
    if (S.user && S.user.preferred_dialect && !store.get('vs_touched_lang', false)) { S.dialect = fromApiName(toApiName(S.user.preferred_dialect)); renderLangBar(); }
  } catch (e) { S.user = null; }
  renderGreeting();
  refreshDistrictUI();
}
function loadProfile() {
  const box = $('#profile-body');
  const best = store.get('vs_best', null);
  const streak = (store.get('vs_streak', { count: 1 }).count) || 1;
  const stats = `<div class="stats"><div class="stat"><b>${streak}</b><span>Day streak</span></div><div class="stat"><b>${best === null ? '–' : best + '/10'}</b><span>Best quiz</span></div><div class="stat"><b>${esc(toApiName(S.dialect))}</b><span>Dialect</span></div></div>`;
  const links = `<div class="setting"><span>Privacy policy</span><a href="privacy-policy.html" target="_blank" rel="noopener">Read ›</a></div>`;
  if (!S.user) {
    box.innerHTML = `<div class="card" style="text-align:center">
        ${bridgeIllustration().replace('<svg ', '<svg style="width:120px;margin-bottom:6px" ')}
        <div style="font:800 21px var(--display)">You’re browsing as a guest</div>
        <p style="color:var(--ink-soft);margin:6px 0 18px">Log in to keep your history and favourite dialect safe.</p>
        <button class="btn btn-gold btn-block" data-go="login">Log In</button>
        <div style="height:12px"></div>
        <button class="btn btn-line btn-block" data-go="register">Create an account</button></div>
      <div style="height:16px"></div>${stats}<div class="card">${links}</div>`;
    return;
  }
  const initials = S.user.name.trim().split(/\s+/).map((w) => w[0]).slice(0, 2).join('').toUpperCase();
  box.innerHTML = `<div class="card" style="padding-top:22px">
      <div class="avatar">${esc(initials)}</div>
      <div class="pname">${esc(S.user.name)}</div><div class="pmail">${esc(S.user.email)}</div>
      ${stats}
      <div class="setting"><span>Preferred dialect</span>
        <select id="profile-dialect">${SUPPORTED.map(toApiName).map((k) => `<option ${k === toApiName(S.user.preferred_dialect) ? 'selected' : ''}>${k}</option>`).join('')}</select></div>
      <div class="setting" style="display:block">
        <label class="district-label" for="profile-district">Current district (64 districts)</label>
        <select id="profile-district" class="input" style="width:100%">${districtSelectOptions(S.user.current_district||'')}</select>
        <div class="district-hint" id="profile-district-hint">${districtHint(S.user.current_district||'')}</div>
        <label class="district-label" for="profile-spoken">Language I speak (independent of district)</label>
        <select id="profile-spoken" class="input" style="width:100%">${spokenSelectOptions(S.user.spoken_language||'')}</select>
        <p class="district-explain">Your district never automatically sets your dialect or translation output. Learning courses remain your choice.</p>
      </div>
      <button class="btn btn-gold btn-block" data-go="learn">📚 Continue learning</button>
      ${links}
      <div style="height:14px"></div>
      <button class="btn btn-line btn-block" data-act="doLogout" style="color:var(--rose);border-color:#F6C9D1;box-shadow:0 4px 0 #F6C9D1">Log out</button></div>`;
}
async function doLogin() {
  const email = $('#login-email').value.trim(), password = $('#login-password').value, btn = $('#login-btn');
  if (!email || !password) { toast('Enter your email and password'); return; }
  btn.disabled = true;
  try {
    const data = await post('auth.php?action=login', { email, password });
    if (data.error) { toast(data.error); vib([20, 30, 20]); return; }
    S.user = data.user;
    setDialect(S.user.preferred_dialect);
    $('#login-email').value = ''; $('#login-password').value = '';
    toast('Welcome back, ' + S.user.name.split(' ')[0] + '!');
    renderGreeting(); goTo(afterLoginDestination || 'profile'); afterLoginDestination = null;
  } catch (e) { toast('Could not reach the server'); }
  finally { btn.disabled = false; }
}
async function doRegister() {
  const name = $('#register-name').value.trim(), email = $('#register-email').value.trim(), password = $('#register-password').value,
        dialect = $('#register-dialect').value, district = $('#register-district').value, spoken = $('#register-spoken').value, btn = $('#register-btn');
  if (!name || !email || !password) { toast('Please fill in all fields'); return; }
  btn.disabled = true;
  try {
    const data = await post('auth.php?action=register', { name, email, password, preferred_dialect: dialect, current_district: district, spoken_language: spoken });
    if (data.error) { toast(data.error); vib([20, 30, 20]); return; }
    S.user = data.user;
    setDialect(S.user.preferred_dialect);
    toast('Account created — welcome, ' + S.user.name.split(' ')[0] + '!');
    confetti(34); renderGreeting(); goTo(afterLoginDestination || 'profile'); afterLoginDestination = null;
  } catch (e) { toast('Could not reach the server'); }
  finally { btn.disabled = false; }
}
async function doLogout() {
  try { await api('auth.php?action=logout', { method: 'POST' }); } catch (e) { /* ignore */ }
  S.user = null; S.history = [];
  const lf=$('#learn-frame'); if(lf){lf.src='about:blank';delete lf.dataset.account;}
  afterLoginDestination=null;
  renderGreeting(); goTo('splash'); toast('Logged out');
}

/* ============================================================
   QUIZ — "Guess the Word"
   ============================================================ */
const Q = { n: 0, total: 10, score: 0, asked: [], cur: null, locked: false };
const PRAISE = ['Nice one!', 'সাবাশ!', 'Bridge built!', 'Perfect!', 'দারুণ!', 'You know it!'];

async function startQuiz() {
  Q.n = 0; Q.score = 0; Q.asked = []; Q.cur = null; Q.locked = false;
  $('#qscore').textContent = '0'; $('#qbar-fill').style.width = '0%';
  await nextQuestion();
}
async function nextQuestion() {
  const box = $('#quiz-body');
  if (Q.n >= Q.total) { finishQuiz(); return; }
  box.innerHTML = '<div class="card qcard"><div class="qlabel">Finding a word…</div></div>';
  let q = null;
  try {
    const lang = ['auto','standard','banglish','english'].includes(S.from) ? toApiName(S.dialect) : S.from;
    const data = await api(`quiz.php?dialect=${encodeURIComponent(lang)}&exclude=${Q.asked.join(',')}`);
    q = data.question;
  } catch (e) { q = null; }
  if (!q) {
    box.innerHTML = `<div class="card qdone"><b style="font:800 21px var(--display)">Not enough words yet</b><p>The quiz needs a few more dictionary words. Add some and come back!</p><button class="btn btn-gold btn-block" data-act="openContribute">Add a word</button></div>`;
    return;
  }
  Q.cur = q; Q.asked.push(q.id); Q.locked = false;
  box.innerHTML = `<div class="card qcard"><div class="qlabel">Question ${Q.n + 1} of ${Q.total} · What does this mean?</div>
      <div class="qword">${esc(q.word)}</div>
      <span class="chip"><span class="dot" style="--c:${colorOf(q.dialect)}"></span>${esc(q.dialect)}</span></div>
    <div class="opts">${q.options.map((o, i) => `<button class="opt" data-act="answer" data-i="${i}">${esc(o)}</button>`).join('')}</div>
    <div class="feedback" id="qfb"></div><div id="qnext"></div>`;
}
function answer(btn) {
  if (Q.locked || !Q.cur) return;
  Q.locked = true;
  const i = Number(btn.dataset.i), correct = i === Q.cur.answer;
  const opts = $$('#quiz-body .opt');
  opts.forEach((o) => { o.disabled = true; });
  opts[Q.cur.answer].classList.add('right');
  const fb = $('#qfb');
  if (correct) {
    Q.score++; fb.textContent = PRAISE[Math.floor(Math.random() * PRAISE.length)]; fb.className = 'feedback ok';
    confetti(24); vib(25);
  } else {
    btn.classList.add('wrong'); fb.textContent = 'Not quite — now you know!'; fb.className = 'feedback no'; vib([30, 40, 30]);
  }
  $('#qscore').textContent = Q.score;
  $('#qbar-fill').style.width = ((Q.n + 1) / Q.total * 100) + '%';
  $('#qnext').innerHTML = `<button class="btn btn-gold btn-block" data-act="nextQ">${Q.n + 1 >= Q.total ? 'See my result' : 'Next'} ${icon('arrow')}</button>`;
  setTimeout(() => $('#qnext').scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 80);
}
function finishQuiz() {
  const best = store.get('vs_best', null);
  const isBest = best === null || Q.score > best;
  if (isBest) store.set('vs_best', Q.score);
  const msg = Q.score >= 9 ? 'Dialect master!' : Q.score >= 7 ? 'Great bridge-builder!' : Q.score >= 4 ? 'Good going — keep practising!' : 'Every word you learn builds the bridge.';
  $('#quiz-body').innerHTML = `<div class="card qdone"><div class="medal">${Q.score >= 7 ? '🏆' : '🌉'}</div>
      <div class="big">${Q.score}/${Q.total}</div><p>${msg}${isBest ? '<br><b style="color:var(--gold-600)">New personal best!</b>' : ''}</p>
      <button class="btn btn-gold btn-block" data-act="playAgain">Play again</button><div style="height:12px"></div>
      <button class="btn btn-line btn-block" data-go="home">Back home</button></div>`;
  if (Q.score >= 7) confetti(60);
}

/* ============================================================
   EVENTS
   ============================================================ */
const ACTIONS = {
  quickSpeak() { goTo('translate'); toggleMic(); },
  quickType() { goTo('translate'); $('#dialect-input').focus(); },
  quickScan() { goTo('translate'); $('#image-input').click(); },
  pickImage() { $('#image-input').click(); },
  toggleMic, doTranslate, copyResult, playAudio, clearScan, doLogin, doRegister, doLogout, submitContribution,
  openBridge() { goTo('breakdown'); },
  clearText() { setInput(''); $('#dialect-input').focus(); },
  translateIn(el) { setDialect(el.dataset.d); S.to = toApiName(el.dataset.d); store.set('vs_translate_target', S.to); S.from='auto'; S.last=null; renderLangBar(); goTo('translate'); $('#dialect-input').focus(); },
  anotherSample(el) { loadSample(el.dataset.d); vib(8); },
  contribFor(el) { openContribute({ dialect: el.dataset.d }); },
  teachWord(el) { closeSheet(); const d = (S.last && S.last.source && S.last.source !== 'standard' && S.last.source !== 'english') ? S.last.source : toApiName(S.dialect); openContribute({ word: el.dataset.w, dialect: d }); },
  openContribute() { openContribute({}); },
  reuse(el) {
    const h = S.history[Number(el.dataset.i)];
    if (!h) return;
    const m = String(h.dialect_type || '').split(' → ');
    const idOf = (name) => (LANGS.find((l) => l.name === name) || {}).id;
    if (m.length === 2 && idOf(m[0]) && idOf(m[1])) { S.from = idOf(m[0]); S.to = idOf(m[1]); } else { S.from = 'auto'; S.to = 'standard'; } store.set('vs_translate_target',S.to);
    S.last = null; renderLangBar();
    setInput(h.original_text);
    goTo('translate');
  },
  pickLang(el) { pickLang(el.dataset.side); },
  async cycleAlt(el) {
    const d = S.last; if (!d || !d.transliteration) return;
    const t = d.transliteration[Number(el.dataset.i)];
    const opts = [t.bn].concat((t.alts || []).filter((a) => a !== t.bn));
    if (opts.length < 2) { toast('No other spelling for this word'); return; }
    const k = ((t.k || 0) + 1) % opts.length; t.k = k; t.bn = opts[k]; t.alts = opts;
    const bangla = d.transliteration.map((x) => x.bn).join(' ');
    try {
      const r = await post('translate.php', { text: bangla, source: 'auto', target: S.to, log: false });
      if (r.error) return;
      r.detected.script = 'banglish'; r.bangla_input = bangla; r.transliteration = d.transliteration; r.original_text = d.original_text;
      S.last = r; S.breakdown = r.breakdown || []; S.result = r.translated_text;
      renderLangBar(); renderDetect(r); renderResult(r);
    } catch (e) { toast('Could not reach the server'); }
  },
  wordEvidence(el) { showWordEvidence(Number(el.dataset.idx)); },
  chooseLang(el) { chooseLang(el.dataset.side, el.dataset.id); },
  swapLang,
  closeSheet,
  lookup(el) { lookupWord(el.dataset.w, el.dataset.lang || (document.body.dataset.screen === 'breakdown' && S.last && DICT_IDS.has(S.last.source) ? S.last.source : D.dialect)); },
  speakText(el) { playAudio(null, null, el.dataset.t); },
  dictDialect(el) { changeDictionaryLanguage(el.dataset.id); vib(8); },
  stopMic() { stopMic(); },
  dictMore() { D.page++; loadDict(false); },
  editWord(el) { openWordForm(el.dataset.lang, el.dataset.w, el.dataset.m, !!el.dataset.p); },
  addWord() { openWordForm(D.dialect, '', '', false); },
  async saveWordForm(el) {
    const f = el.closest('.wordform'); const lang = f.dataset.lang, old = f.dataset.old, phrase = !!f.dataset.p;
    const word = $('#wf-word').value.trim(), mean = $('#wf-mean').value.trim();
    if (!word || !mean) { toast('Please fill both boxes'); return; }
    try { const r = await post('edit_word.php', { language: lang, word, old_word: old, meaning: mean, phrase, action: 'save' }); if (r.error) { toast(r.error, 3600); return; } closeSheet(); toast('Saved'); loadDict(true); }
    catch (e) { toast('Could not save'); }
  },
  async deleteWordForm(el) {
    const f = el.closest('.wordform'); if (!f.dataset.old || !window.confirm('Delete this word?')) return;
    try { const r = await post('edit_word.php', { language: f.dataset.lang, word: f.dataset.old, meaning: '', phrase: !!f.dataset.p, action: 'delete' }); if (r.error) { toast(r.error, 3600); return; } closeSheet(); toast('Deleted'); loadDict(true); }
    catch (e) { toast('Could not delete'); }
  },
  stopMic() { stopMic(); },
  dictMore() { D.page++; loadDict(false); },
  delHistory(el) { deleteHistory(el.dataset.id, el.closest('.hcard')); },
  tryWod() {
    if (!S.wod) return;
    setDialect(S.wod.dialect_type); S.from=toApiName(S.wod.dialect_type); S.to='standard'; store.set('vs_translate_target','standard'); renderLangBar();
    setInput(S.wod.dialect_word);
    goTo('translate');
    doTranslate();
  },
  nextWod() { loadWod(S.wod ? S.wod.id : 0); vib(8); },
  togglePw(el) {
    const input = document.getElementById(el.dataset.target);
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    el.innerHTML = icon(show ? 'eyeoff' : 'eye');
  },
  answer, nextQ() { Q.n++; nextQuestion(); },
  quitQuiz() { goTo('home'); },
  playAgain() { startQuiz(); },
};

document.addEventListener('click', (e) => {
  const pin = e.target.closest('#map-home .pin');
  if (pin) { selectPin(pin.dataset.d); return; }
  const go = e.target.closest('[data-go]');
  if (go) { vib(8); goTo(go.dataset.go); return; }
  const act = e.target.closest('[data-act]');
  if (act && ACTIONS[act.dataset.act]) ACTIONS[act.dataset.act](act, e);
});
document.addEventListener('keydown', (e) => {
  if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches('#map-home .pin')) { e.preventDefault(); selectPin(e.target.dataset.d); }
  if (e.key === 'Enter' && e.target.id && /^(login|register)-/.test(e.target.id)) {
    (e.target.id.startsWith('login') ? doLogin : doRegister)();
  }
});
document.addEventListener('change', async (e) => {
  if (e.target.id==='home-district' || e.target.id==='profile-district') {
    const d=e.target.value;
    if (S.user) {
      try {const res=await post('auth.php?action=update_district',{current_district:d}); if(res.error)throw Error(res.error); S.user.current_district=d;toast('District preference saved ✓');}
      catch(err){toast('District not saved: '+err.message);}
    } else { store.set('vs_guest_district',d);toast('District saved on this device'); }
    refreshDistrictUI();
  }
  if (e.target.id==='register-district') $('#register-district-hint').innerHTML=districtHint(e.target.value);
  if (e.target.id==='profile-spoken' && S.user) {
    const id=e.target.value;
    try {const res=await post('auth.php?action=update_spoken_language',{spoken_language:id});if(res.error)throw Error(res.error); S.user.spoken_language=id; toast('Spoken language saved ✓');}
    catch(err){toast('Could not save spoken language: '+err.message);}
  }

  if (e.target.id === 'image-input') handleImage(e.target);
  if (e.target.id === 'dict-language') changeDictionaryLanguage(e.target.value);
  if (e.target.id === 'allow-approximate') { lastLogged = ''; scheduleLive(); }
  if (e.target.id === 'profile-dialect' && S.user) {
    const d = e.target.value;
    try {
      await post('auth.php?action=update_dialect', { preferred_dialect: d });
      S.user.preferred_dialect = d; setDialect(d); loadProfile(); toast('Preferred dialect updated ✓');
    } catch (err) { toast('Could not save preference'); }
  }
});
document.addEventListener('input', (e) => {
  if (e.target.id === 'dialect-input') { $('#clear-btn').classList.toggle('show', !!e.target.value); scheduleLive(); }
  if (e.target.id === 'dict-q') { clearTimeout(D.t); D.t = setTimeout(() => { D.q = e.target.value.trim(); loadDict(true); }, 350); }
});
document.addEventListener('focusout', (e) => { if (e.target.id === 'dialect-input') logCurrent(); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeSheet(); });

/* ---------- direct links: index.php#login, #translate, ... ---------- */
function openFromHash() {
  const t = location.hash.replace('#', '');
  if (SCREENS.includes(t)) goTo(t);
}
window.addEventListener('hashchange', openFromHash);

/* ---------- start ---------- */
async function init() {
  tickClock(); setInterval(tickClock, 30000);
  touchStreak();
  renderLangBar();
  renderGreeting();
  await Promise.all([checkAuth(),loadDistricts()]);
  openFromHash();

  // "Install app" button: Chrome offers installation through this event; show our own button for it.
  let installEvt = null;
  window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); installEvt = e; const b = $('#install-btn'); if (b) b.hidden = false; });
  window.addEventListener('appinstalled', () => { const b = $('#install-btn'); if (b) b.hidden = true; toast('App installed ✓'); });
  ACTIONS.installApp = async () => {
    if (!installEvt) { toast('Use the browser menu (⋮) → Install app / Add to Home screen', 4200); return; }
    installEvt.prompt(); await installEvt.userChoice.catch(() => {}); installEvt = null; $('#install-btn').hidden = true;
  };
  if ('serviceWorker' in navigator) {
    const hadController = !!navigator.serviceWorker.controller;
    navigator.serviceWorker.addEventListener('controllerchange', () => {
      if (hadController && !window.__swReloaded) { window.__swReloaded = true; location.reload(); }
    });
    window.addEventListener('load', () => { navigator.serviceWorker.register('sw.js').catch(() => {}); });
  }
}
init();
})();
