<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Bangla text helpers in api/lib/text.php and the sentence helpers in api/word_evidence.php. */
final class TextHelpersTest extends TestCase
{
    public static function normalizeCases(): array
    {
        return [
            'EP empty string'                        => ['', ''],
            'EP only spaces'                         => ["   \t \n ", ''],
            'EP only punctuation'                    => ['।?!,"', ''],
            'EP plain Bangla words'                  => ['আমি ভাত খাবো', 'আমি ভাত খাবো'],
            'EP punctuation becomes a space'         => ['আমি,ভাত।খাবো?', 'আমি ভাত খাবো'],
            'EP many spaces become one'              => ["আমি   ভাত\n\nখাবো", 'আমি ভাত খাবো'],
            'EP zero-width joiners are removed'      => ["র\u{200D}্যাব", 'র্যাব'],
            'EP precomposed য় equals য + nukta'     => ["\u{09DF}", "\u{09AF}\u{09BC}"],
            'EP precomposed ড় equals ড + nukta'     => ["\u{09DC}", "\u{09A1}\u{09BC}"],
            'EP English letters are kept'            => ['Hello, World!', 'Hello World'],
        ];
    }

    /** @dataProvider normalizeCases */
    public function testBnNormalize(string $input, string $expected): void
    {
        $this->assertSame($expected, bn_normalize($input));
    }

    public static function tokenCases(): array
    {
        return [
            'BVA no words'          => ['', []],
            'BVA only punctuation'  => ['।।??', []],
            'BVA exactly one word'  => ['ভাত', ['ভাত']],
            'EP two sentences'      => ['আমি ভাত খাবো। তুমি?', ['আমি', 'ভাত', 'খাবো', 'তুমি']],
            'EP hyphenated word'    => ['ইন্দো-আর্য', ['ইন্দো', 'আর্য']],
        ];
    }

    /** @dataProvider tokenCases */
    public function testBnTokens(string $input, array $expected): void
    {
        $this->assertSame($expected, bn_tokens($input));
    }

    public function testScriptStatsCountsLettersOfEachScript(): void
    {
        // Arrange
        $text = 'ami আমি 123';
        // Act
        $stats = script_stats($text);
        // Assert: আমি is 3 code points (আ, ম, ি); digits are neither script
        $this->assertSame(['bangla' => 3, 'latin' => 3], $stats);
    }

    public static function variantCases(): array
    {
        return [
            // the optional final ো is only added/removed for words longer than 2 letters (boundary at length 2/3)
            'BVA length 2 is unchanged'          => ['বো', ['বো']],
            'BVA length 3 without ো gets one'    => ['যাব', ['যাব', 'যাবো']],
            'BVA length 3 with ো loses it'       => ['আছো', ['আছো', 'আছ']],
            'EP length 4 future verb'            => ['খাবো', ['খাবো', 'খাব']],
            'EP word not ending in ব/ছ unchanged'=> ['কাল', ['কাল']],
            'EP black (কালো) is not a verb form' => ['কালো', ['কালো']],
        ];
    }

    /** @dataProvider variantCases */
    public function testBnStdVariants(string $word, array $expected): void
    {
        $this->assertSame($expected, bn_std_variants($word));
    }

    public static function contractionCases(): array
    {
        return [
            'EP no contraction'          => ['I will eat rice', 'I will eat rice'],
            "EP can't"                   => ["I can't go", 'I can not go'],
            "EP won't at sentence start" => ["Won't you come?", 'will not you come?'],
            'EP curly apostrophe'        => ['I don’t know', 'I do not know'],
            'EP unknown contraction kept'=> ["y'all come", "y'all come"],
            'BVA empty text'             => ['', ''],
        ];
    }

    /** @dataProvider contractionCases */
    public function testExpandContractions(string $input, string $expected): void
    {
        $this->assertSame($expected, expand_contractions($input));
    }

    public static function sentenceCases(): array
    {
        return [
            'BVA one sentence, no end mark'    => ['আমি ভাত খাবো', 1],
            'BVA one sentence with end mark'   => ['আমি ভাত খাবো।', 1],
            'EP three sentences, three marks'  => ['আমি ভাত খাবো। তুমি কোথায় যাবে? ভালো!', 3],
            'EP English full stops'            => ['I am here. You are there.', 2],
            'EP line breaks split sentences'   => ["আমি ভাত খাবো\nতুমি কোথায়", 2],
            'EP decimal number is not split'   => ['দাম 2.5 টাকা', 1],
        ];
    }

    /** @dataProvider sentenceCases */
    public function testSplitSentences(string $text, int $parts): void
    {
        $out = vs_split_sentences($text);
        $this->assertCount($parts, $out, json_encode($out, JSON_UNESCAPED_UNICODE));
        $this->assertSame(preg_replace('/\s+/u', '', $text), preg_replace('/\s+/u', '', implode('', $out)), 'no text may be lost');
    }

    public static function punctuationCases(): array
    {
        return [
            'EP commas go back next to their word'  => ['আমি, তুমি ও সে', 'আঁই তুঁই আর হিতে', 'আঁই, তুঁই আর হিতে'],
            'EP question mark kept at the end'      => ['তুমি কোথায়?', 'তুঁই কডে', 'তুঁই কডে?'],
            'EP full stop is not doubled'           => ['আমি যাবো।', 'আঁই যাইয়ুম।', 'আঁই যাইয়ুম।'],
            'EP hyphen kept inside a word'          => ['ইন্দো-আর্য ভাষা', 'ইন্দো আর্য ভাষা', 'ইন্দো-আর্য ভাষা'],
            'EP different word count: ends only'    => ['আমি ভাত খাবো!', 'I will eat rice', 'I will eat rice!'],
            'BVA empty translation stays empty'     => ['আমি', '', ''],
        ];
    }

    /** @dataProvider punctuationCases */
    public function testPunctuate(string $input, string $translation, string $expected): void
    {
        $this->assertSame(nfc($expected), nfc(vs_punctuate($input, $translation)));
    }

    public static function composeCases(): array
    {
        return [
            'EP ো typed as ে + া'       => ["\u{09C7}\u{09BE}", "\u{09CB}"],
            'EP ৌ typed as ে + ৗ'       => ["\u{09C7}\u{09D7}", "\u{09CC}"],
            'EP already one letter'      => ["\u{09CB}", "\u{09CB}"],
            'EP ে alone stays'           => ["\u{09C7}", "\u{09C7}"],
            'EP whole word খাবো'        => ["খাব\u{09C7}\u{09BE}", 'খাবো'],
            'BVA empty'                  => ['', ''],
        ];
    }

    /** @dataProvider composeCases */
    public function testBnComposeJoinsSplitVowelSigns(string $in, string $expected): void
    {
        $this->assertSame($expected, bn_compose($in));
        $this->assertSame(nfc($in), bn_compose($in), 'same result as Unicode NFC');
    }

    public static function fullStopCases(): array
    {
        return [
            'EP sentence end'            => ['আমি ভাত খাবো. তুমি?', 'আমি ভাত খাবো। তুমি?'],
            'EP end of text'             => ['আমি ভাত খাবো.', 'আমি ভাত খাবো।'],
            'EP ellipsis kept'           => ['আমি...', 'আমি...'],
            'EP decimal number kept'     => ['দাম 2.5 টাকা', 'দাম 2.5 টাকা'],
            'EP no full stop'            => ['আমি', 'আমি'],
        ];
    }

    /** @dataProvider fullStopCases */
    public function testBanglishFullStops(string $in, string $expected): void
    {
        $this->assertSame($expected, vs_bn_fullstops($in));
    }
}
