--TEST--
Installer: versionFromInstalledJson returns version string or null
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Installer.php';

use CrazyGoat\ZVec\Installer;

$ref = new ReflectionMethod(Installer::class, 'versionFromInstalledJson');

$result = $ref->invoke(null);

// Result should be null or a valid version string
if ($result === null) {
    echo "PASS: versionFromInstalledJson returns null (no composer metadata)\n";
} else {
    echo "PASS: versionFromInstalledJson returns '{$result}'\n";

    // Verify it's a valid version format (vX.Y.Z)
    if (preg_match('/^v\d+\.\d+\.\d+$/', $result)) {
        echo "PASS: Version matches vX.Y.Z format\n";
    } else {
        echo "FAIL: Version '{$result}' does not match vX.Y.Z format\n";
        exit(1);
    }

    // Verify it starts with 'v'
    if (str_starts_with($result, 'v')) {
        echo "PASS: Version starts with 'v'\n";
    } else {
        echo "FAIL: Version does not start with 'v'\n";
        exit(1);
    }
}

echo "PASS: All versionFromInstalledJson tests completed\n";
?>
--EXPECT--
PASS: versionFromInstalledJson returns null (no composer metadata)
PASS: All versionFromInstalledJson tests completed
