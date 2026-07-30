--TEST--
Installer: Platform label and asset name resolution
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Installer.php';

use CrazyGoat\ZVec\Installer;

$label = Installer::platformLabel();

$osMatch = str_contains($label, PHP_OS_FAMILY)
    || (PHP_OS_FAMILY === 'Darwin' && str_contains($label, 'macOS'));
if ($osMatch) {
    echo "Platform label contains OS family: PASS\n";
} else {
    echo "FAIL: Platform label missing OS family\n";
    exit(1);
}

if (str_contains($label, php_uname('m'))) {
    echo "Platform label contains architecture: PASS\n";
} else {
    echo "FAIL: Platform label missing architecture\n";
    exit(1);
}

echo "PASS: Platform label test completed\n";
?>
--EXPECT--
Platform label contains OS family: PASS
Platform label contains architecture: PASS
PASS: Platform label test completed
