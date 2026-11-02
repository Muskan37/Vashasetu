<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** English → Bangla grammar helpers (api/lib/en_grammar.php). Expected forms are standard written Bangla. */
final class EnglishGrammarTest extends TestCase
{
    public static function conjugations(): array
    {
        // person: 0 = I/we, 1 = you (তুমি), 2 = he/she/they
        return [
            'eat future I'        => ['eat', 'fut', 0, 'খাবো'],
            'eat past he'         => ['eat', 'past', 2, 'খেলো'],
            'eat present you'     => ['eat', 'pres', 1, 'খাও'],
            'eat present he'      => ['eat', 'pres', 2, 'খায়'],
            'go perfect you'      => ['go', 'perf', 1, 'গিয়েছো'],
            'go past I'           => ['go', 'past', 0, 'গেলাম'],
            'write present he'    => ['write', 'pres', 2, 'লেখে'],
            'write present I'     => ['write', 'pres', 0, 'লিখি'],
            'come past I'         => ['come', 'past', 0, 'এলাম'],
            'give future I'       => ['give', 'fut', 0, 'দেবো'],
            'do continuous he'    => ['do', 'cont', 2, 'করছে'],
            'work future you'     => ['work', 'fut', 1, 'কাজ করবে'],
            'eat negative past I' => ['eat', 'neg_past', 0, 'খাইনি'],
            'sleep perfect I'     => ['sleep', 'perf', 0, 'ঘুমিয়েছি'],
            'read past perfect I' => ['read', 'pperf', 0, 'পড়েছিলাম'],
        ];
    }

    /** @dataProvider conjugations */
    public function testConjugation(string $verb, string $tense, int $person, string $expected): void
    {
        $this->assertSame(nfc($expected), nfc(en_bn_conj(en_bn_verbs()[$verb], $tense, $person)));
    }

    public static function lemmas(): array
    {
        return [
            'EP base form'                 => ['eat', ['eat', 'base']],
            'EP irregular past'            => ['went', ['go', 'past']],
            'EP -ing, doubled consonant'   => ['running', ['run', 'ing']],
            'EP -ing, dropped e'           => ['writing', ['write', 'ing']],
            'EP -ed regular'               => ['played', ['play', 'past']],
            'EP -ed after silent e'        => ['used', ['use', 'past']],
            'EP -ied → y'                  => ['studied', ['study', 'past']],
            'EP -ies → y'                  => ['studies', ['study', 's']],
            'EP -es'                       => ['watches', ['watch', 's']],
            'EP -s'                        => ['plays', ['play', 's']],
            'EP verb ending in -ie: dies'  => ['dies', ['die', 's']],
            'EP verb ending in -ie: died'  => ['died', ['die', 'past']],
            'EP unknown word'              => ['xyzzy', null],
            'BVA empty string'             => ['', null],
            'BVA single letter'            => ['s', null],
        ];
    }

    /** @dataProvider lemmas */
    public function testLemma(string $word, ?array $expected): void
    {
        $this->assertSame($expected, en_bn_lemma($word));
    }

    public function testEveryIrregularFormPointsToAKnownVerb(): void
    {
        $verbs = en_bn_verbs();
        $unknown = array_keys(array_filter(en_bn_irregular(), fn($x) => !isset($verbs[$x[0]])));
        $this->assertSame([], $unknown, 'irregular forms whose base verb has no Bangla forms');
    }

    public static function placeEndings(): array
    {
        return [
            'EP ends in a consonant'         => ['বাজার', 'বাজারে'],
            'EP ends in া'                  => ['ঢাকা', 'ঢাকায়'],
            'EP ends in ি'                  => ['বাড়ি', 'বাড়িতে'],
            'EP ends in ু'                  => ['মামু', 'মামুতে'],
            'EP ends in a vowel letter ই'   => ['বই', 'বইয়ে'],
            'EP ends in a vowel letter উ'   => ['বউ', 'বউয়ে'],
            'EP already has -তে'            => ['বাড়িতে', 'বাড়িতে'],
            'EP English name'               => ['Dhaka', 'Dhaka-এ'],
        ];
    }

    /** @dataProvider placeEndings */
    public function testPlaceEnding(string $noun, string $expected): void
    {
        $this->assertSame(nfc($expected), nfc(en_bn_loc($noun)));
    }

    public static function possessiveEndings(): array
    {
        return [
            'EP ends in a consonant'        => ['রহিম', 'রহিমের'],
            'EP ends in া'                 => ['বাবা', 'বাবার'],
            'EP ends in ি'                 => ['বাড়ি', 'বাড়ির'],
            'EP ends in ে'                 => ['ছেলে', 'ছেলের'],
            'EP special word মা'           => ['মা', 'মায়ের'],
            'EP ends in a vowel letter ই'  => ['ভাই', 'ভাইয়ের'],
            'EP ends in a vowel letter উ'  => ['বউ', 'বউয়ের'],
            'EP English name'              => ['Rahim', 'Rahim-এর'],
        ];
    }

    /** @dataProvider possessiveEndings */
    public function testPossessiveEnding(string $noun, string $expected): void
    {
        $this->assertSame(nfc($expected), nfc(en_bn_gen($noun)));
    }

    public function testImperativesAndVerbalNouns(): void
    {
        $V = en_bn_verbs();
        $this->assertSame(nfc('খাও'), nfc(en_bn_imp($V['eat'], false)));
        $this->assertSame(nfc('খেও না'), nfc(en_bn_imp($V['eat'], true)));
        $this->assertSame(nfc('পছন্দ করো'), nfc(en_bn_imp($V['like'], false)));
        $this->assertSame(nfc('খাওয়া'), nfc(en_bn_vn($V['eat'])));
        $this->assertSame(nfc('ঘুমানো'), nfc(en_bn_vn($V['sleep'])));
        $this->assertSame(nfc('লেখা'), nfc(en_bn_vn($V['write'])));
        $this->assertSame(nfc('দেওয়া'), nfc(en_bn_vn($V['give'])));
        $this->assertSame(nfc('রান্না করতে'), nfc(en_bn_inf($V['cook'])));
    }

    public function testEnglishPhraseLookupIgnoresCaseAndPunctuation(): void
    {
        $this->assertSame('শুভ জন্মদিন', phrase_en_bn('Happy   Birthday!'));
        $this->assertNull(phrase_en_bn('unknown phrase here'));
    }
}
