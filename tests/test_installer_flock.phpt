--TEST--
SEC-009: flock() prevents TOCTOU race condition in Installer::install()
--SKIPIF--
<?php if (PHP_OS_FAMILY === 'Windows') die('skip flock test not applicable on Windows'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';
require_once __DIR__ . '/../src/Installer.php';

use CrazyGoat\ZVec\Installer;

$path = __DIR__ . '/../test_dbs/installer_flock_' . uniqid();
try {
    // --- Test 1: Verify lock file is created and cleaned up ---
    $lockDir = sys_get_temp_dir() . '/zvec_flock_test_' . bin2hex(random_bytes(8));
    mkdir($lockDir, 0755, true);

    $lockFile = $lockDir . '/install.lock';
    $lockFh = fopen($lockFile, 'w+');
    if (!$lockFh) {
        echo "FAIL: Could not create lock file\n";
        exit(1);
    }

    $locked = flock($lockFh, LOCK_EX);
    echo "Lock acquired: " . ($locked ? 'yes' : 'no') . "\n";
    echo "Lock file exists: " . (file_exists($lockFile) ? 'yes' : 'no') . "\n";

    flock($lockFh, LOCK_UN);
    fclose($lockFh);
    @unlink($lockFile);
    echo "Lock file after cleanup: " . (file_exists($lockFile) ? 'yes' : 'no') . "\n";

    exec("rm -rf " . escapeshellarg($lockDir));

    // --- Test 2: Concurrent lock acquisition is serialized ---
    $lockDir = sys_get_temp_dir() . '/zvec_flock_concurrent_' . bin2hex(random_bytes(8));
    mkdir($lockDir, 0755, true);
    $lockFile = $lockDir . '/install.lock';
    $logFile = $lockDir . '/log.txt';
    file_put_contents($logFile, '');

    $procs = [];
    for ($i = 0; $i < 3; $i++) {
        $code = <<<'PHP'
<?php
$lockFile = $argv[1];
$logFile = $argv[2];
$id = $argv[3];

$fh = fopen($lockFile, 'w+');
if (!$fh) exit(1);

if (flock($fh, LOCK_EX)) {
    file_put_contents($logFile, "START $id\n", FILE_APPEND);
    usleep(50000);
    file_put_contents($logFile, "END $id\n", FILE_APPEND);
    flock($fh, LOCK_UN);
}
fclose($fh);
PHP;
        $tmpScript = $lockDir . "/child_{$i}.php";
        file_put_contents($tmpScript, $code);
        $procs[] = proc_open(
            "php " . escapeshellarg($tmpScript) . " " . escapeshellarg($lockFile) . " " . escapeshellarg($logFile) . " $i",
            [STDIN, STDOUT, STDERR],
            $pipes
        );
    }

    foreach ($procs as $proc) {
        proc_close($proc);
    }

    $lines = array_filter(explode("\n", trim(file_get_contents($logFile))));
    echo "Log entries: " . count($lines) . "\n";

    $active = 0;
    $maxActive = 0;
    $overlapped = false;
    foreach ($lines as $line) {
        if (str_starts_with($line, 'START')) {
            $active++;
            if ($active > 1) {
                $overlapped = true;
            }
            $maxActive = max($maxActive, $active);
        } elseif (str_starts_with($line, 'END')) {
            $active--;
        }
    }

    echo "Max concurrent locks: $maxActive\n";
    echo "Overlapped: " . ($overlapped ? 'yes' : 'no') . "\n";

    // --- Test 3: Verify flock() code exists in Installer.php ---
    $source = file_get_contents(__DIR__ . '/../src/Installer.php');
    $hasFlock = str_contains($source, 'flock($lockFh, LOCK_EX)');
    $hasUnlock = str_contains($source, 'flock($lockFh, LOCK_UN)');
    $hasLockFile = str_contains($source, "install.lock");
    $hasDoubleCheck = substr_count($source, 'file_exists($libPath)') >= 2;

    echo "flock LOCK_EX in source: " . ($hasFlock ? 'yes' : 'no') . "\n";
    echo "flock LOCK_UN in source: " . ($hasUnlock ? 'yes' : 'no') . "\n";
    echo "install.lock in source: " . ($hasLockFile ? 'yes' : 'no') . "\n";
    echo "double-check pattern: " . ($hasDoubleCheck ? 'yes' : 'no') . "\n";

    exec("rm -rf " . escapeshellarg($lockDir));

    // --- Test 4: Behavioral — double-check pattern skips download when lib already exists ---
    echo "---\n";
    $realLibPath = __DIR__ . '/../lib/' . (PHP_OS_FAMILY === 'Darwin' ? 'libzvec_ffi.dylib' : 'libzvec_ffi.so');
    $dummyCreated = false;
    try {
        // Create dummy library file so Installer::install() sees it as already installed
        file_put_contents($realLibPath, 'dummy');
        $dummyCreated = true;

        // This should detect the file and return early (no download, no exception)
        ob_start();
        Installer::install('v0.4.0');
        $output = ob_get_clean();

        if (str_contains($output, 'already installed')) {
            echo "Test 4: Double-check detected existing library\n";
        } else {
            echo "FAIL: Expected 'already installed' message\n";
            exit(1);
        }

        // Verify lock file persists as sentinel (never deleted — ensures same inode for flock)
        $libDir = __DIR__ . '/../lib';
        $lockFilePath = $libDir . '/install.lock';
        if (file_exists($lockFilePath)) {
            echo "Test 4: Lock file persists as sentinel\n";
        } else {
            echo "FAIL: Lock file was deleted (sentinel should persist)\n";
            exit(1);
        }

        echo "Test 4 PASS\n";
    } finally {
        // Clean up dummy library
        if ($dummyCreated && file_exists($realLibPath)) {
            unlink($realLibPath);
        }
        // Clean up lock file left by test (Installer's own finally never deletes it)
        $lockFilePath = __DIR__ . '/../lib/install.lock';
        if (file_exists($lockFilePath)) {
            unlink($lockFilePath);
        }
    }

    echo "DONE\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Lock acquired: yes
Lock file exists: yes
Lock file after cleanup: no
Log entries: 6
Max concurrent locks: 1
Overlapped: no
flock LOCK_EX in source: yes
flock LOCK_UN in source: yes
install.lock in source: yes
double-check pattern: yes
---
Test 4: Double-check detected existing library
Test 4: Lock file persists as sentinel
Test 4 PASS
DONE
