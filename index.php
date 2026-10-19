<?php
// Adds ?v=<file modified time> so browsers always fetch the newest CSS/JS after you upload changes
function asset($p) { return htmlspecialchars($p) . '?v=' . @filemtime(__DIR__ . '/' . $p); }
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Vashasetu — ভাষাসেতু</title>
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#0F5C56">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Vashasetu">
<link rel="apple-touch-icon" href="icons/icon-192.png">
<link rel="icon" href="icons/icon-192.png">
<link rel="preload" href="assets/fonts/baloo-da-2-bengali-800-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="assets/fonts/hind-siliguri-bengali-500-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
</head>
<body data-screen="splash" data-nav="0">

<!-- Icon sprite -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-home" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></symbol>
  <symbol id="i-translate" viewBox="0 0 24 24"><path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/></symbol>
  <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></symbol>
  <symbol id="i-plus" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></symbol>
  <symbol id="i-user" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></symbol>
  <symbol id="i-mic" viewBox="0 0 24 24"><path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></symbol>
  <symbol id="i-camera" viewBox="0 0 24 24"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></symbol>
  <symbol id="i-keyboard" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M6 8h.01M10 8h.01M14 8h.01M18 8h.01M8 12h.01M12 12h.01M16 12h.01M7 16h10"/></symbol>
  <symbol id="i-speaker" viewBox="0 0 24 24"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 010 14.14M15.54 8.46a5 5 0 010 7.07"/></symbol>
  <symbol id="i-copy" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></symbol>
  <symbol id="i-check" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></symbol>
  <symbol id="i-x" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></symbol>
  <symbol id="i-arrow" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></symbol>
  <symbol id="i-back" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></symbol>
  <symbol id="i-trash" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></symbol>
  <symbol id="i-eye" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></symbol>
  <symbol id="i-eyeoff" viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></symbol>
  <symbol id="i-flame" viewBox="0 0 24 24"><path d="M8.5 14.5A2.5 2.5 0 0011 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 11-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 002.5 2.5z"/></symbol>
  <symbol id="i-star" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></symbol>
  <symbol id="i-bulb" viewBox="0 0 24 24"><path d="M9 18h6M10 22h4"/><path d="M12 2a7 7 0 00-4 12.7c.6.5 1 1.2 1 2V17h6v-.3c0-.8.4-1.5 1-2A7 7 0 0012 2z"/></symbol>
  <symbol id="i-refresh" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></symbol>
  <symbol id="i-swap" viewBox="0 0 24 24"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 01-4 4H3"/></symbol>
  <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></symbol>
  <symbol id="i-book" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20V3H6.5A2.5 2.5 0 004 5.5z"/><path d="M4 19.5A2.5 2.5 0 006.5 22H20v-5"/></symbol>
  <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></symbol>
</svg>

