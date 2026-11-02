<?php
// Security helpers shared by the API (config.php) and the HTML pages (index.php, learn.php).

/** Never show PHP warnings or file paths to visitors (XAMPP has display_errors = On); they go to the error log. */
function vs_hide_errors(): void {
    if (PHP_SAPI === 'cli') return;
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    if (!headers_sent()) header_remove('X-Powered-By');
}

/** Headers for HTML pages: no framing by other websites (clickjacking), no type sniffing,
 *  and only this app may use the microphone (voice input) and camera (Scan). */
function vs_page_headers(): void {
    if (headers_sent()) return;
    header('X-Frame-Options: SAMEORIGIN');
    header("Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: microphone=(self), camera=(self), geolocation=()');
}

/** A request that another website made the browser send (form or script on a different site).
 *  Browsers say so in Sec-Fetch-Site. Changes (POST/DELETE) from another site are refused (CSRF). */
function vs_block_cross_site_writes(): void {
    $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($m, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && strtolower($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Request from another website blocked.']);
        exit;
    }
}

/**
 * Simple per-address rate limit kept in MySQL. Returns true when the request may continue.
 * $bucket names the action (edit, register, contribute); $max requests per $seconds per address.
 */
function vs_rate_limit(PDO $pdo, string $bucket, int $max, int $seconds): bool {
    // The person sitting at the XAMPP computer (not through the tunnel) is not limited.
    if (in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true) && empty($_SERVER['HTTP_CF_CONNECTING_IP']) && empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return true;
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS rate_hits (id BIGINT AUTO_INCREMENT PRIMARY KEY, bucket VARCHAR(20) NOT NULL, ip VARCHAR(64) NOT NULL, at DATETIME NOT NULL, INDEX ix_b (bucket, ip, at)) ENGINE=InnoDB');
        if (random_int(1, 50) === 1) $pdo->exec('DELETE FROM rate_hits WHERE at < NOW() - INTERVAL 1 DAY');
        $ip = vs_client_ip();
        $q = $pdo->prepare('SELECT COUNT(*) FROM rate_hits WHERE bucket = ? AND ip = ? AND at > NOW() - INTERVAL ? SECOND');
        $q->execute([$bucket, $ip, $seconds]);
        if ((int) $q->fetchColumn() >= $max) return false;
        $pdo->prepare('INSERT INTO rate_hits (bucket, ip, at) VALUES (?, ?, NOW())')->execute([$bucket, $ip]);
    } catch (Throwable $e) { /* never block real users because the limit table failed */ }
    return true;
}
function vs_require_rate(PDO $pdo, string $bucket, int $max, int $seconds, string $msg): void {
    if (vs_rate_limit($pdo, $bucket, $max, $seconds)) return;
    http_response_code(429);
    echo json_encode(['error' => $msg]);
    exit;
}

/** Password rules: at least 8 characters, not one of the most common passwords, not the email itself. */
function vs_password_problem(string $pw, string $email = ''): ?string {
    // count letters, not bytes: a Bangla letter is 3 bytes in UTF-8
    $len = mb_strlen($pw, 'UTF-8');
    if ($len < 8) return 'Password must be at least 8 characters';
    if ($len > 200) return 'Password is too long';
    static $common = ['12345678', '123456789', '1234567890', 'password', 'password1', 'password123', 'qwerty123', 'qwertyuiop', '11111111', '00000000', '12341234',
        'iloveyou', 'abcd1234', 'abc12345', 'asdfghjkl', 'admin123', 'welcome1', 'letmein1', 'bangladesh', 'football', 'baseball', 'superman', 'sunshine', 'princess',
        'qwerty12', '1q2w3e4r', '87654321', '123123123', 'passw0rd', 'trustno1', 'dhaka123', 'vashasetu', 'vashasetu1'];
    if (in_array(strtolower($pw), $common, true)) return 'This password is too common. Please choose another one';
    if ($email !== '' && strtolower($pw) === strtolower($email)) return 'Password must not be your email address';
    return null;
}
