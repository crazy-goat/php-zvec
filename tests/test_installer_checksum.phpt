--TEST--
Installer: SHA-256 checksum verification (verifyChecksum)
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Installer.php';

use CrazyGoat\ZVec\Installer;

// Test 1: Valid checksum passes
$tmpFile = sys_get_temp_dir() . '/zvec_test_' . bin2hex(random_bytes(8)) . '.test';
try {
    file_put_contents($tmpFile, 'hello world');
    $expectedHash = hash_file('sha256', $tmpFile);
    Installer::verifyChecksum($tmpFile, $expectedHash);
    echo "Test 1: Valid checksum passed\n";
} finally {
    if (file_exists($tmpFile)) unlink($tmpFile);
}

// Test 2: Mismatched checksum throws RuntimeException
$tmpFile2 = sys_get_temp_dir() . '/zvec_test_' . bin2hex(random_bytes(8)) . '.test';
try {
    file_put_contents($tmpFile2, 'hello world');
    $wrongHash = str_repeat('a', 64);
    try {
        Installer::verifyChecksum($tmpFile2, $wrongHash);
        echo "FAIL: Expected RuntimeException was not thrown\n";
        exit(1);
    } catch (RuntimeException $e) {
        if (str_contains($e->getMessage(), 'Checksum mismatch')) {
            echo "Test 2: Checksum mismatch thrown\n";
        } else {
            echo "FAIL: Wrong exception message\n";
            exit(1);
        }
    }
} finally {
    if (file_exists($tmpFile2)) unlink($tmpFile2);
}

// Test 3: Empty file checksum verification
$tmpFile3 = sys_get_temp_dir() . '/zvec_test_' . bin2hex(random_bytes(8)) . '.test';
try {
    file_put_contents($tmpFile3, '');
    $expectedHash = hash_file('sha256', $tmpFile3);
    Installer::verifyChecksum($tmpFile3, $expectedHash);
    echo "Test 3: Empty file checksum passed\n";
} finally {
    if (file_exists($tmpFile3)) unlink($tmpFile3);
}

// Test 4: Binary content checksum verification
$tmpFile4 = sys_get_temp_dir() . '/zvec_test_' . bin2hex(random_bytes(8)) . '.test';
try {
    file_put_contents($tmpFile4, "\x00\x01\x02\xFF\xFE\xFD");
    $expectedHash = hash_file('sha256', $tmpFile4);
    Installer::verifyChecksum($tmpFile4, $expectedHash);
    echo "Test 4: Binary file checksum passed\n";
} finally {
    if (file_exists($tmpFile4)) unlink($tmpFile4);
}

// Test 5: hash_equals works with re-computed hash from different source
$tmpFile5 = sys_get_temp_dir() . '/zvec_test_' . bin2hex(random_bytes(8)) . '.test';
try {
    file_put_contents($tmpFile5, 'test data');
    $recomputedHash = hash('sha256', 'test data');
    Installer::verifyChecksum($tmpFile5, $recomputedHash);
    echo "Test 5: Re-computed hash match passed\n";
} finally {
    if (file_exists($tmpFile5)) unlink($tmpFile5);
}

echo "PASS: All checksum tests completed\n";
?>
--EXPECT--
Test 1: Valid checksum passed
Test 2: Checksum mismatch thrown
Test 3: Empty file checksum passed
Test 4: Binary file checksum passed
Test 5: Re-computed hash match passed
PASS: All checksum tests completed
