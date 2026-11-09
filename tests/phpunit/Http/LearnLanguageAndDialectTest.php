<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regression tests for D36 (dialect-to-dialect word boxes) and D37 (learning page instructions in the learner's language).
 * Expected values come from the native-speaker reviewed word lists (Nov 2026) and the Standard Bangla instruction table.
 */
final class LearnLanguageAndDialectTest extends TestCase
{
    public function testDialectToDialectWordBoxesShowTheTargetWord(): void
    {
        // Arrange + Act
        [$c, $b] = Http::request('POST', '/api/translate.php', ['text' => 'আঁই ভাত খামু', 'source' => 'Noakhali', 'target' => 'Chattogram']);
        // Assert: every box and every Bridge pair carries the Chattogram word, not the Standard word used in between
        $this->assertSame(200, $c);
        $this->assertSame([nfc('আঁই'), 'ভাত', nfc('খাইয়ুম')], array_map('nfc', array_column($b['word_meanings'], 'target_word')));
        $this->assertSame([nfc('আঁই'), 'ভাত', nfc('খাইয়ুম')], array_map('nfc', array_column($b['breakdown'], 'tgt')));
        $this->assertSame(['আমি', 'ভাত', 'খাবো'], array_column($b['word_meanings'], 'standard_meaning'), 'the Standard word is still given');
    }

    public function testTheOtherDirectionToo(): void
    {
        [, $b] = Http::request('POST', '/api/translate.php', ['text' => 'আঁই ভাত খাইয়ুম', 'source' => 'Chattogram', 'target' => 'Noakhali']);
        $this->assertSame(nfc('খামু'), nfc($b['word_meanings'][2]['target_word']));
    }

    public function testDialectToStandardIsUnchanged(): void
    {
        [, $b] = Http::request('POST', '/api/translate.php', ['text' => 'আঁই ভাত খামু', 'source' => 'Noakhali', 'target' => 'standard']);
        $this->assertNull($b['word_meanings'][2]['target_word']);
        $this->assertSame('খাবো', $b['word_meanings'][2]['standard_meaning']);
    }

    public static function garoPairs(): array
    {
        // D39: Standard → Garo never used the reviewed Garo list, and Garo words with ' (চা'আ) were split in two
        return [
            'Standard → Garo, all words reviewed' => ['আমি ভাত', 'standard', 'garo', 'আং মি'],
            'Standard → Garo, one word missing'   => ['আমি দুধ খাবো', 'standard', 'garo', "আং দুধ চা'নো"],
            'Garo → Standard with an apostrophe'  => ["আং মি চা'আ", 'garo', 'standard', 'আমি ভাত খাওয়া'],
            'Standard → Garo gives the apostrophe word' => ['আমি খাওয়া', 'standard', 'garo', "আং চা'আ"],
        ];
    }

    /** @dataProvider garoPairs */
    public function testGaroUsesTheReviewedWordList(string $text, string $src, string $tgt, string $expected): void
    {
        [$c, $b] = Http::request('POST', '/api/translate.php', ['text' => $text, 'source' => $src, 'target' => $tgt]);
        $this->assertSame(200, $c);
        $this->assertSame(nfc($expected), nfc($b['translated_text']));
    }

    public function testMroWithoutReviewedWordsStillGivesNoGuess(): void
    {
        [, $b] = Http::request('POST', '/api/translate.php', ['text' => 'আমি ভাত খাবো', 'source' => 'standard', 'target' => 'mro']);
        $this->assertSame('', $b['translated_text']);
    }

    public static function learners(): array
    {
        // learner language => [mode, text of the "correct" message]
        return [
            'Noakhali speaker (Bangla script)' => ['noakhali-bn', 'bn', 'ঠিক হয়েছে!'],
            'Standard Bangla speaker'          => ['standard', 'bn', 'ঠিক হয়েছে!'],
            'Garo speaker (one reviewed word)' => ['garo', 'icons', '✓ নাম্মা!'],
            'Mro speaker (nothing reviewed)'   => ['mro', 'icons', '✓'],
        ];
    }

    /** @dataProvider learners */
    public function testInstructionsAreInTheLearnersLanguageAndNeverEnglish(string $lang, string $mode, string $correct): void
    {
        [$c, $b] = Http::request('GET', '/api/learn.php?action=ui&lang=' . $lang);
        $this->assertSame(200, $c);
        $this->assertSame($mode, $b['mode']);
        $this->assertSame($correct, $b['strings']['correct']);
        foreach ($b['strings'] as $k => $v) {
            if ($k === 'err_languages' && str_contains($v, 'setup.php')) continue;   // a file name
            $this->assertDoesNotMatchRegularExpression('/[A-Za-z]{3,}/', $v, "$lang: '$k' is English");
        }
    }

    public function testUnknownLearnerLanguageIsRefused(): void
    {
        [$c] = Http::request('GET', '/api/learn.php?action=ui&lang=' . rawurlencode('../etc'));
        $this->assertSame(400, $c);
    }

    public function testWordQuestionShowsTheLearnersOwnReviewedWord(): void
    {
        // Garo reviewed list: ছি = পানি. A Mro learner has no reviewed words, so no hint.
        [, $g] = Http::request('GET', '/api/learn.php?action=course_items&source=garo&target=standard&unit=words-2');
        $hints = array_column($g['items'], 'native_hint', 'speech_bn');
        $this->assertSame('ছি', $hints['পানি'] ?? null);
        [, $m] = Http::request('GET', '/api/learn.php?action=course_items&source=mro&target=standard&unit=words-2');
        $this->assertSame([], array_filter(array_column($m['items'], 'native_hint')));
    }
}