<div class="device" id="device">
  <div class="statusbar"><span id="clock">9:41</span><span>●●●●</span></div>

  <!-- ===================== SPLASH ===================== -->
  <section class="screen active" id="screen-splash">
    <div class="splash">
      <div class="floaters" aria-hidden="true">
        <span style="left:8%;animation-duration:17s;animation-delay:0s">ভা</span>
        <span style="left:24%;animation-duration:21s;animation-delay:3s">ষা</span>
        <span style="left:44%;animation-duration:19s;animation-delay:7s">সে</span>
        <span style="left:63%;animation-duration:23s;animation-delay:1s">তু</span>
        <span style="left:80%;animation-duration:18s;animation-delay:9s">ক</span>
        <span style="left:34%;animation-duration:25s;animation-delay:12s">খ</span>
        <span style="left:72%;animation-duration:20s;animation-delay:5s">ম</span>
      </div>
      <svg class="bridge-logo" viewBox="0 0 140 90" aria-hidden="true">
        <rect class="bl-deck" x="18" y="46" width="104" height="7" rx="3.5" fill="#F2A93B"/>
        <g stroke="#F2A93B" stroke-width="5" stroke-linecap="round">
          <line class="bl-hang" style="--i:0" x1="34" y1="46" x2="34" y2="66"/>
          <line class="bl-hang" style="--i:1" x1="52" y1="46" x2="52" y2="66"/>
          <line class="bl-hang" style="--i:2" x1="70" y1="46" x2="70" y2="66"/>
          <line class="bl-hang" style="--i:3" x1="88" y1="46" x2="88" y2="66"/>
          <line class="bl-hang" style="--i:4" x1="106" y1="46" x2="106" y2="66"/>
        </g>
        <rect class="bl-pillar" x="14" y="24" width="9" height="52" rx="4.5" fill="#fff"/>
        <rect class="bl-pillar" x="117" y="24" width="9" height="52" rx="4.5" fill="#fff"/>
        <g class="bl-bob"><circle class="bl-dot" cx="18.5" cy="17" r="12" fill="#2F6FE0" stroke="#FBF6EC" stroke-width="3"/></g>
        <g class="bl-bob r"><circle class="bl-dot" cx="121.5" cy="17" r="12" fill="#E2556B" stroke="#FBF6EC" stroke-width="3"/></g>
      </svg>
      <h1 class="wordmark-bn">ভাষাসেতু</h1>
      <div class="wordmark-en">VASHASETU</div>
      <p class="tagline">Hear every dialect.<br>Be understood by everyone.</p>
      <button class="btn btn-gold splash-cta" data-go="choice">Start Translating <svg class="ic"><use href="#i-arrow"/></svg></button>
      <div class="waves" aria-hidden="true">
        <svg class="w2" viewBox="0 0 600 100" preserveAspectRatio="none"><path d="M0 46 Q75 6 150 46 T300 46 T450 46 T600 46 V100 H0Z" fill="rgba(255,255,255,.13)"/></svg>
        <svg class="w1" viewBox="0 0 600 100" preserveAspectRatio="none"><path d="M0 62 Q75 30 150 62 T300 62 T450 62 T600 62 V100 H0Z" fill="#FBF6EC"/></svg>
      </div>
    </div>
  </section>

  <!-- ===================== CHOICE ===================== -->
  <section class="screen" id="screen-choice">
    <div class="splash">
      <div class="floaters" aria-hidden="true">
        <span style="left:12%;animation-duration:20s">ভা</span><span style="left:52%;animation-duration:24s;animation-delay:6s">ষা</span><span style="left:76%;animation-duration:19s;animation-delay:2s">সে</span>
      </div>
      <svg class="bridge-logo" viewBox="0 0 140 90" style="width:120px" aria-hidden="true">
        <rect x="18" y="46" width="104" height="7" rx="3.5" fill="#F2A93B"/>
        <g stroke="#F2A93B" stroke-width="5" stroke-linecap="round"><line x1="34" y1="46" x2="34" y2="66"/><line x1="52" y1="46" x2="52" y2="66"/><line x1="70" y1="46" x2="70" y2="66"/><line x1="88" y1="46" x2="88" y2="66"/><line x1="106" y1="46" x2="106" y2="66"/></g>
        <rect x="14" y="24" width="9" height="52" rx="4.5" fill="#fff"/><rect x="117" y="24" width="9" height="52" rx="4.5" fill="#fff"/>
        <circle cx="18.5" cy="17" r="12" fill="#2F6FE0" stroke="#FBF6EC" stroke-width="3"/><circle cx="121.5" cy="17" r="12" fill="#E2556B" stroke="#FBF6EC" stroke-width="3"/>
      </svg>
      <h2>স্বাগতম</h2>
      <div class="wordmark-en" style="margin-top:2px">WELCOME</div>
      <p class="sub">Log in to keep your history and favourite dialect, or jump straight in as a guest.</p>
      <div class="splash-stack">
        <button class="btn btn-gold btn-block" data-go="login">Log In</button>
        <button class="btn btn-ghost btn-block" data-go="translate">Continue without Login</button>
        <button class="link" style="color:#BFE3D4" data-go="register">New here? Create an account</button>
      </div>
      <div class="waves" aria-hidden="true">
        <svg class="w2" viewBox="0 0 600 100" preserveAspectRatio="none"><path d="M0 46 Q75 6 150 46 T300 46 T450 46 T600 46 V100 H0Z" fill="rgba(255,255,255,.13)"/></svg>
        <svg class="w1" viewBox="0 0 600 100" preserveAspectRatio="none"><path d="M0 62 Q75 30 150 62 T300 62 T450 62 T600 62 V100 H0Z" fill="#FBF6EC"/></svg>
      </div>
    </div>
  </section>

  <!-- ===================== HOME ===================== -->
  <section class="screen" id="screen-home">
    <div class="scroll">
      <header class="hero">
        <div class="hero-row">
          <div>
            <div class="greet-bn" id="greet-bn">শুভ সকাল</div>
            <h1 id="greet-name">Welcome</h1>
          </div>
          <button class="streak" id="streak-chip" data-go="profile" aria-label="Your day streak"><svg class="ic"><use href="#i-flame"/></svg><span id="streak-n">1</span></button>
        </div>
        <p>What would you like to understand today?</p>
      </header>

      <div class="pull">
        <div class="card">
          <div class="quick-title">Translate a dialect</div>
          <div class="quick-row">
            <button class="qa" data-act="quickSpeak"><span class="qa-ic rose"><svg class="ic"><use href="#i-mic"/></svg></span>Speak</button>
            <button class="qa" data-act="quickType"><span class="qa-ic blue"><svg class="ic"><use href="#i-keyboard"/></svg></span>Type</button>
            <button class="qa" data-act="quickScan"><span class="qa-ic gold"><svg class="ic"><use href="#i-camera"/></svg></span>Scan</button>
          </div>
        </div>
      </div>

      <div class="section">
        <h2>Tap your region <span class="bn">আপনার অঞ্চল</span></h2>
        <div class="card mapcard">
          <div id="map-home"></div>
          <div class="bubble" id="bubble"></div>
        </div>
      </div>

      <div class="section">
        <h2>Word of the day <span class="bn">আজকের শব্দ</span></h2>
        <div class="card wod" id="wod-card"><div class="wod-empty">Loading…</div></div>
      </div>

      <div class="section">
        <button class="card playcard" data-go="quiz">
          <span class="play-ic"><svg class="ic" style="width:30px;height:30px"><use href="#i-star"/></svg></span>
          <span><b>Guess the Word</b><small id="play-sub">Test what you know — keep your streak alive</small></span>
        </button>
      </div>

      <div class="section">
        <div class="card contribcard">
          <span class="qa-ic gold"><svg class="ic"><use href="#i-bulb"/></svg></span>
          <div style="flex:1"><b>Know a word we’re missing?</b><small>Teach Vashasetu your dialect.</small></div>
          <button class="btn btn-line btn-sm" data-act="openContribute">Add</button>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== TRANSLATE ===================== -->
  <section class="screen" id="screen-translate">
    <div class="scroll">
      <header class="hero" style="padding-bottom:58px">
        <h1>Translate</h1>
        <p>Dialect · Standard Bangla · Banglish · English</p>
      </header>
      <div class="pull">
        <div class="langbar">
          <button class="lang-btn" id="from-btn" data-act="pickLang" data-side="from" aria-label="Translate from"><small>From</small><span id="from-label">Detect language</span></button>
          <button class="swap-btn" data-act="swapLang" aria-label="Swap languages"><svg class="ic"><use href="#i-swap"/></svg></button>
          <button class="lang-btn" id="to-btn" data-act="pickLang" data-side="to" aria-label="Translate to"><small>To</small><span id="to-label">Standard Bangla</span></button>
        </div>
        <div class="detect-line" id="detect-line"></div>

        <div class="card compose" id="compose">
          <div class="scan-row" id="scan-row">
            <img id="scan-thumb" alt="Selected image">
            <div class="scan-info"><div class="scan-status" id="scan-status">Reading image…</div><div class="scan-bar"><div class="scan-bar-fill" id="scan-bar-fill"></div></div></div>
            <button class="scan-close" data-act="clearScan" aria-label="Remove image"><svg class="ic"><use href="#i-x"/></svg></button>
          </div>
          <button class="clear-btn" id="clear-btn" data-act="clearText" aria-label="Clear text"><svg class="ic" style="width:15px;height:15px"><use href="#i-x"/></svg></button>
          <textarea id="dialect-input" rows="3" placeholder="Type in dialect, Bangla, Banglish or English…" aria-label="Text to translate"></textarea>
          <div class="wavebox" id="wavebox"><div class="wave" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div><span id="listen-label">Listening… speak now</span></div>
          <div class="translit" id="translit"></div>
          <input type="file" id="image-input" accept="image/*" hidden>
          <div class="actions3">
            <button class="side-btn" id="cam-btn" data-act="pickImage"><span class="sb-ic"><svg class="ic" style="width:24px;height:24px"><use href="#i-camera"/></svg></span>Scan photo</button>
            <button class="mic" id="mic-btn" data-act="toggleMic" aria-label="Speak"><svg class="ic"><use href="#i-mic"/></svg></button>
            <button class="side-btn" data-act="openContribute"><span class="sb-ic"><svg class="ic" style="width:24px;height:24px"><use href="#i-bulb"/></svg></span>Add word</button>
          </div>
        </div>

        <button class="btn btn-gold btn-block translate-go" id="translate-btn" data-act="doTranslate">Translate &amp; save <svg class="ic"><use href="#i-arrow"/></svg></button>
        <div class="live-hint">Translates as you type · Banglish is understood</div>

        <div class="card result" id="result-card">
          <div class="result-top">
            <span class="result-label" id="result-label">Standard Bangla · প্রমিত বাংলা</span>
            <span style="display:flex;gap:8px">
              <button class="round-btn" id="copy-btn" data-act="copyResult" aria-label="Copy"><svg class="ic" style="width:19px;height:19px"><use href="#i-copy"/></svg></button>
              <button class="round-btn speaker-btn" id="speak-btn" data-act="playAudio" aria-label="Listen"><svg class="ic" style="width:19px;height:19px"><use href="#i-speaker"/></svg></button>
            </span>
          </div>
          <div class="result-text" id="result-text"></div>
          <div class="result-roman" id="result-roman"></div>
          <div class="pairs" id="result-pairs"></div>
          <div class="notes" id="result-notes"></div>
          <div class="nomatch" id="no-match-note">Some words aren’t in the dictionary yet. Tap a dashed word to teach it to Vashasetu.</div>
          <div class="result-foot">
            <button class="link" data-act="openBridge">See the bridge →</button><button class="link" data-go="dictionary">Browse dictionary</button>
          </div>
        </div>

        <button class="card tr-wod" id="tr-wod" data-act="tryWod">
          <span class="qa-ic gold"><svg class="ic"><use href="#i-star"/></svg></span>
          <span style="flex:1"><small>Word of the day · আজকের শব্দ</small><b id="tr-wod-text">…</b></span>
          <svg class="ic" style="color:#B8893A"><use href="#i-arrow"/></svg>
        </button>
        <div class="centered"><button class="link" data-go="home">Explore dialects on the map</button></div>
      </div>
    </div>
  </section>

  <!-- ===================== BRIDGE (word breakdown) ===================== -->
  <section class="screen" id="screen-breakdown">
    <div class="scroll">
      <header class="hero" style="padding-bottom:52px">
        <button class="hero-back" data-go="translate"><svg class="ic" style="width:18px;height:18px"><use href="#i-back"/></svg> Back</button>
        <h1>The Bridge</h1>
        <p id="bridge-sub">See how each word crosses the bridge</p>
      </header>
      <div class="pull">
        <div class="card" id="bridge-card"></div>
      </div>
    </div>
  </section>

  <!-- ===================== HISTORY ===================== -->
  <section class="screen" id="screen-history">
    <div class="scroll">
      <header class="hero" style="padding-bottom:46px"><h1>History</h1><p>Tap a past translation to use it again</p></header>
      <div class="pull" style="margin-top:-30px" id="history-list"></div>
    </div>
  </section>

  <!-- ===================== CONTRIBUTE ===================== -->
  <section class="screen" id="screen-contribute">
    <div class="scroll">
      <header class="hero" style="padding-bottom:58px"><h1>Add a word</h1><p>Help Vashasetu understand more of Bangladesh</p></header>
      <div class="pull">
        <div class="card">
          <div class="tip"><svg class="ic"><use href="#i-bulb"/></svg><span>Every word you add is reviewed by an admin before it appears in the dictionary.</span></div>
          <div class="field"><label for="contrib-word">Dialect word or phrase</label><input class="input" id="contrib-word" placeholder="e.g. ক্যান আছন" autocomplete="off"></div>
          <div class="field"><label for="contrib-meaning">Standard Bangla meaning</label><input class="input" id="contrib-meaning" placeholder="e.g. কেমন আছেন" autocomplete="off"></div>
          <div class="field"><label for="contrib-dialect">Which dialect?</label>
            <select class="input" id="contrib-dialect">
              <option>Chattogram</option><option>Sylheti</option><option>Noakhali</option><option>Barishal</option><option>Mymensingh</option><option>Rajshahi</option><option>Rangpur</option><option>Dhaka</option><option>Khulna</option><option>Other</option>
            </select>
          </div>
          <button class="btn btn-gold btn-block" id="submit-btn" data-act="submitContribution">Submit for review</button>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== PROFILE ===================== -->
  <section class="screen" id="screen-profile">
    <div class="scroll">
      <header class="hero" style="padding-bottom:46px"><h1>Profile</h1><p>Your account and preferences</p></header>
      <div class="pull" style="margin-top:-24px" id="profile-body"></div>
    </div>
  </section>

  <!-- ===================== LOGIN ===================== -->
  <section class="screen" id="screen-login">
    <div class="scroll">
      <header class="hero" style="padding-bottom:58px">
        <button class="hero-back" data-go="choice"><svg class="ic" style="width:18px;height:18px"><use href="#i-back"/></svg> Back</button>
        <h1>Welcome back</h1><p>Log in to see your history and preferences</p>
      </header>
      <div class="pull">
        <div class="card">
          <div class="field"><label for="login-email">Email</label><input class="input" id="login-email" type="email" placeholder="you@example.com" autocomplete="email"></div>
          <div class="field"><label for="login-password">Password</label><div class="pw"><input class="input" id="login-password" type="password" placeholder="Your password" autocomplete="current-password"><button type="button" data-act="togglePw" data-target="login-password" aria-label="Show password"><svg class="ic"><use href="#i-eye"/></svg></button></div></div>
          <button class="btn btn-gold btn-block" id="login-btn" data-act="doLogin">Log In</button>
          <div class="centered">New here? <button class="link" data-go="register">Create an account</button></div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== REGISTER ===================== -->
  <section class="screen" id="screen-register">
    <div class="scroll">
      <header class="hero" style="padding-bottom:58px">
        <button class="hero-back" data-go="choice"><svg class="ic" style="width:18px;height:18px"><use href="#i-back"/></svg> Back</button>
        <h1>Join Vashasetu</h1><p>Build your own bridge between dialects</p>
      </header>
      <div class="pull">
        <div class="card">
          <div class="field"><label for="register-name">Full name</label><input class="input" id="register-name" placeholder="Your name" autocomplete="name"></div>
          <div class="field"><label for="register-email">Email</label><input class="input" id="register-email" type="email" placeholder="you@example.com" autocomplete="email"></div>
          <div class="field"><label for="register-password">Password</label><div class="pw"><input class="input" id="register-password" type="password" placeholder="At least 6 characters" autocomplete="new-password"><button type="button" data-act="togglePw" data-target="register-password" aria-label="Show password"><svg class="ic"><use href="#i-eye"/></svg></button></div></div>
          <div class="field"><label for="register-dialect">Your dialect</label>
            <select class="input" id="register-dialect"><option>Chattogram</option><option>Sylheti</option><option>Noakhali</option><option>Barishal</option><option>Mymensingh</option><option>Rajshahi</option></select>
          </div>
          <button class="btn btn-gold btn-block" id="register-btn" data-act="doRegister">Create account</button>
          <div class="legal">By signing up you agree to our <a href="privacy-policy.html" target="_blank" rel="noopener">Privacy Policy</a></div>
          <div class="centered">Already have an account? <button class="link" data-go="login">Log in</button></div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== QUIZ ===================== -->
  <section class="screen" id="screen-quiz">
    <div class="scroll">
      <header class="hero" style="padding-bottom:50px">
        <button class="hero-back" data-act="quitQuiz"><svg class="ic" style="width:18px;height:18px"><use href="#i-back"/></svg> Leave</button>
        <div class="qtop"><div class="qbar"><i id="qbar-fill"></i></div><span class="qscore"><svg class="ic"><use href="#i-star"/></svg><span id="qscore">0</span></span></div>
      </header>
      <div class="pull" id="quiz-body"></div>
    </div>
  </section>

  <!-- ===================== DICTIONARY ===================== -->
  <section class="screen" id="screen-dictionary">
    <div class="scroll" id="dict-scroll">
      <header class="hero" style="padding-bottom:60px"><h1>Dictionary</h1><p id="dict-sub">Search in Bangla, Banglish or English</p></header>
      <div class="pull">
        <div class="card dict-search">
          <svg class="ic"><use href="#i-search"/></svg>
          <input class="input" id="dict-q" placeholder="Try  valo,  water  or  ভাত" autocomplete="off" aria-label="Search the dictionary">
        </div>
        <div class="dchips scrolly" id="dict-chips"></div>
        <div class="dict-count" id="dict-count"></div>
        <div id="dict-list"></div>
        <div class="centered"><button class="btn btn-line btn-sm" id="dict-more" data-act="dictMore" style="display:none">Show more</button></div>
        <p class="credit">Dialect data: Vashantor / Unified-Bangla (CC BY 4.0) · Word lists: MinhasKamal/BengaliDictionary · Verified phrases: Wikivoyage</p>
      </div>
    </div>
  </section>

  <!-- ===================== NAV ===================== -->
  <nav class="nav" id="nav" aria-label="Main">
    <button data-go="home" data-tab="home"><span class="ni"><svg class="ic"><use href="#i-home"/></svg></span><span class="l">Home</span></button>
    <button data-go="translate" data-tab="translate"><span class="ni"><svg class="ic"><use href="#i-translate"/></svg></span><span class="l">Translate</span></button>
    <button data-go="history" data-tab="history"><span class="ni"><svg class="ic"><use href="#i-clock"/></svg></span><span class="l">History</span></button>
    <button data-go="dictionary" data-tab="dictionary"><span class="ni"><svg class="ic"><use href="#i-book"/></svg></span><span class="l">Words</span></button>
    <button data-go="profile" data-tab="profile"><span class="ni"><svg class="ic"><use href="#i-user"/></svg></span><span class="l">Profile</span></button>
  </nav>

  <div class="sheet" id="sheet" aria-hidden="true">
    <div class="sheet-bg" data-act="closeSheet"></div>
    <div class="sheet-panel" role="dialog" aria-modal="true"><div class="sheet-grab"></div><button class="sheet-x" data-act="closeSheet" aria-label="Close">×</button><div id="sheet-body"></div></div>
  </div>

  <div class="toast" id="toast" role="status" aria-live="polite"></div>
  <div class="confetti" id="confetti" aria-hidden="true"></div>
</div>

<script src="<?= asset('assets/js/bdmap.js') ?>"></script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
