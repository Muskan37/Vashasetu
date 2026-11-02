<?php
// ============================================================
// Vashasetu configuration — XAMPP (local) settings.
// XAMPP's MySQL has user "root" with a blank password, so these
// work out of the box. The database "vashasetu" and all tables
// are created for you by setup.php (open it once in the browser).
// ============================================================
require_once __DIR__ . '/lib/security.php';
vs_hide_errors();
define('DB_HOST', '127.0.0.1');   // not "localhost": on Windows that can add a ~1 second IPv6 lookup delay to every request
define('DB_NAME', 'vashasetu');
define('DB_USER', 'root');
define('DB_PASS', '');   // XAMPP default: blank password

function get_db() {
    static $pdo = null;
    if ($pdo === null) {
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Not persistent: with persistent connections every Apache worker kept one MySQL connection open for ever,
            // so under heavy load all 151 MySQL connections were used up and a MySQL restart broke the open ones.
            PDO::ATTR_PERSISTENT => false,
        ];
        $last = null;
        $full = fn($e) => $e && ((int) ($e->errorInfo[1] ?? 0) === 1040 || str_contains($e->getMessage(), 'Too many connections'));
        // When MySQL is momentarily full (connections from finished requests are still closing), wait a little and
        // try again for up to about 2 seconds before telling the visitor the server is busy.
        for ($try = 0; $try < 12 && $pdo === null; $try++) {
            if ($try > 0) { if (!$full($last)) break; usleep(random_int(80000, 200000)); }
            // try 127.0.0.1 first (fast on Windows), then plain "localhost" as a fallback
            foreach (array_unique([DB_HOST, 'localhost']) as $host) {
                try {
                    $pdo = new PDO('mysql:host=' . $host . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, $opts);
                    break;
                } catch (PDOException $e) { $last = $e; if ($full($e)) break; }
            }
        }
        if ($pdo === null && $full($last)) {
            http_response_code(503); header('Retry-After: 2');   // MySQL is full for a moment: ask the browser to try again
            echo json_encode(['error' => 'The server is busy. Please try again in a moment.']);
            exit;
        }
        if ($pdo === null) {
            http_response_code(500);
            echo json_encode(['error' => 'Database not ready. Start MySQL in XAMPP, then open http://localhost/vashasetu/setup.php once.']);
            exit;
        }
    }
    return $pdo;
}

/** True when the request came through HTTPS, including through the Cloudflare tunnel. */
function vs_is_https(): bool {
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        || stripos($_SERVER['HTTP_CF_VISITOR'] ?? '', 'https') !== false;
}
/** The visitor's address (through the tunnel the real one is in CF-Connecting-IP). */
function vs_client_ip(): string {
    $ra = $_SERVER['REMOTE_ADDR'] ?? '';
    // Only the Cloudflare tunnel program running on this computer (it connects from 127.0.0.1) may tell us the
    // visitor's real address. Anyone else (e.g. on the same Wi-Fi) could fake the header, so it is ignored for them.
    if (in_array($ra, ['127.0.0.1', '::1'], true) && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return substr((string) $_SERVER['HTTP_CF_CONNECTING_IP'], 0, 64);
    return $ra;
}
/** Only someone sitting at this computer (not someone using the public tunnel link). */
function vs_is_local_request(): bool {
    if (PHP_SAPI === 'cli') return true;
    $ra = $_SERVER['REMOTE_ADDR'] ?? '';
    $viaProxy = isset($_SERVER['HTTP_CF_CONNECTING_IP']) || isset($_SERVER['HTTP_X_FORWARDED_FOR']) || isset($_SERVER['HTTP_CF_RAY']);
    return in_array($ra, ['127.0.0.1', '::1'], true) && !$viaProxy;
}
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
}

vs_block_cross_site_writes();   // CSRF: another website cannot make the browser change anything here

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');    // never accept a session ID the server did not create
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => vs_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    // Release the session lock straight away: PHP otherwise makes every request from the same browser
    // wait for the previous one to finish (this made live translate feel laggy). Only auth.php writes the session.
    if (!defined('KEEP_SESSION_OPEN')) session_write_close();
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function require_login() {
    if (current_user_id() === null) {
        http_response_code(401);
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
}

header('Content-Type: application/json; charset=utf-8');
// Safety net: a request with the wrong kind of value (for example a list where text is expected) used to stop
// PHP with an empty "500 server error". Answer with a clear JSON message instead.
set_exception_handler(function (Throwable $e) {
    if (!headers_sent()) { http_response_code($e instanceof TypeError || $e instanceof ValueError ? 400 : 500); header('Content-Type: application/json; charset=utf-8'); }
    echo json_encode(['error' => $e instanceof TypeError || $e instanceof ValueError ? 'Invalid input: every field must be plain text.' : 'Server error. Please try again.']);
});
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// The five supported dialects (what the app sends). Anything else is rejected or replaced.
function valid_dialect(string $d): bool { return in_array($d, ['Chattogram', 'Sylheti', 'Noakhali', 'Barishal', 'Mymensingh', 'Rajshahi', 'Rangpur', 'Narail', 'Kishoreganj', 'Narsingdi', 'Tangail'], true); }

/** High-trust learned word list (database/showcase.csv) used when a language has too few reviewed word records. */
function showcase_rows(?string $dialect = null): ?array {
    static $all = null;
    if ($all === null) {
        $all = [];
        $f = __DIR__ . '/../database/showcase.csv';
        if (is_file($f) && ($h = fopen($f, 'r'))) {
            fgetcsv($h);
            while (($r = fgetcsv($h)) !== false) {
                if (count($r) < 3) continue;
                $all[] = ['id' => crc32($r[0] . '|' . $r[1] . '|' . $r[2]), 'dialect_word' => $r[1], 'standard_meaning' => $r[2], 'dialect_type' => $r[0]];
            }
            fclose($h);
        }
    }
    if (!$all) return null;
    if ($dialect === null || $dialect === '' || strtolower($dialect) === 'any') return $all;
    $out = array_values(array_filter($all, fn($r) => $r['dialect_type'] === $dialect));
    return $out ?: null;
}
