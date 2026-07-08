<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Installer.php';

use CrazyGoat\ZVec\Installer;

$label = Installer::platformLabel();

if (str_contains($label, PHP_OS_FAMILY)) {
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
