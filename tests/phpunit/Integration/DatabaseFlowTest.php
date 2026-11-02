<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Flows that change the database, run inside a transaction that is rolled back afterwards,
 * so the real dictionary is never changed by the tests.
 */
final class DatabaseFlowTest extends TestCase
{
    private PDO $pdo;
    private array $server;

    protected function setUp(): void
    {
        $this->pdo = get_db();
        $this->pdo->beginTransaction();
        $this->server = $_SERVER;
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) $this->pdo->rollBack();
        $_SERVER = $this->server;
    }

    public function testAddedDialectWordIsUsedByTheTranslatorThenDeleted(): void
    {
        // Arrange: a made-up Sylheti word for পরীক্ষা
        $word = 'টেস্টশব্দ' . random_int(1000, 9999);
        // Act 1: save it the way the Words screen does
        vs_apply_word_edit($this->pdo, 'Sylheti', $word, 'পরীক্ষা', 'save');
        $saved = $this->pdo->prepare("SELECT standard_word, meaning_status FROM word_meanings WHERE language='Sylheti' AND source_word=?");
        $saved->execute([$word]);
        // Assert 1: stored as a native-speaker word, and the translator now uses it
        $this->assertSame(['standard_word' => 'পরীক্ষা', 'meaning_status' => 'native_speaker_reported'], $saved->fetch());
        $r = vs_translate_with_evidence($this->pdo, 'পরীক্ষা', 'standard', 'Sylheti', true);
        $this->assertSame($word, $r['translated_text']);
        // Act 2: delete it
        vs_apply_word_edit($this->pdo, 'Sylheti', $word, '', 'delete');
        $saved->execute([$word]);
        // Assert 2: gone
        $this->assertFalse($saved->fetch());
    }

    public function testEditingAWordReplacesItsOldMeaning(): void
    {
        $word = 'টেস্টশব্দ' . random_int(1000, 9999);
        vs_apply_word_edit($this->pdo, 'Noakhali', $word, 'ভুলমানে', 'save');
        vs_apply_word_edit($this->pdo, 'Noakhali', $word, 'ঠিকমানে', 'save');
        $q = $this->pdo->prepare("SELECT standard_word FROM word_meanings WHERE language='Noakhali' AND source_word=?");
        $q->execute([$word]);
        $this->assertSame(['ঠিকমানে'], $q->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testPhraseEditIsFoundAsAnExactSentence(): void
    {
        $phrase = 'টেস্ট বাক্য ' . random_int(1000, 9999);
        vs_apply_word_edit($this->pdo, 'Chattogram', $phrase . '?', 'পরীক্ষার বাক্য', 'save', true);
        $hit = vs_exact_parallel($this->pdo, 'Chattogram', $phrase);
        $this->assertNotNull($hit, 'the saved phrase is matched without its question mark');
    }

    public static function rateLimits(): array
    {
        // max = 3 requests per window
        return [
            'BVA below the limit (2 of 3)'  => [2, true],
            'BVA at the limit (3 of 3)'     => [3, true],
            'BVA above the limit (4 of 3)'  => [4, false],
        ];
    }

    /** @dataProvider rateLimits */
    public function testRateLimitAllowsExactlyMaxRequests(int $requests, bool $lastAllowed): void
    {
        // Arrange: a visitor from another computer (the XAMPP computer itself is never limited)
        $_SERVER['REMOTE_ADDR'] = '198.51.100.' . random_int(1, 250);
        $bucket = 't' . random_int(100000, 999999);
        $this->pdo->commit();                          // CREATE TABLE IF NOT EXISTS inside vs_rate_limit ends a transaction anyway
        // Act
        $last = null;
        for ($i = 0; $i < $requests; $i++) $last = vs_rate_limit($this->pdo, $bucket, 3, 60);
        // Assert
        $this->assertSame($lastAllowed, $last);
        $this->pdo->prepare('DELETE FROM rate_hits WHERE bucket = ?')->execute([$bucket]);
    }

    public function testLocalComputerIsNeverRateLimited(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
        $this->pdo->commit();
        $ok = true;
        for ($i = 0; $i < 5; $i++) $ok = $ok && vs_rate_limit($this->pdo, 'tlocal', 1, 60);
        $this->assertTrue($ok);
    }
}
