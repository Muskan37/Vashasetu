<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Native-speaker rule for Noakhali "will not": Standard খাবো না = Noakhali খাইতান ন (and the reverse).
 * Source of the rule: the native speaker's sentence আমি ভাত খাবো না = আইঁ ভাত খাইতান ন.
 */
final class NoakhaliRuleTest extends TestCase
{
    private static function toNoakhali(array $std, array $tgt): string
    {
        $rows = array_map(fn($s, $t) => ['std' => $s, 'tgt' => $t], $std, $tgt);
        return implode(' ', array_column(vs_noakhali_negative_future($rows, false), 'tgt'));
    }

    public static function standardToNoakhali(): array
    {
        return [
            'EP খাবো না'                           => [['আমি', 'ভাত', 'খাবো', 'না'], ['আইঁ', 'ভাত', 'খামু', 'না'], 'আইঁ ভাত খাইতান ন'],
            'EP করবো না (dialect stem কইরমু)'       => [['আমি', 'করবো', 'না'], ['আইঁ', 'কইরমু', 'না'], 'আইঁ কইরতান ন'],
            'EP পড়বো না (dialect stem হইড়মু)'       => [['আমি', 'পড়বো', 'না'], ['আইঁ', 'হইড়মু', 'না'], 'আইঁ হইড়তান ন'],
            'EP positive future is not changed'    => [['আমি', 'ভাত', 'খাবো'], ['আইঁ', 'ভাত', 'খামু'], 'আইঁ ভাত খামু'],
            'EP না without a future verb'          => [['আমি', 'না'], ['আইঁ', 'না'], 'আইঁ না'],
            'BVA না as the first word'             => [['না', 'খাবো'], ['না', 'খামু'], 'না খামু'],
        ];
    }

    /** @dataProvider standardToNoakhali */
    public function testStandardToNoakhali(array $std, array $tgt, string $expected): void
    {
        $this->assertSame(nfc($expected), nfc(self::toNoakhali($std, $tgt)));
    }

    public function testNoakhaliToStandard(): void
    {
        // Arrange
        $rows = [['src' => 'আইঁ', 'std' => 'আমি', 'tgt' => 'আইঁ'], ['src' => 'খাইতান', 'std' => 'খাইতান', 'tgt' => ''], ['src' => 'ন', 'std' => 'ন', 'tgt' => '']];
        // Act
        $out = vs_noakhali_negative_future($rows, true);
        // Assert
        $this->assertSame(nfc('আমি খাবো না'), nfc(implode(' ', array_column($out, 'std'))));
    }

    public function testEmptyInputGivesEmptyOutput(): void
    {
        $this->assertSame([], vs_noakhali_negative_future([], false));
        $this->assertSame([], vs_noakhali_negative_future([], true));
    }
}
