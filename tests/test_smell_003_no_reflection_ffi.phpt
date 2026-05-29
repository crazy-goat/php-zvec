--TEST--
SMELL-003: No ReflectionClass used for FFI access — ZVec::ffi() is public
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

// Test 1: ZVec::ffi() is callable (public)
$ffi = ZVec::ffi();
if ($ffi === null) {
    echo "FAIL: ZVec::ffi() returned null\n";
    exit(1);
}
echo "PASS: ZVec::ffi() is public and callable\n";

// Test 2: Verify no ReflectionClass in source files for FFI access
$srcDir = __DIR__ . '/../src/';
$files = glob($srcDir . '*.php');
$reflectionCount = 0;
foreach ($files as $file) {
    $content = file_get_contents($file);
    if (preg_match('/ReflectionClass.*ZVec/', $content)) {
        $reflectionCount++;
        echo "WARN: ReflectionClass hack found in $file\n";
    }
}
if ($reflectionCount === 0) {
    echo "PASS: No ReflectionClass hacks found in source files\n";
} else {
    echo "FAIL: Found $reflectionCount ReflectionClass hacks\n";
    exit(1);
}

// Test 3: Verify all helper classes can access FFI without reflection
// This exercises the ffi() method in each class that previously used reflection
$path = __DIR__ . '/../test_dbs/smell_003_' . uniqid();
try {
    $schema = new ZVecSchema('smell003');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $doc = new ZVecDoc("doc1");
    $doc->setInt64('id', 1);
    $doc->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc);
    $c->optimize();

    // Query (exercises ZVecVectorQuery::ffi())
    $results = $c->query('v', [0.1, 0.2, 0.3, 0.4], topk: 5);
    if (count($results) >= 1) {
        echo "PASS: query() works via direct FFI access\n";
    } else {
        echo "FAIL: query() returned no results\n";
        exit(1);
    }

    // Schema introspection (exercises ZVecFieldSchema::ffi())
    $fields = $c->schema();
    if (is_string($fields) && strlen($fields) > 0) {
        echo "PASS: schema() works via direct FFI access\n";
    } else {
        echo "FAIL: schema() returned empty\n";
        exit(1);
    }

    // Stats (exercises ZVecCollectionStats::ffi())
    $stats = $c->stats();
    echo "PASS: stats() works via direct FFI access\n";

    // Index params (exercises ZVecIndexParams::ffi())
    $idxParams = ZVecIndexParams::forHnsw(ZVecSchema::METRIC_IP);
    echo "PASS: ZVecIndexParams works via direct FFI access\n";

    $c->close();
    $c->destroy();
} catch (ZVecException $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    exec("rm -rf " . escapeshellarg($path));
}

echo "PASS: SMELL-003 - all tests passed\n";
?>
--EXPECT--
PASS: ZVec::ffi() is public and callable
PASS: No ReflectionClass hacks found in source files
PASS: query() works via direct FFI access
PASS: schema() works via direct FFI access
PASS: stats() works via direct FFI access
PASS: ZVecIndexParams works via direct FFI access
PASS: SMELL-003 - all tests passed
