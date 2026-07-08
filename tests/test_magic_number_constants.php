<?php
require_once __DIR__ . '/../src/ZVec.php';

// Verify all new constants have expected values
$tests = [
    ['ZVec::DEFAULT_MAX_BUFFER_SIZE', ZVec::DEFAULT_MAX_BUFFER_SIZE, 67108864],
    ['ZVec::SCHEMA_BUFFER_SIZE', ZVec::SCHEMA_BUFFER_SIZE, 8192],
    ['ZVec::PATH_BUFFER_SIZE', ZVec::PATH_BUFFER_SIZE, 4096],
    ['ZVec::MAX_STRING_BUFFER_SIZE', ZVec::MAX_STRING_BUFFER_SIZE, 1048576],
    ['ZVec::BYTES_PER_MB', ZVec::BYTES_PER_MB, 1048576],
    ['ZVec::DEFAULT_HNSW_M', ZVec::DEFAULT_HNSW_M, 50],
    ['ZVec::DEFAULT_HNSW_EF_CONSTRUCTION', ZVec::DEFAULT_HNSW_EF_CONSTRUCTION, 500],
];

$failed = 0;
foreach ($tests as [$name, $actual, $expected]) {
    if ($actual === $expected) {
        echo "OK: $name = $actual\n";
    } else {
        echo "FAIL: $name = $actual (expected $expected)\n";
        $failed++;
    }
}

// Verify no magic numbers remain in ZVec.php (outside constant declarations)
$source = file_get_contents(__DIR__ . '/../src/ZVec.php');
$lines = explode("\n", $source);

// Helper: returns true if line is a PHPDoc comment line
$isPhpDoc = function(string $line): bool {
    $t = trim($line);
    return str_starts_with($t, '*') || str_starts_with($t, '/**');
};

// Check for 67108864 (64 MB) - should only appear in constant declaration or PHPDoc
$found67108864 = [];
foreach ($lines as $i => $line) {
    if (strpos($line, '67108864') !== false && !$isPhpDoc($line)) {
        $found67108864[] = $i + 1;
    }
}
if (count($found67108864) === 1 && strpos($lines[$found67108864[0] - 1], 'DEFAULT_MAX_BUFFER_SIZE') !== false) {
    echo "OK: 67108864 only in constant declaration\n";
} else {
    echo "FAIL: 67108864 found at non-PHPDoc lines: " . implode(', ', $found67108864) . "\n";
    $failed++;
}

// Check for 8192 - should only appear in constant declaration or PHPDoc
$found8192 = [];
foreach ($lines as $i => $line) {
    if (preg_match('/\b8192\b/', $line) && !$isPhpDoc($line)) {
        $found8192[] = $i + 1;
    }
}
if (count($found8192) === 1 && strpos($lines[$found8192[0] - 1], 'SCHEMA_BUFFER_SIZE') !== false) {
    echo "OK: 8192 only in constant declaration\n";
} else {
    echo "FAIL: 8192 found at non-PHPDoc lines: " . implode(', ', $found8192) . "\n";
    $failed++;
}

// Check for 4096 - should only appear in constant declaration or PHPDoc
$found4096 = [];
foreach ($lines as $i => $line) {
    if (preg_match('/\b4096\b/', $line) && !$isPhpDoc($line)) {
        $found4096[] = $i + 1;
    }
}
if (count($found4096) === 1 && strpos($lines[$found4096[0] - 1], 'PATH_BUFFER_SIZE') !== false) {
    echo "OK: 4096 only in constant declaration\n";
} else {
    echo "FAIL: 4096 found at non-PHPDoc lines: " . implode(', ', $found4096) . "\n";
    $failed++;
}

// Check for 1048576 - should only appear in constant declarations or PHPDoc
$found1048576 = [];
foreach ($lines as $i => $line) {
    if (preg_match('/\b1048576\b/', $line) && !$isPhpDoc($line)) {
        $found1048576[] = $i + 1;
    }
}
if (count($found1048576) === 2) {
    $bothConstants = true;
    foreach ($found1048576 as $lineNum) {
        $line = $lines[$lineNum - 1];
        if (strpos($line, 'MAX_STRING_BUFFER_SIZE') === false && strpos($line, 'BYTES_PER_MB') === false) {
            $bothConstants = false;
        }
    }
    if ($bothConstants) {
        echo "OK: 1048576 only in constant declarations\n";
    } else {
        echo "FAIL: 1048576 found at non-constant lines: " . implode(', ', $found1048576) . "\n";
        $failed++;
    }
} else {
    echo "FAIL: 1048576 found at lines: " . implode(', ', $found1048576) . "\n";
    $failed++;
}

// Check for 50 as default HNSW m - should only appear in constant declaration, deprecated methods, and PHPDoc
$found50 = [];
foreach ($lines as $i => $line) {
    if (preg_match('/\b50\b/', $line) && strpos($line, 'LOG_WARN') === false && !$isPhpDoc($line)) {
        $found50[] = $i + 1;
    }
}
$valid50 = true;
foreach ($found50 as $lineNum) {
    $line = $lines[$lineNum - 1];
    if (strpos($line, 'DEFAULT_HNSW_M') === false && 
        strpos($line, 'deprecated') === false &&
        strpos($line, '@deprecated') === false) {
        $valid50 = false;
    }
}
if ($valid50 && count($found50) >= 1) {
    echo "OK: 50 only in constant declaration and deprecated methods\n";
} else {
    echo "FAIL: 50 found at lines: " . implode(', ', $found50) . "\n";
    $failed++;
}

// Check for 500 as default HNSW efConstruction - should only appear in constant declaration, deprecated methods, and PHPDoc
$found500 = [];
foreach ($lines as $i => $line) {
    if (preg_match('/\b500\b/', $line) && !$isPhpDoc($line)) {
        $found500[] = $i + 1;
    }
}
$valid500 = true;
foreach ($found500 as $lineNum) {
    $line = $lines[$lineNum - 1];
    if (strpos($line, 'DEFAULT_HNSW_EF_CONSTRUCTION') === false && 
        strpos($line, 'deprecated') === false &&
        strpos($line, '@deprecated') === false) {
        $valid500 = false;
    }
}
if ($valid500 && count($found500) >= 1) {
    echo "OK: 500 only in constant declaration and deprecated methods\n";
} else {
    echo "FAIL: 500 found at lines: " . implode(', ', $found500) . "\n";
    $failed++;
}

// Verify ZVecIndexParams uses the constants
$indexParamsSource = file_get_contents(__DIR__ . '/../src/ZVecIndexParams.php');
if (strpos($indexParamsSource, 'ZVec::DEFAULT_HNSW_M') !== false) {
    echo "OK: ZVecIndexParams uses ZVec::DEFAULT_HNSW_M\n";
} else {
    echo "FAIL: ZVecIndexParams does not use ZVec::DEFAULT_HNSW_M\n";
    $failed++;
}
if (strpos($indexParamsSource, 'ZVec::DEFAULT_HNSW_EF_CONSTRUCTION') !== false) {
    echo "OK: ZVecIndexParams uses ZVec::DEFAULT_HNSW_EF_CONSTRUCTION\n";
} else {
    echo "FAIL: ZVecIndexParams does not use ZVec::DEFAULT_HNSW_EF_CONSTRUCTION\n";
    $failed++;
}

// Summary
echo "\n";
if ($failed === 0) {
    echo "All tests passed!\n";
} else {
    echo "FAILED: $failed test(s) failed\n";
}
?>
