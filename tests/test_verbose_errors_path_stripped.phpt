--TEST--
Verbose errors: C++ file path stripped to basename only
--SKIPIF--
<?php if (extension_loaded('zvec')) die('skip verboseErrors is FFI-only, not available with native zvec extension'); ?>
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN, verboseErrors: true);

try {
    $c = ZVec::open('/tmp/nonexistent_' . uniqid());
    echo "FAIL: Should have thrown exception\n";
    exit(1);
} catch (ZVecException $e) {
    $file = $e->getErrorFile();

    if ($file === null) {
        echo "FAIL: file is null\n";
        exit(1);
    }

    if (!str_contains($file, '/')) {
        echo "PASS: C++ path stripped to basename: $file\n";
    } else {
        echo "FAIL: C++ path still contains directory separator: $file\n";
        exit(1);
    }
}
?>
--EXPECT--
PASS: C++ path stripped to basename: zvec_ffi.cc
