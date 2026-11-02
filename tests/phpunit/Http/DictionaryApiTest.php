<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Integration: the Words screen's API — add, search, edit and delete a word, and the input limits. */
final class DictionaryApiTest extends TestCase
{
    private static int $n = 100;

    private function edit(array $body): array
    {
        return Http::request('POST', '/api/edit_word.php', $body, ['CF-Connecting-IP: ' . Http::ip(++self::$n)]);
    }

    private function search(string $dialect, string $q): array
    {
        return Http::request('GET', '/api/dictionary.php?' . http_build_query(['dialect' => $dialect, 'q' => $q]));
    }

    public function testAddSearchEditDeleteFlow(): void
    {
        $word = 'পিইউশব্দ' . random_int(10000, 99999);
        // add
        [$c] = $this->edit(['language' => 'Sylheti', 'word' => $word, 'meaning' => 'পরীক্ষা']);
        $this->assertSame(200, $c);
        [, $b] = $this->search('Sylheti', $word);
        $this->assertSame(1, $b['total']);
        $this->assertSame('পরীক্ষা', $b['rows'][0]['standard_word']);
        $this->assertSame(1, $b['rows'][0]['verified'], 'a word added in the app counts as native-speaker reported');
        // edit
        $this->edit(['language' => 'Sylheti', 'word' => $word, 'meaning' => 'যাচাই']);
        [, $b] = $this->search('Sylheti', $word);
        $this->assertSame('যাচাই', $b['rows'][0]['standard_word']);
        // the translator uses it at once
        [, $t] = Http::request('POST', '/api/translate.php', ['text' => 'যাচাই', 'source' => 'standard', 'target' => 'Sylheti']);
        $this->assertSame($word, $t['translated_text']);
        // delete
        $this->edit(['language' => 'Sylheti', 'word' => $word, 'meaning' => '', 'action' => 'delete']);
        [, $b] = $this->search('Sylheti', $word);
        $this->assertSame(0, $b['total']);
    }

    public static function editInputs(): array
    {
        return [
            'BVA word 100 letters (max)'      => [str_repeat('ক', 100), 'পরীক্ষা', 'Sylheti', 200],
            'BVA word 101 letters (max+1)'    => [str_repeat('ক', 101), 'পরীক্ষা', 'Sylheti', 400],
            'BVA meaning 100 letters (max)'   => ['পিইউ' . random_int(1000, 9999), str_repeat('খ', 100), 'Sylheti', 200],
            'BVA meaning 101 letters'         => ['পিইউ' . random_int(1000, 9999), str_repeat('খ', 101), 'Sylheti', 400],
            'BVA empty word'                  => ['', 'পরীক্ষা', 'Sylheti', 400],
            'BVA empty meaning on save'       => ['পিইউ' . random_int(1000, 9999), '', 'Sylheti', 400],
            'EP unsupported language'         => ['পিইউ', 'পরীক্ষা', 'Klingon', 400],
        ];
    }

    /** @dataProvider editInputs */
    public function testEditValidation(string $word, string $meaning, string $lang, int $expected): void
    {
        [$c, $b] = $this->edit(['language' => $lang, 'word' => $word, 'meaning' => $meaning]);
        $this->assertSame($expected, $c, json_encode($b, JSON_UNESCAPED_UNICODE));
        if ($c === 200) $this->edit(['language' => $lang, 'word' => $word, 'meaning' => '', 'action' => 'delete']);   // clean up
    }

    public static function pages(): array
    {
        return [
            'BVA page 0 → first page'       => ['0', 1],
            'BVA page 1'                    => ['1', 1],
            'EP negative page → first page' => ['-5', 1],
            'EP text instead of a number'   => ['abc', 1],
            'BVA page 100000 (max)'         => ['100000', 100000],
            'BVA page 100001 → max'         => ['100001', 100000],
            'EP huge page number'           => ['1000000000000000', 100000],
        ];
    }

    /** @dataProvider pages */
    public function testPageNumberIsKeptInRange(string $page, int $expected): void
    {
        [$c, $b] = Http::request('GET', '/api/dictionary.php?dialect=Noakhali&page=' . urlencode($page));
        $this->assertSame(200, $c);
        $this->assertSame($expected, $b['page']);
    }

    public function testSearchTreatsWildcardsAsLetters(): void
    {
        [, $b] = $this->search('Noakhali', '%');
        $this->assertSame(0, $b['total']);
    }
}
