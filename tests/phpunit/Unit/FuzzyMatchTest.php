<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * The phonetic keys and weighted edit distance that match Roman typing ("ami bhat khabo") against Bangla words.
 * Distance 0 = same sound; small = spelling variant; large = different word.
 */
final class FuzzyMatchTest extends TestCase
{
    public static function sameSound(): array
    {
        return [
            'EP ami = আমি'     => ['ami', 'আমি'],
            'EP tumi = তুমি'   => ['tumi', 'তুমি'],
            'EP khabo = খাবো'  => ['khabo', 'খাবো'],
            'EP bhat = ভাত'    => ['bhat', 'ভাত'],
        ];
    }

    /** @dataProvider sameSound */
    public function testRomanTypingOfTheSameWordHasZeroDistance(string $roman, string $bangla): void
    {
        $this->assertEqualsWithDelta(0.0, key_rel_dist(latin_key($roman), bn_key($bangla)), 0.15);
    }

    public function testDifferentWordsAreFarApart(): void
    {
        $this->assertGreaterThan(0.5, key_rel_dist(latin_key('bhat'), bn_key('পানি')));
    }

    public function testSubstitutionCosts(): void
    {
        $this->assertSame(0.0, key_sub_cost('K', 'K'));
        $this->assertSame(0.15, key_sub_cost('K', 'KH'));
        $this->assertSame(0.15, key_sub_cost('KH', 'K'));
        $this->assertSame(1.5, key_sub_cost('A', 'K'));
        $this->assertSame(1.2, key_sub_cost('K', 'M'));
        $this->assertSame(0.5, key_sub_cost('J', 'Y'));      // য / য় / জ sound alike (added after mutant M-22 survived)
        $this->assertSame(0.5, key_sub_cost('N', 'NG'));
    }

    public function testDistanceIsSymmetric(): void
    {
        $a = bn_key('খাইয়ুম'); $b = bn_key('খাবো');
        $this->assertEqualsWithDelta(key_dist($a, $b), key_dist($b, $a), 1e-9);
    }

    public static function distanceBoundaries(): array
    {
        return [
            'BVA both empty'            => [[], [], 0.0],
            'BVA one token vs empty'    => [['K'], [], 1.0],
            'BVA identical'             => [['K', 'A'], ['K', 'A'], 0.0],
            'EP aspirate pair K/KH'     => [['K'], ['KH'], 0.15],
            // substituting a vowel by a consonant costs 1.5, but deleting the vowel (0.3) and inserting the
            // consonant (1.0) is cheaper, so the distance is 1.3 (the first version of this test wrongly expected 1.5)
            'EP vowel vs consonant'     => [['A'], ['K'], 1.3],
            'EP optional inherent o'    => [['K', 'o', 'L'], ['K', 'L'], 0.12],
        ];
    }

    /** @dataProvider distanceBoundaries */
    public function testKeyDist(array $a, array $b, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, key_dist($a, $b), 1e-9);
    }

    public static function latinKeys(): array
    {
        return [
            'EP chh is one sound'          => ['chhobi', ['CH', 'O', 'B', 'I']],
            'EP x is k + s'                => ['xa', ['K', 'S', 'A']],
            'EP f is ph'                   => ['fol', ['PH', 'O', 'L']],
            'EP w before a vowel is b'     => ['wa', ['B', 'A']],
            'EP y between vowels is y'     => ['aya', ['A', 'Y', 'A']],
            'EP repeated letters collapse' => ['ammi', ['A', 'M', 'I']],
            'BVA empty'                    => ['', []],
            'EP digits and symbols ignored'=> ['a1-b', ['A', 'B']],
        ];
    }

    /** @dataProvider latinKeys */
    public function testLatinKey(string $word, array $expected): void
    {
        $this->assertSame($expected, latin_key($word));
    }

    public function testFoldKeyDropsVowelsAndJoinsAspirates(): void
    {
        $this->assertSame('KB', fold_key(['KH', 'A', 'B', 'O']));
    }

    public function testFoldVariantsContainsOneEditNeighboursButNeverEmpty(): void
    {
        $v = fold_variants('KB');
        $this->assertContains('KB', $v);
        $this->assertContains('K', $v);      // one deletion
        $this->assertContains('KRB', $v);    // one insertion
        $this->assertNotContains('', $v);
        $this->assertSame(count($v), count(array_unique($v)));
    }

    public function testFoldVariantsOfAOneLetterKeyNeverContainTheEmptyKey(): void
    {
        // BVA: a key of length 1 (added after mutant M-20 survived: deleting its only letter gives '')
        $this->assertNotContains('', fold_variants('K'));
        $this->assertContains('K', fold_variants('K'));
    }
}
