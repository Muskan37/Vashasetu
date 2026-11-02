<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Integration: the account flow through the real web server (api/auth.php). */
final class AuthFlowTest extends TestCase
{
    private static string $run;
    private static int $n = 0;

    public static function setUpBeforeClass(): void { self::$run = bin2hex(random_bytes(3)); }

    private function register(array $body, ?string &$jar = null): array
    {
        $jar ??= '';
        return Http::request('POST', '/api/auth.php?action=register', $body, ['CF-Connecting-IP: ' . Http::ip(++self::$n)], $jar);
    }

    private static function email(string $tag): string { return "pu_{$tag}_" . self::$run . '@example.com'; }

    public function testRegisterMeLogoutLoginFlow(): void
    {
        $jar = '';
        $email = self::email('flow');
        // 1. register → logged in
        [$c, $b] = $this->register(['name' => 'Rahim', 'email' => $email, 'password' => 'Sonar#Bangla9'], $jar);
        $this->assertSame(200, $c, json_encode($b));
        $this->assertSame($email, $b['user']['email']);
        // 2. me → same user
        [$c, $b] = Http::request('GET', '/api/auth.php?action=me', null, [], $jar);
        $this->assertSame($email, $b['user']['email'] ?? null);
        // 3. logout → me is nobody
        Http::request('POST', '/api/auth.php?action=logout', [], [], $jar);
        [$c, $b] = Http::request('GET', '/api/auth.php?action=me', null, [], $jar);
        $this->assertNull($b['user'] ?? null);
        // 4. login again with the right password
        [$c, $b] = Http::request('POST', '/api/auth.php?action=login', ['email' => strtoupper($email), 'password' => 'Sonar#Bangla9'], ['CF-Connecting-IP: ' . Http::ip(++self::$n)], $jar);
        $this->assertSame(200, $c, 'email is not case-sensitive');
        // 5. wrong password
        [$c, $b] = Http::request('POST', '/api/auth.php?action=login', ['email' => $email, 'password' => 'wrong-password'], ['CF-Connecting-IP: ' . Http::ip(++self::$n)]);
        $this->assertSame(401, $c);
        $this->assertSame('Incorrect email or password', $b['error']);
    }

    public static function registrations(): array
    {
        $at = '@example.com';
        return [
            'EP valid'                              => [['name' => 'Karim', 'email' => '{e}', 'password' => 'Sonar#Bangla9'], 200],
            'EP missing name'                       => [['name' => '', 'email' => '{e}', 'password' => 'Sonar#Bangla9'], 400],
            'EP name only HTML tags'                => [['name' => '<b></b>', 'email' => '{e}', 'password' => 'Sonar#Bangla9'], 400],
            'EP invalid email'                      => [['name' => 'K', 'email' => 'not-an-email', 'password' => 'Sonar#Bangla9'], 400],
            'BVA password 7 characters'             => [['name' => 'K', 'email' => '{e}', 'password' => 'Abc#123'], 400],
            'BVA password 8 characters'             => [['name' => 'K', 'email' => '{e}', 'password' => 'Abc#1234'], 200],
            'EP Bangla password of 7 letters'       => [['name' => 'K', 'email' => '{e}', 'password' => 'কখগঘঙচছ'], 400],
            'BVA email 150 characters (max)'        => [['name' => 'K', 'email' => '{e150}', 'password' => 'Sonar#Bangla9'], 200],
            'BVA email 151 characters (max+1)'      => [['name' => 'K', 'email' => '{e151}', 'password' => 'Sonar#Bangla9'], 400],
            'EP password is a list, not text'       => [['name' => 'K', 'email' => '{e}', 'password' => ['x']], 400],
            'EP unknown district'                   => [['name' => 'K', 'email' => '{e}', 'password' => 'Sonar#Bangla9', 'current_district' => 'Atlantis'], 400],
        ];
    }

    /** @dataProvider registrations */
    public function testRegistrationValidation(array $body, int $expected): void
    {
        $tag = 'r' . (++self::$n);
        $local = 'pu_' . $tag . '_' . self::$run;
        // a valid address of an exact length: the part before @ may be at most 64 characters, so the domain is made
        // longer instead, from labels of at most 63 letters (the first version padded the local part and was invalid)
        $fill = function (int $len) use ($local): string {
            $need = $len - strlen($local) - strlen('@example.com');
            $labels = [];
            while ($need > 0) { $l = min(40, $need - 1); if ($l < 1) { $labels[count($labels) - 1] .= 'x'; break; } $labels[] = str_repeat('d', $l); $need -= $l + 1; }
            return $local . '@' . implode('.', $labels) . ($labels ? '.' : '') . 'example.com';
        };
        $body['email'] = strtr((string) (is_string($body['email']) ? $body['email'] : ''), ['{e150}' => $fill(150), '{e151}' => $fill(151), '{e}' => self::email($tag)]);
        [$c, $b] = $this->register($body);
        $this->assertSame($expected, $c, json_encode($b));
        if ($expected === 400) $this->assertArrayHasKey('error', $b, 'a clear message is given');
    }

    public function testDuplicateEmailIsRefused(): void
    {
        $email = self::email('dup');
        $this->register(['name' => 'A', 'email' => $email, 'password' => 'Sonar#Bangla9']);
        [$c, $b] = $this->register(['name' => 'B', 'email' => $email, 'password' => 'Sonar#Bangla9']);
        $this->assertSame(409, $c);
    }

    public function testLoginNeedsBothFields(): void
    {
        [$c] = Http::request('POST', '/api/auth.php?action=login', ['email' => '', 'password' => ''], ['CF-Connecting-IP: ' . Http::ip(++self::$n)]);
        $this->assertSame(400, $c);
    }
}
