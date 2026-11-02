<?php
// PHPUnit bootstrap: loads the app's PHP code the same way the API does, without a web server.
// Unit and Integration tests call the functions directly; Http tests talk to the running app (BASE).
define('KEEP_SESSION_OPEN', true);
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
ob_start();
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/matching_engine.php';
require_once __DIR__ . '/../../api/word_evidence.php';
require_once __DIR__ . '/../../api/edit_lib.php';
ob_end_clean();
restore_exception_handler();          // config.php installs a JSON error handler for the API; PHPUnit reports errors itself

/** Same text in Unicode NFC (Bangla can be typed in two ways that look the same). */
function nfc(?string $s): ?string { return $s === null ? null : normalizer_normalize($s, Normalizer::FORM_C); }

/** Small HTTP client for the Http tests (no extra libraries needed). */
final class Http
{
    public static function base(): string { return rtrim(getenv('BASE') ?: 'http://127.0.0.1/vashasetu', '/'); }

    /** A made-up visitor address for this run, sent the way the Cloudflare tunnel sends it, so rate limits start fresh. */
    public static function ip(int $n): string
    {
        static $h = null;
        $h ??= random_int(0, 64 * 256 - 1);
        return sprintf('100.%d.%d.%d', 64 + ($h % 64), intdiv($h, 64) % 256, $n % 250 + 1);
    }

    /** @return array{0:int,1:array,2:array} status, decoded JSON body, response headers */
    public static function request(string $method, string $path, ?array $json = null, array $headers = [], ?string &$cookies = null, ?string $rawBody = null): array
    {
        $ch = curl_init(self::base() . $path);
        $h = array_merge(['Accept: application/json'], $headers);
        if ($json !== null || $rawBody !== null) $h[] = 'Content-Type: application/json';
        $respHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => $h,
            CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$respHeaders) {
                if (str_contains($line, ':')) { [$k, $v] = explode(':', $line, 2); $respHeaders[strtolower(trim($k))][] = trim($v); }
                return strlen($line);
            },
        ]);
        if ($json !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_UNICODE));
        if ($rawBody !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
        if ($cookies !== null && $cookies !== '') curl_setopt($ch, CURLOPT_COOKIE, $cookies);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        foreach ($respHeaders['set-cookie'] ?? [] as $c) {           // keep the session cookie, like a browser
            $pair = explode(';', $c, 2)[0];
            [$name] = explode('=', $pair, 2);
            $jar = array_filter(explode('; ', (string) $cookies), fn($x) => $x !== '' && !str_starts_with($x, $name . '='));
            $jar[] = $pair;
            $cookies = implode('; ', $jar);
        }
        return [$code, json_decode((string) $body, true) ?? ['_raw' => (string) $body], $respHeaders];
    }
}
