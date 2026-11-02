<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Step 3: defect categories the first suite did not cover.
 *  - Authorisation between users (can one user touch another user's data? "IDOR")
 *  - Text typed in a different but equivalent Unicode form (ে + া instead of ো)
 *  - Learning actions without logging in
 *  - Banglish typed with Roman full stops
 */
final class GapTest extends TestCase
{
    private function user(string $tag, int $ipNo): string
    {
        $jar = '';
        Http::request('POST', '/api/auth.php?action=register', ['name' => $tag, 'email' => 'pu_gap_' . $tag . '_' . bin2hex(random_bytes(3)) . '@example.com',
            'password' => 'Sonar#Bangla9'], ['CF-Connecting-IP: ' . Http::ip($ipNo)], $jar);
        return $jar;
    }

    public function testAUserCannotDeleteAnotherUsersHistory(): void
    {
        // Arrange: A saves a translation; B is a different user
        $a = $this->user('a', 201); $b = $this->user('b', 202);
        Http::request('POST', '/api/translate.php', ['text' => 'আমি ভাত খাবো', 'source' => 'standard', 'target' => 'Sylheti', 'log' => true], [], $a);
        [, $h] = Http::request('GET', '/api/history.php', null, [], $a);
        $id = (int) $h['history'][0]['id'];
        // Act: B tries to delete A's entry by its id
        [$c, $res] = Http::request('DELETE', '/api/history.php', ['id' => $id], [], $b);
        // Assert: nothing deleted, A still has it, B cannot see it
        $this->assertFalse($res['deleted'] ?? true);
        [, $h] = Http::request('GET', '/api/history.php', null, [], $a);
        $this->assertSame($id, (int) $h['history'][0]['id']);
        [, $hb] = Http::request('GET', '/api/history.php', null, [], $b);
        $this->assertSame([], $hb['history']);
    }

    public function testAGuestCannotSeeAUsersHistory(): void
    {
        $a = $this->user('c', 203);
        Http::request('POST', '/api/translate.php', ['text' => 'তুমি কোথায়', 'source' => 'standard', 'target' => 'Sylheti', 'log' => true], [], $a);
        [, $h] = Http::request('GET', '/api/history.php');
        $this->assertSame([], $h['history']);
    }

    public function testSplitVowelSignIsTreatedLikeTheNormalLetter(): void
    {
        // ো typed as ে + া (some Android keyboards). Unicode says both are the same letter. (D31)
        $split = str_replace("\u{09CB}", "\u{09C7}\u{09BE}", 'আমি ভাত খাবো');
        [, $one] = Http::request('POST', '/api/translate.php', ['text' => 'আমি ভাত খাবো', 'source' => 'standard', 'target' => 'Chattogram']);
        [, $two] = Http::request('POST', '/api/translate.php', ['text' => $split, 'source' => 'standard', 'target' => 'Chattogram']);
        $this->assertSame($one['translated_text'], $two['translated_text']);
        [, $d] = Http::request('GET', '/api/dictionary.php?' . http_build_query(['dialect' => 'Noakhali', 'q' => str_replace("\u{09CB}", "\u{09C7}\u{09BE}", 'খাবো')]));
        $this->assertGreaterThan(0, $d['total']);
    }

    public function testLearnAnswerNeedsLogin(): void
    {
        // for POST the learning API reads "action" from the JSON body (the first version of this test put it in the URL)
        [$c, $b] = Http::request('POST', '/api/learn.php', ['action' => 'course_answer', 'id' => 1, 'kind' => 'core', 'guess' => 'x']);
        $this->assertSame(401, $c);
    }

    public function testBanglishFullStopBecomesDari(): void
    {
        // D32: "ami bhat khabo. tumi kothay?" gave "আমি ভাত খাবো. তুমি কোথায়?" with a Roman full stop
        [, $b] = Http::request('POST', '/api/translate.php', ['text' => 'ami bhat khabo. tumi kothay?', 'source' => 'auto', 'target' => 'standard']);
        $this->assertSame(nfc('আমি ভাত খাবো। তুমি কোথায়?'), nfc($b['translated_text']));
    }

    public function testTitleDotIsNotASentenceEnd(): void
    {
        // D30: "Mr. Karim is here." gave "মি। Karim এখানে আছে।"
        [, $b] = Http::request('POST', '/api/translate.php', ['text' => 'Mr. Karim is here.', 'source' => 'english', 'target' => 'standard']);
        $this->assertStringNotContainsString('মি।', $b['translated_text']);
        $this->assertSame(1, substr_count($b['translated_text'], '।'));
    }
}
