<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Step 4: tests added after reading the coverage report. These endpoints and branches had no test at all:
 * profile settings, word contributions, wrong HTTP methods and invalid ids.
 */
final class ProfileAndOtherEndpointsTest extends TestCase
{
    private static int $n = 220;

    private function login(): string
    {
        $jar = '';
        Http::request('POST', '/api/auth.php?action=register', ['name' => 'Profile Tester', 'email' => 'pu_prof_' . bin2hex(random_bytes(3)) . '@example.com',
            'password' => 'Sonar#Bangla9'], ['CF-Connecting-IP: ' . Http::ip(++self::$n)], $jar);
        return $jar;
    }

    public static function profileUpdates(): array
    {
        return [
            'EP valid spoken language'     => ['update_spoken_language', ['spoken_language' => 'noakhali-bn'], 200, 'spoken_language', 'noakhali-bn'],
            'EP empty = no language'       => ['update_spoken_language', ['spoken_language' => ''], 200, 'spoken_language', null],
            'EP unknown spoken language'   => ['update_spoken_language', ['spoken_language' => 'klingon'], 400, null, null],
            'EP valid district'            => ['update_district', ['current_district' => 'dhaka'], 200, 'current_district', 'dhaka'],
            'EP unknown district'          => ['update_district', ['current_district' => 'atlantis'], 400, null, null],
            'EP valid preferred dialect'   => ['update_dialect', ['preferred_dialect' => 'Noakhali'], 200, null, null],
        ];
    }

    /** @dataProvider profileUpdates */
    public function testProfileUpdate(string $action, array $body, int $code, ?string $field, $value): void
    {
        $jar = $this->login();
        [$c, $b] = Http::request('POST', '/api/auth.php?action=' . $action, $body, [], $jar);
        $this->assertSame($code, $c, json_encode($b));
        if ($field !== null) {
            [, $me] = Http::request('GET', '/api/auth.php?action=me', null, [], $jar);
            $this->assertSame($value, $me['user'][$field]);
        }
    }

    public function testProfileUpdateNeedsLogin(): void
    {
        [$c] = Http::request('POST', '/api/auth.php?action=update_district', ['current_district' => 'dhaka']);
        $this->assertSame(401, $c);
    }

    public function testUnknownAuthActionIsRefused(): void
    {
        [$c, $b] = Http::request('POST', '/api/auth.php?action=delete_everything', []);
        $this->assertSame(400, $c);
        $this->assertSame('Unknown or missing action', $b['error']);
    }

    public static function contributions(): array
    {
        return [
            'EP valid contribution'       => [['dialect_word' => 'পিইউদান', 'meaning' => 'পরীক্ষা', 'dialect_type' => 'Noakhali'], 200],
            'EP missing meaning'          => [['dialect_word' => 'পিইউদান', 'meaning' => '', 'dialect_type' => 'Noakhali'], 400],
            'EP unsupported dialect'      => [['dialect_word' => 'পিইউদান', 'meaning' => 'পরীক্ষা', 'dialect_type' => 'Dhaka'], 400],
            'EP only HTML tags as word'   => [['dialect_word' => '<i></i>', 'meaning' => 'পরীক্ষা', 'dialect_type' => 'Noakhali'], 400],
        ];
    }

    /** @dataProvider contributions */
    public function testContribution(array $body, int $code): void
    {
        [$c] = Http::request('POST', '/api/contribute.php', $body, ['CF-Connecting-IP: ' . Http::ip(++self::$n)]);
        $this->assertSame($code, $c);
    }

    public function testContributionFromALoggedInUserIsAccepted(): void
    {
        $jar = $this->login();
        [$c, $b] = Http::request('POST', '/api/contribute.php', ['dialect_word' => 'পিইউদান', 'meaning' => 'পরীক্ষা', 'dialect_type' => 'Sylheti'], [], $jar);
        $this->assertSame(200, $c, json_encode($b));
    }

    public static function wrongMethods(): array
    {
        return [
            'GET on contribute'  => ['GET', '/api/contribute.php', 405],
            'POST on quiz'       => ['POST', '/api/quiz.php?dialect=Noakhali', 405],
            'PUT on history'     => ['PUT', '/api/history.php', 405],
        ];
    }

    /** @dataProvider wrongMethods */
    public function testWrongHttpMethod(string $method, string $path, int $code): void
    {
        [$c] = Http::request($method, $path, $method === 'GET' ? null : []);
        $this->assertSame($code, $c);
    }

    public static function historyIds(): array
    {
        return ['BVA id 0' => [0], 'EP negative id' => [-3], 'EP text id' => ['abc']];
    }

    /** @dataProvider historyIds */
    public function testDeletingHistoryNeedsAValidId($id): void
    {
        [$c] = Http::request('DELETE', '/api/history.php', ['id' => $id]);
        $this->assertSame(400, $c);
    }

    public function testWordOfTheDayDefaultsToChattogram(): void
    {
        // the first version of this test expected 400 without a language; the code uses Chattogram instead
        // (the 400 branch in api/word.php can never run: it is dead code, see the coverage notes)
        [$c, $b] = Http::request('GET', '/api/word.php');
        $this->assertSame(200, $c);
        $this->assertSame('Chattogram', $b['word']['dialect_type']);
        [$c] = Http::request('POST', '/api/word.php', []);
        $this->assertSame(405, $c);
    }
}
