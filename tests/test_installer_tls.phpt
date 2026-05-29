--TEST--
Installer: Explicit TLS certificate verification
--SKIPIF--
<?php if (!extension_loaded('openssl')) die('skip openssl extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Installer.php';

use CrazyGoat\ZVec\Installer;

// Test 1: Verify download() uses explicit TLS options via reflection
$refl = new ReflectionMethod(Installer::class, 'download');
$startLine = $refl->getStartLine();
$endLine = $refl->getEndLine();

$sourceFile = new ReflectionClass(Installer::class)->getFileName();
$lines = file($sourceFile);
$methodCode = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

// Verify 'https' context key is used (not 'http')
if (!str_contains($methodCode, "'https'")) {
    echo "FAIL: download() must use 'https' context key\n";
    exit(1);
}
echo "Test 1: download() uses 'https' context key\n";

// Verify verify_peer is explicitly set
if (!str_contains($methodCode, "'verify_peer' => true")) {
    echo "FAIL: download() must set verify_peer => true\n";
    exit(1);
}
echo "Test 2: download() sets verify_peer => true\n";

// Verify verify_peer_name is explicitly set
if (!str_contains($methodCode, "'verify_peer_name' => true")) {
    echo "FAIL: download() must set verify_peer_name => true\n";
    exit(1);
}
echo "Test 3: download() sets verify_peer_name => true\n";

// Verify verify_depth is set for defense-in-depth
if (!str_contains($methodCode, "'verify_depth'")) {
    echo "FAIL: download() must set verify_depth for defense-in-depth\n";
    exit(1);
}
echo "Test 4: download() sets verify_depth\n";

// Test 5: Verify getExpectedHash() uses explicit TLS options
$refl2 = new ReflectionMethod(Installer::class, 'getExpectedHash');
$startLine2 = $refl2->getStartLine();
$endLine2 = $refl2->getEndLine();
$methodCode2 = implode('', array_slice($lines, $startLine2 - 1, $endLine2 - $startLine2 + 1));

if (!str_contains($methodCode2, "'https'")) {
    echo "FAIL: getExpectedHash() must use 'https' context key\n";
    exit(1);
}
echo "Test 5: getExpectedHash() uses 'https' context key\n";

if (!str_contains($methodCode2, "'verify_peer' => true")) {
    echo "FAIL: getExpectedHash() must set verify_peer => true\n";
    exit(1);
}
echo "Test 6: getExpectedHash() sets verify_peer => true\n";

if (!str_contains($methodCode2, "'verify_peer_name' => true")) {
    echo "FAIL: getExpectedHash() must set verify_peer_name => true\n";
    exit(1);
}
echo "Test 7: getExpectedHash() sets verify_peer_name => true\n";

// Test 8: Verify NO @ error suppression operator on file_get_contents
$allSource = file_get_contents($sourceFile);
if (preg_match_all('/@\s*file_get_contents/', $allSource) > 0) {
    echo "FAIL: Must not use @ error suppression on file_get_contents\n";
    exit(1);
}
echo "Test 8: No @ error suppression on file_get_contents\n";

// Test 9: Verify error_get_last() is used for error details
if (!str_contains($methodCode, 'error_get_last()')) {
    echo "FAIL: download() must use error_get_last() for error details\n";
    exit(1);
}
echo "Test 9: download() uses error_get_last() for error details\n";

if (!str_contains($methodCode2, 'error_get_last()')) {
    echo "FAIL: getExpectedHash() must use error_get_last() for error details\n";
    exit(1);
}
echo "Test 10: getExpectedHash() uses error_get_last() for error details\n";

echo "PASS: All TLS verification tests passed\n";
?>
--EXPECT--
Test 1: download() uses 'https' context key
Test 2: download() sets verify_peer => true
Test 3: download() sets verify_peer_name => true
Test 4: download() sets verify_depth
Test 5: getExpectedHash() uses 'https' context key
Test 6: getExpectedHash() sets verify_peer => true
Test 7: getExpectedHash() sets verify_peer_name => true
Test 8: No @ error suppression on file_get_contents
Test 9: download() uses error_get_last() for error details
Test 10: getExpectedHash() uses error_get_last() for error details
PASS: All TLS verification tests passed
