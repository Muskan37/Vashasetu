<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Integration: POST api/translate.php through the web server, including input validation at its boundaries. */
final class TranslateApiTest extends TestCase
{
    private function post(array $body): array { return Http::request('POST', '/api/translate.php', $body); }

    public function testNormalTranslation(): void
    {
        [$c, $b] = $this->post(['text' => 'আমি ভাত খাবো', 'source' => 'standard', 'target' => 'Chattogram']);
        $this->assertSame(200, $c);
        $this->assertSame(nfc('আঁই ভাত খাইয়ুম'), nfc($b['translated_text']));
    }

    public static function invalidInputs(): array
    {
        return [
            'EP no text field'               => [['source' => 'standard', 'target' => 'Chattogram'], 400],
            'BVA empty text'                 => [['text' => ''], 400],
            'BVA only spaces'                => [['text' => "   \n "], 400],
            'EP unknown target language'     => [['text' => 'আমি', 'source' => 'standard', 'target' => 'Klingon'], 400],
            'EP unknown source language'     => [['text' => 'আমি', 'source' => 'Klingon', 'target' => 'standard'], 400],
            'EP text is a list'              => [['text' => ['আমি'], 'source' => 'standard', 'target' => 'Noakhali'], 400],
        ];
    }

    /** @dataProvider invalidInputs */
    public function testInvalidInputGivesA400WithAMessage(array $body, int $expected): void
    {
        [$c, $b] = $this->post($body);
        $this->assertSame($expected, $c);
        $this->assertNotEmpty($b['error'] ?? '');
    }

    public static function lengths(): array
    {
        // the API translates at most 500 characters; longer text is cut, never refused
        return [
            'BVA 1 character'        => [1, 1],
            'BVA 499 characters'     => [499, 499],
            'BVA 500 characters'     => [500, 500],
            'BVA 501 characters'     => [501, 500],
            'EP 3,000 characters'    => [3000, 500],
        ];
    }

    /** @dataProvider lengths */
    public function testTextLengthLimit(int $length, int $kept): void
    {
        $text = mb_substr(str_repeat('ভাত ', 1000), 0, $length);
        [$c, $b] = $this->post(['text' => $text, 'source' => 'standard', 'target' => 'Noakhali']);
        $this->assertSame(200, $c);
        $this->assertSame($kept, mb_strlen(rtrim($b['original_text'] ?? '', ' ')) + (str_ends_with(mb_substr($text, 0, $kept), ' ') ? 1 : 0));
    }

    public function testGetIsNotAllowed(): void
    {
        [$c] = Http::request('GET', '/api/translate.php');
        $this->assertSame(405, $c);
    }

    public function testBrokenJsonIsHandled(): void
    {
        [$c, $b] = Http::request('POST', '/api/translate.php', null, [], $none, '{"text": "আমি');
        $this->assertSame(400, $c);
        $this->assertArrayHasKey('error', $b);
    }

    public function testTranslationIsSavedInHistoryForTheUser(): void
    {
        // Arrange: a logged-in user
        $jar = '';
        $email = 'pu_hist_' . bin2hex(random_bytes(3)) . '@example.com';
        Http::request('POST', '/api/auth.php?action=register', ['name' => 'H', 'email' => $email, 'password' => 'Sonar#Bangla9'], ['CF-Connecting-IP: ' . Http::ip(240)], $jar);
        // Act: translate with log=true, then read the history
        Http::request('POST', '/api/translate.php', ['text' => 'আমি ভাত খাবো', 'source' => 'standard', 'target' => 'Chattogram', 'log' => true], [], $jar);
        [$c, $b] = Http::request('GET', '/api/history.php', null, [], $jar);
        // Assert
        $this->assertSame(200, $c);
        $this->assertSame(nfc('আঁই ভাত খাইয়ুম'), nfc($b['history'][0]['translated_text'] ?? ''));
        // Act + Assert: delete it again
        [$c] = Http::request('DELETE', '/api/history.php', ['id' => (int) $b['history'][0]['id']], [], $jar);
        $this->assertSame(200, $c);
        [$c, $b] = Http::request('GET', '/api/history.php', null, [], $jar);
        $this->assertSame([], $b['history']);
    }
}
