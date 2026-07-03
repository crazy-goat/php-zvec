--TEST--
SEC-007: Version argument validation in CLI installer
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Installer.php';

use CrazyGoat\ZVec\Installer;

// Test 1: Valid versions pass validation (Installer::install() will attempt download,
// but RuntimeException with "Invalid version format" should NOT be thrown)
$validVersions = ['v0.4.0', 'v1.2.3', 'v0.5.0-alpha1', 'v0.4.0-rc.2'];
foreach ($validVersions as $v) {
    ob_start();
    try {
        Installer::install($v);
        ob_end_clean();
        echo "PASS: {$v} accepted\n";
    } catch (RuntimeException $e) {
        ob_end_clean();
        if (str_contains($e->getMessage(), 'Invalid version format')) {
            echo "FAIL: {$v} rejected as invalid but should be accepted\n";
            exit(1);
        }
        echo "PASS: {$v} accepted (download error expected)\n";
    }
}

// Test 2: Invalid versions are rejected with RuntimeException
$invalidVersions = [
    ['../../etc/passwd', 'path traversal'],
    ['v0.4', 'incomplete semver'],
    ['', 'empty string'],
    ['foo/bar', 'slash in version'],
    ["v0.4.0\x00exploit", 'null byte'],
    ['not-a-version', 'just text'],
    ['v1.2', 'missing patch'],
    ['v0.4.0+build.123', 'plus in version (URL-unsafe)'],
    ['v0.4.0.', 'trailing dot'],
    ['v0.4.0-', 'trailing hyphen'],
    ['v0.4.0--', 'double trailing hyphen'],
];
foreach ($invalidVersions as [$v, $label]) {
    try {
        Installer::install($v);
        echo "FAIL: {$label} ({$v}) should have been rejected\n";
        exit(1);
    } catch (RuntimeException $e) {
        if (str_contains($e->getMessage(), 'Invalid version format')) {
            echo "PASS: {$label} rejected\n";
        } else {
            echo "FAIL: {$label} threw wrong exception: {$e->getMessage()}\n";
            exit(1);
        }
    }
}

echo "PASS: All version validation tests completed\n";
?>
--EXPECT--
PASS: v0.4.0 accepted (download error expected)
PASS: v1.2.3 accepted (download error expected)
PASS: v0.5.0-alpha1 accepted (download error expected)
PASS: v0.4.0-rc.2 accepted (download error expected)
PASS: path traversal rejected
PASS: incomplete semver rejected
PASS: empty string rejected
PASS: slash in version rejected
PASS: null byte rejected
PASS: just text rejected
PASS: missing patch rejected
PASS: plus in version (URL-unsafe) rejected
PASS: trailing dot rejected
PASS: trailing hyphen rejected
PASS: double trailing hyphen rejected
PASS: All version validation tests completed
