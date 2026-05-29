--TEST--
Installer: libName returns correct shared library name per platform
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Installer.php';

use CrazyGoat\ZVec\Installer;

$ref = new ReflectionMethod(Installer::class, 'libName');
$libName = $ref->invoke(null);

$expected = PHP_OS_FAMILY === 'Darwin' ? 'libzvec_ffi.dylib' : 'libzvec_ffi.so';

if ($libName === $expected) {
    echo "PASS: libName returns '{$libName}' (expected '{$expected}')\n";
} else {
    echo "FAIL: libName returned '{$libName}', expected '{$expected}'\n";
    exit(1);
}

// Verify it's a valid filename (no path traversal, no null bytes)
if (strpos($libName, '/') !== false || strpos($libName, "\0") !== false) {
    echo "FAIL: libName contains invalid characters\n";
    exit(1);
}
echo "PASS: libName is a valid filename\n";

// Verify it starts with expected prefix
if (!str_starts_with($libName, 'libzvec_ffi.')) {
    echo "FAIL: libName does not start with expected prefix\n";
    exit(1);
}
echo "PASS: libName has correct prefix\n";

echo "PASS: All libName tests completed\n";
?>
--EXPECT--
PASS: libName returns 'libzvec_ffi.so' (expected 'libzvec_ffi.so')
PASS: libName is a valid filename
PASS: libName has correct prefix
PASS: All libName tests completed
