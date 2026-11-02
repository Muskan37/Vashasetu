<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Step 2 of the lab: edge-case tests produced by an AI test generator for two functions,
 * vs_split_sentences() and vs_punctuate(), then inspected one by one.
 *
 * Generator: Claude (AI assistant), 2 Nov 2026. Qodo (CodiumAI) needs its IDE plugin and an account, and
 * Diffblue Cover only supports Java, so neither could be run on this PHP project here.
 * Like Qodo and Diffblue, the generator took each expected value from what the current code returned.
 * Every expected value was then checked against how Bangla and English sentences are really punctuated.
 * Two generated expectations were wrong: they described a bug, not correct behaviour (G-01, G-02).
 * They were corrected below and the bug was fixed (defect D30). The review of all 25 is in
 * tests/phpunit/generated_review.json.
 */
final class GeneratedEdgeCasesTest extends TestCase
{
    public static function sentenceSplits(): array
    {
        return [
            // G-01 generated: ["Dr. ","Rahim came."]  → WRONG, "Dr." is a title, not a sentence
            'G-01 title Dr. does not end a sentence'        => ['Dr. Rahim came.', ['Dr. Rahim came.']],
            // G-02 generated: ["Mr. ","Karim is here. ","He eats."] → WRONG
            'G-02 title Mr. inside two sentences'           => ['Mr. Karim is here. He eats.', ['Mr. Karim is here. ', 'He eats.']],
            // G-03 generated: ["১. ","প্রথম ২. ","দ্বিতীয়"] → questionable; after the D30 fix a list number never ends a sentence
            'G-03 list numbers stay with their item'         => ['১. প্রথম ২. দ্বিতীয়', ['১. প্রথম ২. দ্বিতীয়']],
            'G-04 ellipsis then a question'                  => ['আমি ভাত খাবো... তুমি?', ['আমি ভাত খাবো... ', 'তুমি?']],
            'G-05 ?! then another question'                  => ['What?! Really?', ['What?! ', 'Really?']],
            'G-06 blank line between sentences'              => ["আমি\n\nতুমি", ["আমি\n\n", 'তুমি']],
            'G-07 only end marks'                            => ['।।।', ['।।।']],
            'G-08 dots without spaces are one word'          => ['a.b.c', ['a.b.c']],
        ];
    }

    /** @dataProvider sentenceSplits */
    public function testSentenceSplit(string $text, array $expected): void
    {
        $this->assertSame($expected, vs_split_sentences($text));
    }

    public static function punctuation(): array
    {
        return [
            'G-09 quotation marks around the sentence'   => ['"আমি ভাত খাবো"', 'আঁই ভাত খাইয়ুম', '"আঁই ভাত খাইয়ুম"'],
            'G-10 brackets around one word'              => ['(আমি) ভাত খাবো', 'আঁই ভাত খাইয়ুম', '(আঁই) ভাত খাইয়ুম'],
            'G-11 ellipsis at the end'                   => ['আমি ভাত খাবো...', 'আঁই ভাত খাইয়ুম', 'আঁই ভাত খাইয়ুম...'],
            'G-12 ?! at the end'                         => ['আমি ভাত খাবো?!', 'আঁই ভাত খাইয়ুম', 'আঁই ভাত খাইয়ুম?!'],
            'G-13 dash standing alone'                   => ['আমি — ভাত খাবো', 'আঁই ভাত খাইয়ুম', 'আঁই — ভাত খাইয়ুম'],
            'G-14 trailing space kept'                   => ['আমি ভাত খাবো ', 'আঁই ভাত খাইয়ুম', 'আঁই ভাত খাইয়ুম '],
            'G-15 trailing line break kept'              => ["আমি ভাত খাবো\n", 'আঁই ভাত খাইয়ুম', "আঁই ভাত খাইয়ুম\n"],
            'G-16 two commas'                            => ['আমি, তুমি, সে', 'আঁই তুঁই হিতে', 'আঁই, তুঁই, হিতে'],
            'G-17 comma without a space'                 => ['আমি,তুমি', 'আঁই তুঁই', 'আঁই,তুঁই'],
            'G-18 Bangla digits'                         => ['১০০ টাকা!', '১০০ টিয়া', '১০০ টিয়া!'],
            'G-19 data has a double দাঁড়ি'              => ['আমি ভাত খাবো।', 'আঁই ভাত খাইয়ুম।।', 'আঁই ভাত খাইয়ুম।'],
            'G-20 data has a দাঁড়ি at the start'        => ['আমি ভাত খাবো।', '। আঁই ভাত খাইয়ুম', 'আঁই ভাত খাইয়ুম।'],
            'G-21 empty translation'                     => ['ভালো!', '', ''],
            'G-22 empty input'                           => ['', 'আঁই', 'আঁই'],
            'G-23 many spaces in the input'              => ['আমি   ভাত', 'আঁই ভাত', 'আঁই ভাত'],
            'G-24 comma only in the data is dropped'     => ['আমি ভাত খাবো', 'আঁই, ভাত খাইয়ুম', 'আঁই ভাত খাইয়ুম'],
            'G-25 different word count keeps only ends'  => ['Rahim-er boi', 'রহিমের বই', 'রহিমের বই'],
        ];
    }

    /** @dataProvider punctuation */
    public function testPunctuate(string $input, string $translation, string $expected): void
    {
        $this->assertSame(nfc($expected), nfc(vs_punctuate($input, $translation)));
    }
}
