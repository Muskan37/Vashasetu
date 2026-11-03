<?php
// A logged-in account is required for learning progress in V6.
require_once __DIR__ . '/api/lib/security.php';
vs_hide_errors(); vs_page_headers();
ini_set('session.use_strict_mode', '1');
if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || stripos($_SERVER['HTTP_CF_VISITOR'] ?? '', 'https') !== false;
    session_set_cookie_params(['lifetime' => 60 * 60 * 24 * 30, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
if (empty($_SESSION['user_id'])) { header('Location: index.php#login', true, 302); exit; }
$embedded = isset($_GET['embedded']) && $_GET['embedded'] === '1';
function asset_v($p) { return htmlspecialchars($p, ENT_QUOTES) . '?v=' . @filemtime(__DIR__ . '/' . $p); } ?>
<!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0f5c56"><link rel="manifest" href="manifest.json"><link rel="apple-touch-icon" href="icons/icon-192.png"><title>ভাষাসেতু · শিখি</title><link rel="stylesheet" href="<?= asset_v('assets/css/learn.css') ?>"></head>
<body class="<?= $embedded ? 'embedded-learn' : '' ?>">
<?php if (!$embedded): ?><header class="top"><a class="back" href="index.php" data-t="top_back">← ভাষাসেতু</a><strong data-t="top_title">ভাষাসেতু</strong><span>★</span></header><?php endif; ?>
<main class="wrap">
 <section class="hero pairHero">
  <div class="pairStatement"><span class="chosenCourse" id="chosenCourse" aria-live="polite"></span><button type="button" class="changePair" id="changePair" aria-expanded="false">⇄</button></div>
  <div class="coursePicker pairLine" id="pairPicker" hidden>
   <select id="sourceLang" data-t-label="sel_src" aria-label="🗣"><option value="">⏳</option></select>
   <span class="arrow" aria-hidden="true">→</span>
   <select id="targetLang" data-t-label="sel_tgt" aria-label="🎯"><option value="">⏳</option></select>
   <button type="button" class="swap" id="swapLang" data-t-label="swap" aria-label="⇄">⇄</button>
  </div>
  <div class="notice" id="courseNotice" hidden></div>
 </section>
 <section id="unitsView"><h2 data-t="path_title">🔊 → অ → 📖 → 💬</h2><p class="minor" data-t="path_minor"></p><div class="unitList" id="unitList" aria-live="polite">⏳</div></section>
 <section id="exerciseView" hidden><button type="button" class="plain" id="goBack" data-t="back_path">←</button><div class="smallrow"><span id="exerciseTitle"></span><span id="counter"></span></div><div class="progressTrack"><div id="progressBar"></div></div>
 <article class="card"><p class="prompt" id="prompt"></p><div class="sound" id="sound"><span id="symbol" class="symbol"></span><div class="nativeHint" id="nativeHint" hidden></div><div class="subscript" id="soundIpa"></div><button type="button" class="listen" id="listenBtn" hidden>🔊</button></div><div id="answers" class="answers"></div><div id="feedback" role="status" aria-live="polite"></div><button class="next" id="nextBtn" hidden>→</button></article></section>
 <section id="previewView" hidden><button type="button" class="plain" id="previewBack" data-t="back_path">←</button><div class="smallrow"><h2 id="previewTitle">🃏</h2><span id="previewCount"></span></div><p class="notice previewWarning" id="previewWarning"></p>
 <div id="previewCards" aria-live="polite"></div><p class="minor" data-t="flash_minor"></p></section>
 <footer data-t="footer"></footer>
</main><script src="<?= asset_v('assets/js/learn.js') ?>"></script><script>if('serviceWorker' in navigator){window.addEventListener('load',()=>navigator.serviceWorker.register('sw.js').catch(()=>{}));}</script>
</body></html>
