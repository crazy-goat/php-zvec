--TEST--
Installer: resolveAssetName returns correct asset name for current platform
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Installer.php';

use CrazyGoat\ZVec\Installer;

$ref = new ReflectionMethod(Installer::class, 'resolveAssetName');
$result = $ref->invoke(null);

// On supported platforms, should return a string
if ($result === null) {
    echo "PASS: resolveAssetName returns null on unsupported platform (" . PHP_OS_FAMILY . " " . php_uname('m') . ")\n";
} else {
    echo "PASS: resolveAssetName returns '{$result}'\n";

    // Verify the asset name contains expected components
    $os = PHP_OS_FAMILY;
    $arch = php_uname('m');

    if (!str_contains($result, '.tar.gz')) {
        echo "FAIL: Asset name does not end with .tar.gz\n";
        exit(1);
    }
    echo "PASS: Asset name ends with .tar.gz\n";

    if (!str_contains($result, 'libzvec_ffi')) {
        echo "FAIL: Asset name does not contain 'libzvec_ffi'\n";
        exit(1);
    }
    echo "PASS: Asset name contains 'libzvec_ffi'\n";

    // Platform-specific checks (uniform output across platforms)
    $identifierOk = ($os === 'Linux' && str_contains($result, 'linux'))
        || ($os === 'Darwin' && str_contains($result, 'darwin'));
    if (!$identifierOk) {
        echo "FAIL: Asset name missing platform identifier\n";
        exit(1);
    }
    echo "PASS: Asset name contains platform identifier\n";

    $expectedArch = $os === 'Darwin' && $arch === 'arm64' ? 'aarch64' : $arch;
    if (!str_contains($result, $expectedArch)) {
        echo "FAIL: Asset name missing architecture\n";
        exit(1);
    }
    echo "PASS: Asset name contains architecture\n";
}

echo "PASS: All resolveAssetName tests completed\n";
?>
--EXPECTF--
PASS: resolveAssetName returns '%s'
PASS: Asset name ends with .tar.gz
PASS: Asset name contains 'libzvec_ffi'
PASS: Asset name contains platform identifier
PASS: Asset name contains architecture
PASS: All resolveAssetName tests completed
