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

    // Platform-specific checks
    if ($os === 'Linux') {
        if (str_contains($result, 'ubuntu') || str_contains($result, 'alpine')) {
            echo "PASS: Linux asset name contains distro identifier\n";
        } else {
            echo "FAIL: Linux asset name missing distro identifier\n";
            exit(1);
        }
        if (str_contains($result, $arch)) {
            echo "PASS: Linux asset name contains architecture\n";
        } else {
            echo "FAIL: Linux asset name missing architecture\n";
            exit(1);
        }
    } elseif ($os === 'Darwin') {
        if (str_contains($result, 'darwin')) {
            echo "PASS: macOS asset name contains 'darwin'\n";
        } else {
            echo "FAIL: macOS asset name missing 'darwin'\n";
            exit(1);
        }
    }
}

echo "PASS: All resolveAssetName tests completed\n";
?>
--EXPECTF--
PASS: resolveAssetName returns '%s'
PASS: Asset name ends with .tar.gz
PASS: Asset name contains 'libzvec_ffi'
PASS: Linux asset name contains distro identifier
PASS: Linux asset name contains architecture
PASS: All resolveAssetName tests completed
