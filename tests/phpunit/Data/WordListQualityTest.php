<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Step 3 gap: data defects. The translator is only as good as its word lists, but no test looked at the lists themselves.
 * Checks every database/languages/<language>/words.csv and database/curated.csv.
 */
final class WordListQualityTest extends TestCase
{
    /** a vowel sign on a vowel letter, a word starting with a sign, two vowel signs in a row */
    private const MALFORMED = '/[\x{0985}-\x{0994}][\x{09BE}-\x{09CC}\x{09D7}]|^[\x{09BE}-\x{09CC}\x{09D7}\x{09BC}\x{09CD}]|[\x{09BE}-\x{09CC}][\x{09BE}-\x{09CC}]/u';
    /** approved by the native reviewer exactly as written (2 Nov 2026), although the script is unusual */
    private const APPROVED_AS_WRITTEN = ['অ়ঞ্জক', 'অ়রকত্', 'অ়লরে', 'অ়াজাইয়্যুন', 'অ়াজি', 'আস়া', 'আস়ে', 'আ৴ইর'];

    private const STATUSES = ['needs_native_review', 'existing_curated_needs_recheck', 'corpus_confirmed', 'native_speaker_reported', 'native_speaker_alternative',
        'publication_reviewed_not_reaudited', 'community_verified'];

    public static function lists(): array
    {
        $out = [];
        foreach (glob(__DIR__ . '/../../../database/languages/*/words.csv') as $f) $out[basename(dirname($f))] = [$f];
        return $out;
    }

    private static function rows(string $file): array
    {
        $h = fopen($file, 'r'); $head = fgetcsv($h); $rows = [];
        while (($r = fgetcsv($h)) !== false) if (count($r) === count($head)) $rows[] = array_combine($head, $r);
        fclose($h);
        return $rows;
    }

    /** @dataProvider lists */
    public function testEveryRowIsComplete(string $file): void
    {
        $this->assertFileIsReadable($file);       // a language may have no words yet (only sentences)
        foreach (self::rows($file) as $i => $r) {
            foreach (['source_word', 'standard_word'] as $k) {
                $this->assertNotSame('', trim($r[$k]), "row $i: empty $k");
                $this->assertSame(trim($r[$k]), $r[$k], "row $i: space around '{$r[$k]}'");
            }
            $this->assertContains($r['meaning_status'], self::STATUSES, "row $i: unknown status");
        }
    }

    /** @dataProvider lists */
    public function testNoTypingMistakesThatCanBeFixedForCertain(string $file): void
    {
        $bad = [];
        foreach (self::rows($file) as $r) foreach (['source_word', 'standard_word'] as $k) {
            if (preg_match('/([\x{09BE}-\x{09CC}\x{09D7}\x{09C3}])\1/u', $r[$k])) $bad[] = $r[$k] . ' (same vowel sign twice)';
            if (preg_match('/\x{09C7}[\x{09BE}\x{09D7}]/u', $r[$k])) $bad[] = $r[$k] . ' (ো typed in two parts)';
        }
        $this->assertSame([], $bad);
    }

    /** @dataProvider lists */
    public function testNoDuplicateRows(string $file): void
    {
        $seen = [];
        $dups = [];
        foreach (self::rows($file) as $r) {
            $k = $r['language'] . '|' . $r['source_word'] . '|' . $r['standard_word'];
            if (isset($seen[$k])) $dups[] = $k;
            $seen[$k] = true;
        }
        $this->assertSame([], $dups);
    }

    /** @dataProvider lists */
    public function testTrustedWordsAreSpelledCorrectly(string $file): void
    {
        // words a person approved are used without a warning, so their spelling must be valid Bangla
        $bad = [];
        foreach (self::rows($file) as $r) {
            if (!in_array($r['meaning_status'], ['native_speaker_reported', 'native_speaker_alternative', 'community_verified'], true)) continue;
            foreach (['source_word', 'standard_word'] as $k) foreach (preg_split('/\s+/u', $r[$k]) as $w) if (preg_match(self::MALFORMED, $w) && !in_array($w, self::APPROVED_AS_WRITTEN, true)) $bad[] = $w;
        }
        $this->assertSame([], $bad);
    }

    public function testUnusualSpellingsInUnreviewedListsStayMarkedForReview(): void
    {
        // research lists still contain a few odd spellings (e.g. ঝিামাইর); they must never be shown as trusted
        $odd = [];
        foreach (self::lists() as [$f]) foreach (self::rows($f) as $r) {
            if (preg_match(self::MALFORMED, $r['source_word'] . ' ' . $r['standard_word'])) $odd[] = $r;
        }
        $this->assertNotEmpty($odd, 'if this fails, all odd spellings were fixed: remove this test');
        foreach ($odd as $r) if (!in_array($r['source_word'], self::APPROVED_AS_WRITTEN, true)) $this->assertSame('needs_native_review', $r['meaning_status'], $r['source_word']);
    }

    public function testCuratedListIsClean(): void
    {
        $h = fopen(__DIR__ . '/../../../database/curated.csv', 'r'); fgetcsv($h); $seen = [];
        while (($r = fgetcsv($h)) !== false) {
            [$d, $w, $s] = $r;
            $this->assertTrue(valid_dialect($d), "unknown dialect $d");
            $this->assertDoesNotMatchRegularExpression(self::MALFORMED, $w);
            $this->assertArrayNotHasKey("$d|$w|$s", $seen, "duplicate $d $w");
            $seen["$d|$w|$s"] = true;
        }
        fclose($h);
    }
}
