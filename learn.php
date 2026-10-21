<?php
// A logged-in account is required for learning progress in V6.
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user_id'])) { header('Location: index.php#login', true, 302); exit; }
$embedded = isset($_GET['embedded']) && $_GET['embedded'] === '1';
function asset_v($p) { return htmlspecialchars($p, ENT_QUOTES) . '?v=' . @filemtime(__DIR__ . '/' . $p); } ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0f5c56"><title>Learn Languages · VashaSetu</title><link rel="stylesheet" href="<?= asset_v('assets/css/learn.css') ?>"></head>
<body class="<?= $embedded ? 'embedded-learn' : '' ?>">
<?php if (!$embedded): ?><header class="top"><a class="back" href="index.php">← VashaSetu</a><strong>ভাষাসেতু · Learn together</strong><span>★</span></header><?php endif; ?>
<main class="wrap">
 <section class="hero">
  <small>YOU CHOOSE THE LANGUAGE PATH</small>
  <h1>Learn any supported language from another</h1>
  <p>Choose what you speak and what you want to learn. Progress is saved independently for every language pair.</p>
  <div class="coursePicker">
   <div><label for="sourceLang">I speak / My teaching language</label><select id="sourceLang" aria-label="Language I speak"><option value="">Loading…</option></select></div>
   <button type="button" class="swap" id="swapLang" aria-label="Swap the source and learning languages" title="Swap languages">⇄</button>
   <div><label for="targetLang">I want to learn</label><select id="targetLang" aria-label="Language I want to learn"><option value="">Loading…</option></select></div>
  </div>
  <div class="chosenCourse" id="chosenCourse" aria-live="polite"></div>
  <div class="notice" id="courseNotice">Verified courses, research previews and data availability will appear here.</div>
 </section>
 <section id="unitsView"><h2>Your learning path</h2><p class="minor">Sounds → letters → phonetics → words → sentences → conversations. A stage opens as verified lessons become available. Research-only flashcards do not award progress.</p><div class="unitList" id="unitList" aria-live="polite">Loading courses…</div></section>
 <section id="exerciseView" hidden><button type="button" class="plain" id="goBack">← Back to learning path</button><div class="smallrow"><span id="exerciseTitle">Lesson</span><span id="counter"></span></div><div class="progressTrack"><div id="progressBar"></div></div>
 <article class="card"><p class="prompt" id="prompt"></p><div class="sound" id="sound"><span id="symbol" class="symbol"></span><div class="subscript" id="soundIpa"></div><button type="button" class="listen" id="listenBtn" hidden>🔊 Listen</button></div><div id="answers" class="answers"></div><div id="feedback" role="status" aria-live="polite"></div><button class="next" id="nextBtn" hidden>Continue →</button></article></section>
 <section id="previewView" hidden><button type="button" class="plain" id="previewBack">← Back to learning path</button><div class="smallrow"><h2 id="previewTitle">Research flashcards</h2><span id="previewCount"></span></div><p class="notice previewWarning" id="previewWarning"></p>
 <div id="previewCards" aria-live="polite"></div><p class="minor">These are reference examples, not checked lessons. Audio and dialect-specific pronunciation require authentic recordings and native-speaker review.</p></section>
 <footer>Indigenous languages are never treated as Bangla dialects. Only the selected source and target language datasets are used, with Standard Bangla as an explicitly labeled reference bridge. Unreviewed research examples are not graded. The learner's first language requires community-reviewed prompts for full localized instruction.</footer>
</main><script src="<?= asset_v('assets/js/learn.js') ?>"></script></body></html>
