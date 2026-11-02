<?php
// Server-side code coverage for tests that use the app through the web server (Http suite and the Python suites).
// Turned on only while the file tests/results/coverage-raw/ON exists, so normal use of the app is not affected.
//
// Setup (once):  php.ini of Apache (XAMPP: C:\xampp\php\php.ini)
//     extension=pcov            (or Xdebug with xdebug.mode=coverage)
//     pcov.directory = "C:/xampp/htdocs/vashasetu"
//     auto_prepend_file = "C:/xampp/htdocs/vashasetu/tests/phpunit/coverage/prepend.php"
// then restart Apache. Merge the results with:  php tests/phpunit/coverage/merge.php
$vsCovDir = dirname(__DIR__, 2) . '/results/coverage-raw';
if (!is_file($vsCovDir . '/ON') || PHP_SAPI === 'cli') return;
if (!function_exists('pcov\start') && !function_exists('xdebug_start_code_coverage')) return;

$vsApp = dirname(__DIR__, 3);
foreach ([$vsApp . '/vendor/autoload.php', '/usr/share/php/PHPUnit/Autoload.php'] as $vsAuto) if (is_file($vsAuto)) { require_once $vsAuto; break; }
if (!class_exists(\SebastianBergmann\CodeCoverage\CodeCoverage::class)) return;

(static function (string $app, string $out): void {
    $filter = new \SebastianBergmann\CodeCoverage\Filter();
    $filter->includeDirectory($app . '/api', '.php');
    foreach (['index.php', 'learn.php', 'setup.php', 'admin_edits.php'] as $f) $filter->includeFile($app . '/' . $f);
    $cov = new \SebastianBergmann\CodeCoverage\CodeCoverage((new \SebastianBergmann\CodeCoverage\Driver\Selector())->forLineCoverage($filter), $filter);
    $id = ($_SERVER['REQUEST_METHOD'] ?? 'GET') . ' ' . strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    // flood tests send thousands of identical requests; 400 recorded requests per endpoint are enough
    $tally = $out . '/count_' . md5($id);
    if (@filesize($tally) >= 400) return;
    @file_put_contents($tally, '.', FILE_APPEND);
    $cov->start($id);
    register_shutdown_function(static function () use ($cov, $out): void {
        $cov->stop();
        (new \SebastianBergmann\CodeCoverage\Report\PHP())->process($cov, $out . '/' . uniqid('req_', true) . '.cov');
    });
})($vsApp, $vsCovDir);
