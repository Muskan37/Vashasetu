<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Input validation and the security helpers that do not need a database. */
final class ValidationAndSecurityTest extends TestCase
{
    private array $server;

    protected function setUp(): void { $this->server = $_SERVER; }
    protected function tearDown(): void { $_SERVER = $this->server; }

    public static function dialects(): array
    {
        $valid = ['Chattogram', 'Sylheti', 'Noakhali', 'Barishal', 'Mymensingh', 'Rajshahi', 'Rangpur', 'Narail', 'Kishoreganj', 'Narsingdi', 'Tangail'];
        $cases = [];
        foreach ($valid as $d) $cases["EP valid: $d"] = [$d, true];
        return $cases + [
            'EP invalid: not a supported district' => ['Dhaka', false],
            'EP invalid: lower case'               => ['chattogram', false],
            'EP invalid: leading space'            => [' Noakhali', false],
            'EP invalid: Bangla name'              => ['নোয়াখালী', false],
            'BVA empty'                            => ['', false],
        ];
    }

    /** @dataProvider dialects */
    public function testValidDialect(string $dialect, bool $expected): void
    {
        $this->assertSame($expected, valid_dialect($dialect));
    }

    public static function clientIps(): array
    {
        return [
            'EP tunnel on this computer: header is trusted'  => ['127.0.0.1', '203.0.113.9', '203.0.113.9'],
            'EP IPv6 loopback: header is trusted'            => ['::1', '203.0.113.9', '203.0.113.9'],
            'EP other computer: fake header is ignored'      => ['192.168.1.20', '9.9.9.9', '192.168.1.20'],
            'EP no header'                                   => ['192.168.1.20', null, '192.168.1.20'],
            'BVA header cut to 64 characters'                => ['127.0.0.1', str_repeat('a', 100), str_repeat('a', 64)],
        ];
    }

    /** @dataProvider clientIps */
    public function testClientIp(string $remote, ?string $cfHeader, string $expected): void
    {
        // Arrange
        $_SERVER['REMOTE_ADDR'] = $remote;
        unset($_SERVER['HTTP_CF_CONNECTING_IP']);
        if ($cfHeader !== null) $_SERVER['HTTP_CF_CONNECTING_IP'] = $cfHeader;
        // Act + Assert
        $this->assertSame($expected, vs_client_ip());
    }

    public static function httpsCases(): array
    {
        return [
            'EP plain HTTP'             => [[], false],
            'EP HTTPS on'               => [['HTTPS' => 'on'], true],
            'EP HTTPS off'              => [['HTTPS' => 'off'], false],
            'EP behind a proxy'         => [['HTTP_X_FORWARDED_PROTO' => 'https'], true],
            'EP through the CF tunnel'  => [['HTTP_CF_VISITOR' => '{"scheme":"https"}'], true],
        ];
    }

    /** @dataProvider httpsCases */
    public function testIsHttps(array $server, bool $expected): void
    {
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTP_CF_VISITOR']);
        $_SERVER = array_merge($_SERVER, $server);
        $this->assertSame($expected, vs_is_https());
    }

    public function testCommandLineCountsAsTheLocalComputer(): void
    {
        $this->assertTrue(vs_is_local_request());
    }

    public function testInPlaceholdersMatchesTheNumberOfValues(): void
    {
        $this->assertSame('?,?,?', in_placeholders([1, 2, 3]));
        $this->assertSame('?', in_placeholders(['x']));
    }
}
