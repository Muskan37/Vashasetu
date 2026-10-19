<?php
// ============================================================
// Vashasetu configuration — XAMPP (local) settings.
// XAMPP's MySQL has user "root" with a blank password, so these
// work out of the box. The database "vashasetu" and all tables
// are created for you by setup.php (open it once in the browser).
// ============================================================
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
            PDO::ATTR_PERSISTENT => true,   // reuse the MySQL connection between requests (faster on XAMPP)
        ];
        $last = null;
        // try 127.0.0.1 first (fast on Windows), then plain "localhost" as a fallback
        foreach (array_unique([DB_HOST, 'localhost']) as $host) {
            try {
                $pdo = new PDO('mysql:host=' . $host . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, $opts);
                break;
            } catch (PDOException $e) { $last = $e; }
        }
        if ($pdo === null) {
            http_response_code(500);
            echo json_encode(['error' => 'Database not ready. Start MySQL in XAMPP, then open http://localhost/vashasetu/setup.php once.']);
            exit;
        }
    }
    return $pdo;
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
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
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// The five supported dialects (what the app sends). Anything else is rejected or replaced.
function valid_dialect(string $d): bool { return in_array($d, ['Chattogram', 'Sylheti', 'Noakhali', 'Barishal', 'Mymensingh', 'Rajshahi'], true); }
