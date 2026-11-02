<?php
// Text-to-speech for phones that have no Bangla voice installed.
// The browser plays the MP3 this returns, the same way Duolingo and Google Translate play
// sound generated on a server, so the user does not need to install anything.
//
// Order: 1) cached clip on this computer, 2) Google Cloud Text-to-Speech if a key is set
// in GOOGLE_TTS_KEY below (official API, free tier), 3) Google Translate's public voice.
// Clips are cached in database/tts_cache so each sentence is fetched only once.

const GOOGLE_TTS_KEY = '';          // optional: Google Cloud API key with Text-to-Speech enabled
const MAX_CHARS = 400;              // longest text spoken in one request
const NEW_CLIPS_PER_10_MIN = 120;   // per internet address, to stop abuse through the public link

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function tts_fail(int $code, string $msg): void {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

$text = trim((string)($_GET['q'] ?? ''));
$lang = ($_GET['lang'] ?? 'bn') === 'en' ? 'en' : 'bn';
$text = preg_replace('/\s+/u', ' ', $text);
if ($text === '') tts_fail(400, 'No text');
if (mb_strlen($text) > MAX_CHARS) $text = mb_substr($text, 0, MAX_CHARS);

$dir = __DIR__ . '/../database/tts_cache';
if (!is_dir($dir)) @mkdir($dir, 0775, true);
$file = $dir . '/' . sha1($lang . '|' . $text) . '.mp3';

if (!is_file($file) || filesize($file) < 200) {
    // simple per-address limit for clips that are not cached yet
    $ip = preg_replace('/[^0-9a-fA-F:.]/', '', $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'x');
    $lim = $dir . '/rate_' . md5($ip) . '.txt';
    $now = time();
    $hits = is_file($lim) ? array_filter(array_map('intval', explode("\n", (string)@file_get_contents($lim))), fn($t) => $t > $now - 600) : [];
    if (count($hits) >= NEW_CLIPS_PER_10_MIN) tts_fail(429, 'Too many requests, try again in a few minutes');
    $hits[] = $now;
    @file_put_contents($lim, implode("\n", $hits));

    $audio = GOOGLE_TTS_KEY !== '' ? tts_cloud($text, $lang) : '';
    if ($audio === '') $audio = tts_translate($text, $lang);
    if ($audio === '') tts_fail(502, 'Voice service not reachable. Check the internet connection of the computer running Vashasetu.');
    @file_put_contents($file, $audio);
} else {
    $audio = file_get_contents($file);
}

header('Content-Type: audio/mpeg');
header('Content-Length: ' . strlen($audio));
header('Cache-Control: public, max-age=2592000');
echo $audio;

function tts_get(string $url, ?string $post = null, array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Mobile Safari/537.36',
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); }
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return [$code, $type, $body === false ? '' : $body];
}

// Official Google Cloud Text-to-Speech (needs GOOGLE_TTS_KEY).
function tts_cloud(string $text, string $lang): string {
    $voice = $lang === 'en' ? ['languageCode' => 'en-US'] : ['languageCode' => 'bn-IN', 'name' => 'bn-IN-Standard-A'];
    $req = json_encode(['input' => ['text' => $text], 'voice' => $voice, 'audioConfig' => ['audioEncoding' => 'MP3', 'speakingRate' => 0.9]]);
    [$code, , $body] = tts_get('https://texttospeech.googleapis.com/v1/text:synthesize?key=' . urlencode(GOOGLE_TTS_KEY), $req, ['Content-Type: application/json']);
    if ($code !== 200) return '';
    $j = json_decode($body, true);
    return isset($j['audioContent']) ? (string)base64_decode($j['audioContent']) : '';
}

// Google Translate's public voice (no key; long text is spoken in pieces of up to 190 characters).
function tts_translate(string $text, string $lang): string {
    $parts = [];
    $buf = '';
    foreach (preg_split('/(?<=[।.?!,;])\s+|\s+/u', $text) as $w) {
        if ($w === '') continue;
        if (mb_strlen($buf . ' ' . $w) > 190 && $buf !== '') { $parts[] = $buf; $buf = $w; }
        else $buf = $buf === '' ? $w : $buf . ' ' . $w;
    }
    if ($buf !== '') $parts[] = $buf;
    $out = '';
    foreach ($parts as $i => $p) {
        $url = 'https://translate.google.com/translate_tts?ie=UTF-8&client=tw-ob&tl=' . $lang
             . '&total=' . count($parts) . '&idx=' . $i . '&textlen=' . mb_strlen($p) . '&q=' . rawurlencode($p);
        [$code, $type, $body] = tts_get($url);
        if ($code !== 200 || stripos($type, 'audio') === false || strlen($body) < 200) return '';
        $out .= $body;   // MP3 frames can be joined directly
    }
    return $out;
}
