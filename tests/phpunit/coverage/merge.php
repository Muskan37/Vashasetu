<?php
// Merges every coverage file in tests/results/coverage-raw (PHPUnit's own run plus one file per web request)
// into one report: HTML (tests/results/coverage/index.html), Clover XML and a text summary.
//     php tests/phpunit/coverage/merge.php
$app = dirname(__DIR__, 3);
foreach ([$app . '/vendor/autoload.php', '/usr/share/php/PHPUnit/Autoload.php'] as $a) if (is_file($a)) { require_once $a; break; }
ini_set('memory_limit', '2048M');

use SebastianBergmann\CodeCoverage\Report\{Clover, Html\Facade, Text};

$raw = $app . '/tests/results/coverage-raw';
$files = glob($raw . '/*.cov');
if (!$files) { fwrite(STDERR, "No coverage files in $raw\n"); exit(1); }
$total = null; $n = 0;
foreach ($files as $f) {
    $c = include $f;
    if (!$c instanceof \SebastianBergmann\CodeCoverage\CodeCoverage) continue;
    if ($total === null) $total = $c; else $total->merge($c);
    $n++;
}
$out = $app . '/tests/results/coverage';
(new Facade())->process($total, $out . '/html');
(new Clover())->process($total, $out . '/clover.xml', 'Vashasetu');
$text = (new Text(50, 90, true, false))->process($total, false);
file_put_contents($out . '/summary.txt', $text);
echo "Merged $n coverage files\n" . $text;
