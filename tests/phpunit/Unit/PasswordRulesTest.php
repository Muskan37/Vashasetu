<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * vs_password_problem(): the rule for new passwords.
 * Specification (README §13): at least 8 characters, at most 200, not a very common password, not the email address.
 * "Characters" means letters the user types, so a Bangla password counts its letters, not its bytes.
 */
final class PasswordRulesTest extends TestCase
{
    /** Equivalence classes and boundary values. Expected: true = refused, false = accepted. */
    public static function passwords(): array
    {
        return [
            // boundary values on the minimum length (8)
            'BVA empty (0 chars)'                 => ['', '', true],
            'BVA min-1 (7 chars)'                 => ['Abc#123', '', true],
            'BVA min (8 chars)'                   => ['Abc#1234', '', false],
            'BVA min+1 (9 chars)'                 => ['Abc#12345', '', false],
            // boundary values on the maximum length (200)
            'BVA max-1 (199 chars)'               => [str_repeat('Zq7!', 49) . 'abc', '', false],
            'BVA max (200 chars)'                 => [str_repeat('Zq7!', 50), '', false],
            'BVA max+1 (201 chars)'               => [str_repeat('Zq7!', 50) . 'x', '', true],
            // equivalence classes
            'EP valid ordinary password'          => ['Correct#Horse9', '', false],
            'EP common password'                  => ['password123', '', true],
            'EP common password in capitals'      => ['PASSWORD123', '', true],
            'EP same as the email'                => ['rahim@example.com', 'rahim@example.com', true],
            'EP same as the email, other case'    => ['Rahim@Example.com', 'rahim@example.com', true],
            'EP contains the email but longer'    => ['rahim@example.com!', 'rahim@example.com', false],
            // non-English passwords: the length is counted in letters
            'EP Bangla, 7 letters (too short)'    => ['কখগঘঙচছ', '', true],
            'EP Bangla, 8 letters'                => ['কখগঘঙচছজ', '', false],
            'EP Bangla, 200 letters (allowed)'    => [str_repeat('ক', 200), '', false],
        ];
    }

    /** @dataProvider passwords */
    public function testPasswordRule(string $password, string $email, bool $refused): void
    {
        // Arrange: $password and $email come from the data provider
        // Act
        $problem = vs_password_problem($password, $email);
        // Assert
        $this->assertSame($refused, $problem !== null, $problem ?? 'accepted');
    }

    public function testRefusalGivesAReadableReason(): void
    {
        $this->assertSame('Password must be at least 8 characters', vs_password_problem('short'));
        $this->assertSame('Password is too long', vs_password_problem(str_repeat('a', 201)));
        $this->assertSame('This password is too common. Please choose another one', vs_password_problem('12345678'));
        $this->assertSame('Password must not be your email address', vs_password_problem('a.b@c.org', 'a.b@c.org'));
    }
}
