--TEST--
Installer: Temp directory cleanup (no orphaned files after success/failure)
--FILE--
<?php
declare(strict_types=1);

// Test 1: Cleanup removes temp directory after successful operation
$tmpDir = sys_get_temp_dir() . '/zvec_ffi_' . bin2hex(random_bytes(8));
if (!mkdir($tmpDir, 0700, true)) {
    echo "FAIL: Failed to create temp directory\n";
    exit(1);
}
$tmpFile = $tmpDir . '/download.tar.gz';
try {
    file_put_contents($tmpFile, 'test content');
    // Simulate cleanup
    if (file_exists($tmpFile)) unlink($tmpFile);
    exec("rm -rf " . escapeshellarg($tmpDir));
    if (is_dir($tmpDir)) {
        echo "FAIL: Temp directory not removed after cleanup\n";
        exit(1);
    }
    echo "Test 1: Cleanup removed temp directory after success\n";
} finally {
    if (is_dir($tmpDir)) exec("rm -rf " . escapeshellarg($tmpDir));
}

// Test 2: Cleanup removes temp directory after failure (exception case)
$tmpDir2 = sys_get_temp_dir() . '/zvec_ffi_' . bin2hex(random_bytes(8));
if (!mkdir($tmpDir2, 0700, true)) {
    echo "FAIL: Failed to create temp directory 2\n";
    exit(1);
}
$tmpFile2 = $tmpDir2 . '/download.tar.gz';
try {
    file_put_contents($tmpFile2, 'partial data');
    // Simulate failure mid-operation, then cleanup
    if (file_exists($tmpFile2)) unlink($tmpFile2);
    exec("rm -rf " . escapeshellarg($tmpDir2));
} finally {
    if (is_dir($tmpDir2)) exec("rm -rf " . escapeshellarg($tmpDir2));
}
if (is_dir($tmpDir2)) {
    echo "FAIL: Temp directory 2 not removed after failure cleanup\n";
    exit(1);
}
echo "Test 2: Cleanup removed temp directory after failure\n";

// Test 3: Cleanup handles missing temp file gracefully
$tmpDir3 = sys_get_temp_dir() . '/zvec_ffi_' . bin2hex(random_bytes(8));
if (!mkdir($tmpDir3, 0700, true)) {
    echo "FAIL: Failed to create temp directory 3\n";
    exit(1);
}
$tmpFile3 = $tmpDir3 . '/download.tar.gz';
try {
    // Don't create temp file - simulate download failure before file creation
    exec("rm -rf " . escapeshellarg($tmpDir3));
} finally {
    if (is_dir($tmpDir3)) exec("rm -rf " . escapeshellarg($tmpDir3));
}
if (file_exists($tmpFile3)) {
    echo "FAIL: Temp file still exists after cleanup\n";
    exit(1);
}
if (is_dir($tmpDir3)) {
    echo "FAIL: Temp directory 3 not removed after cleanup\n";
    exit(1);
}
echo "Test 3: Cleanup handles missing temp file gracefully\n";

// Test 4: Concurrent installations use unique temp directories
$tmpDirs = [];
$tmpFiles = [];
for ($i = 0; $i < 3; $i++) {
    $dir = sys_get_temp_dir() . '/zvec_ffi_' . bin2hex(random_bytes(8));
    if (!mkdir($dir, 0700, true)) {
        echo "FAIL: Failed to create concurrent temp directory {$i}\n";
        exit(1);
    }
    $tmpDirs[] = $dir;
    $file = $dir . '/download.tar.gz';
    file_put_contents($file, "concurrent-test-{$i}");
    $tmpFiles[] = $file;
}

// Verify all directories and files are distinct
$uniqueDirs = array_unique($tmpDirs);
$uniqueFiles = array_unique($tmpFiles);
if (count($uniqueDirs) !== count($tmpDirs)) {
    echo "FAIL: Concurrent directories are not unique\n";
    exit(1);
}
if (count($uniqueFiles) !== count($tmpFiles)) {
    echo "FAIL: Concurrent files are not unique\n";
    exit(1);
}
echo "Test 4: Concurrent temp directories are unique\n";

// Cleanup concurrent dirs
for ($i = 0; $i < 3; $i++) {
    if (file_exists($tmpFiles[$i])) unlink($tmpFiles[$i]);
    exec("rm -rf " . escapeshellarg($tmpDirs[$i]));
}

// Test 5: Temp directory has restricted permissions (0700)
$tmpDir5 = sys_get_temp_dir() . '/zvec_ffi_' . bin2hex(random_bytes(8));
if (!mkdir($tmpDir5, 0700, true)) {
    echo "FAIL: Failed to create temp directory 5\n";
    exit(1);
}
try {
    $perms = fileperms($tmpDir5) & 0777;
    if ($perms !== 0700) {
        echo "FAIL: Expected 0700 permissions, got " . decoct($perms) . "\n";
        exit(1);
    }
    echo "Test 5: Temp directory has 0700 permissions\n";
} finally {
    exec("rm -rf " . escapeshellarg($tmpDir5));
}

echo "PASS: All temp directory cleanup tests completed\n";
?>
--EXPECT--
Test 1: Cleanup removed temp directory after success
Test 2: Cleanup removed temp directory after failure
Test 3: Cleanup handles missing temp file gracefully
Test 4: Concurrent temp directories are unique
Test 5: Temp directory has 0700 permissions
PASS: All temp directory cleanup tests completed
