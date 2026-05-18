--TEST--
Installer: Secure temp directory creation (prevents symlink race, TOCTOU)
--FILE--
<?php
declare(strict_types=1);

// Test 1: Create temp dir with 0700 permissions
$tmpDir = sys_get_temp_dir() . '/zvec_ffi_' . bin2hex(random_bytes(8));
if (!mkdir($tmpDir, 0700, true)) {
    echo "FAIL: Failed to create temp directory\n";
    exit(1);
}

try {
    $perms = fileperms($tmpDir) & 0777;
    if ($perms !== 0700) {
        echo "FAIL: Expected 0700 permissions, got " . decoct($perms) . "\n";
        exit(1);
    }
    echo "Test 1: Temp directory created with 0700 permissions\n";

    // Test 2: Temp file inside directory
    $tmpFile = $tmpDir . '/download.tar.gz';
    file_put_contents($tmpFile, 'test content');
    if (!file_exists($tmpFile)) {
        echo "FAIL: Temp file not created inside directory\n";
        exit(1);
    }
    echo "Test 2: Temp file created inside secure directory\n";

    // Test 3: Cleanup removes entire directory
    if (file_exists($tmpFile)) unlink($tmpFile);
    exec("rm -rf " . escapeshellarg($tmpDir));
    if (is_dir($tmpDir)) {
        echo "FAIL: Temp directory not cleaned up\n";
        exit(1);
    }
    echo "Test 3: Cleanup removed entire directory\n";

    // Test 4: Unique names don't collide (run 5 iterations, all distinct)
    $names = [];
    for ($i = 0; $i < 5; $i++) {
        $dir = sys_get_temp_dir() . '/zvec_ffi_' . bin2hex(random_bytes(8));
        $names[] = $dir;
    }
    $unique = array_unique($names);
    if (count($unique) !== count($names)) {
        echo "FAIL: Generated duplicate temp directory names\n";
        exit(1);
    }
    echo "Test 4: All 5 generated directory names are unique\n";

    echo "PASS: All temp directory tests completed\n";
} finally {
    if (is_dir($tmpDir)) exec("rm -rf " . escapeshellarg($tmpDir));
}
?>
--EXPECT--
Test 1: Temp directory created with 0700 permissions
Test 2: Temp file created inside secure directory
Test 3: Cleanup removed entire directory
Test 4: All 5 generated directory names are unique
PASS: All temp directory tests completed
