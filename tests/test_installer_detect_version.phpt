--TEST--
Installer: detectVersion throws RuntimeException when composer metadata unavailable
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Installer.php';

use CrazyGoat\ZVec\Installer;

// Test 1: detectVersion should throw RuntimeException when no installed.json
$ref = new ReflectionMethod(Installer::class, 'detectVersion');

try {
    $ref->invoke(null);
    echo "FAIL: Expected RuntimeException was not thrown\n";
    exit(1);
} catch (RuntimeException $e) {
    $msg = $e->getMessage();
    if (str_contains($msg, 'Could not determine package version')) {
        echo "PASS: detectVersion throws RuntimeException with expected message\n";
    } else {
        echo "FAIL: Wrong exception message: {$msg}\n";
        exit(1);
    }
    if (str_contains($msg, 'composer install')) {
        echo "PASS: Exception message mentions composer install\n";
    } else {
        echo "FAIL: Exception message missing composer install hint\n";
        exit(1);
    }
    if (str_contains($msg, 'vendor/bin/zvec-install')) {
        echo "PASS: Exception message mentions vendor/bin/zvec-install\n";
    } else {
        echo "FAIL: Exception message missing vendor/bin/zvec-install hint\n";
        exit(1);
    }
}

echo "PASS: All detectVersion tests completed\n";
?>
--EXPECT--
PASS: detectVersion throws RuntimeException with expected message
PASS: Exception message mentions composer install
PASS: Exception message mentions vendor/bin/zvec-install
PASS: All detectVersion tests completed
