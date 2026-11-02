<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Translation end to end inside PHP: detection → matching engine → word evidence → punctuation, with the real database.
 * Expected outputs come from the trusted sources: native-speaker sentences, the native-speaker-approved Noakhali
 * word list (1 Nov 2026) and standard Bangla grammar.
 */
final class TranslationFlowTest extends TestCase
{
    private static PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = get_db();
    }

    private function tr(string $text, string $src, string $tgt): array
    {
        return vs_translate_with_evidence(self::$pdo, $text, $src, $tgt, true);
    }

    public static function trustedPairs(): array
    {
        return [
            'Standard → Chattogram (native sentence)'   => ['আমি ভাত খাবো', 'standard', 'Chattogram', 'আঁই ভাত খাইয়ুম'],
            'Standard → Noakhali (native sentence)'     => ['আমি ভাত খাবো না', 'standard', 'Noakhali', 'আইঁ ভাত খাইতান ন'],
            'Noakhali → Standard (native sentence)'     => ['আইঁ ভাত খাইতান ন', 'Noakhali', 'standard', 'আমি ভাত খাবো না'],
            'Standard → Noakhali (approved word অনেক)'  => ['অনেক', 'standard', 'Noakhali', 'মেলা'],
            'Standard → Noakhali (approved word শিক্ষক)'=> ['শিক্ষক', 'standard', 'Noakhali', 'মাস্টর'],
            'Noakhali → Standard (approved word মেহমান)'=> ['মেহমান', 'Noakhali', 'standard', 'অতিথি'],
            'Noakhali → Standard (approved word কোত্তা)'=> ['কোত্তা', 'Noakhali', 'standard', 'কাপড়'],
            'Standard → Chattogram (approved word কেমন)' => ['কেমন', 'standard', 'Chattogram', 'ক্যাঁন'],
            'Chattogram → Standard (approved word ঔড়)'  => ['ঔড়', 'Chattogram', 'standard', 'শ্বশুর'],
            'Garo → Standard (approved word মি)'        => ['মি', 'garo', 'standard', 'ভাত'],
            'Garo → Standard (approved word গিচ্ছাক)'   => ['গিচ্ছাক', 'garo', 'standard', 'লাল'],
            'English → Standard (grammar)'              => ['I will eat rice', 'english', 'standard', 'আমি ভাত খাবো'],
            'English → Standard (question)'             => ['Do you eat fish?', 'english', 'standard', 'তুমি কি মাছ খাও?'],
            'English → Standard (negative past)'        => ['I did not go', 'english', 'standard', 'আমি যাইনি'],
            'English → Standard (-ie verb, fixed D27)'  => ['He dies', 'english', 'standard', 'সে মরে'],
            'Banglish → Standard'                       => ['ami bhat khabo', 'auto', 'standard', 'আমি ভাত খাবো'],
            'Chattogram → Noakhali (dialect to dialect)'=> ['আঁই ভাত খাইয়ুম', 'Chattogram', 'Noakhali', 'আইঁ ভাত খামু'],
        ];
    }

    /** @dataProvider trustedPairs */
    public function testTrustedTranslation(string $text, string $src, string $tgt, string $expected): void
    {
        // Act
        $r = $this->tr($text, $src, $tgt);
        // Assert
        $this->assertTrue($r['success'] ?? false);
        $this->assertSame(nfc($expected), nfc($r['translated_text']));
    }

    public static function reviewedLists(): array
    {
        // native-speaker reviews of 1-2 Nov 2026: whole Noakhali and Garo lists, first 300 Chattogram words
        return ['Noakhali' => ['Noakhali', 537, 6], 'Chattogram (first 300)' => ['Chattogram', 301, 43], 'Garo' => ['garo', 83, 5]];
    }

    /** @dataProvider reviewedLists */
    public function testReviewedWordListIsLoaded(string $lang, int $pairs, int $alternatives): void
    {
        $q = self::$pdo->prepare("SELECT meaning_status, COUNT(*) n FROM word_meanings WHERE language=? AND source_dataset='Native speaker reviewed list (Nov 2026)' GROUP BY meaning_status");
        $q->execute([$lang]);
        $c = array_map('intval', array_column($q->fetchAll(), 'n', 'meaning_status'));
        $this->assertSame($pairs, array_sum($c));
        $this->assertSame($alternatives, $c['native_speaker_alternative'] ?? 0);
    }

    public function testATextOfSeveralSentencesIsTranslatedSentenceBySentence(): void
    {
        // added after mutant M-23 survived: each sentence must match its own exact translation
        $parts = ['আমি ভাত খাবো।', 'তুমি কোথায় যাবে?', 'আমি ভাত খাবো না।'];
        $one = array_map(fn($p) => trim($this->tr($p, 'standard', 'Noakhali')['translated_text']), $parts);
        $all = $this->tr(implode(' ', $parts), 'standard', 'Noakhali');
        $this->assertSame(nfc(implode(' ', $one)), nfc($all['translated_text']));
        // the third sentence is a native speaker's exact sentence, and the result must say so
        $this->assertContains("Exact sentence from a native speaker's list.", $all['notes']);
    }

    public function testEveryWordGetsAWordBox(): void
    {
        $r = $this->tr('আমি ভাত খাবো', 'standard', 'Chattogram');
        $words = $r['word_meanings'] ?? $r['words'] ?? [];
        $this->assertCount(3, $words, 'one word box per input word');
    }

    public function testUnknownLanguageIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->tr('আমি ভাত খাবো', 'standard', 'Klingon');
    }

    public function testFormerlyCrashingSentenceNowTranslates(): void
    {
        // D26: "lying" pointed to a verb with no Bangla forms and stopped PHP with a TypeError
        $r = $this->tr('I am lying', 'english', 'standard');
        $this->assertTrue($r['success'] ?? false);
        $this->assertNotSame('', $r['translated_text']);
    }

    public function testEnglishPlaceEndingAfterVowelLetter(): void
    {
        // D25: "বইে" (a vowel sign on a vowel letter) is not valid Bangla
        $r = $this->tr('I went to the book', 'english', 'standard');
        $this->assertStringNotContainsString("ই\u{09C7}", $r['translated_text']);
    }

    public function testNoakhaliWordListIsFullyNativeApproved(): void
    {
        // Arrange + Act
        $q = self::$pdo->query("SELECT meaning_status, COUNT(*) n FROM word_meanings WHERE language='Noakhali' AND source_dataset='Native speaker reviewed list (Nov 2026)' GROUP BY meaning_status");
        $counts = array_column($q->fetchAll(), 'n', 'meaning_status');
        // Assert: 537 approved words, of which 6 are approved alternative spellings
        $this->assertSame(537, array_sum(array_map('intval', $counts)));
        ksort($counts);
        $this->assertSame(['native_speaker_alternative', 'native_speaker_reported'], array_keys($counts));
        $this->assertSame(6, (int) $counts['native_speaker_alternative']);
    }
}
