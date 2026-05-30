--TEST--
Memory leak: collection lifecycle (create/open/close/destroy) does not leak
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$basePath = __DIR__ . '/../test_dbs/mem_lifecycle_';
$paths = [];

try {
    $memBefore = memory_get_usage();

    // 50x create/open/close/destroy cycle
    for ($i = 0; $i < 50; $i++) {
        $path = $basePath . uniqid();
        $paths[] = $path;

        $schema = new ZVecSchema('mem_test');
        $schema->setMaxDocCountPerSegment(1000)
            ->addInt64('id', nullable: false)
            ->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);

        $c = ZVec::create($path, $schema);
        $doc = new ZVecDoc('doc1');
        $doc->setInt64('id', 1)->setVectorFp32('vec', [1.0, 0.0, 0.0, 0.0]);
        $c->insert($doc);
        $c->optimize();
        $c->close();

        // Re-open and destroy
        $c2 = ZVec::open($path);
        $c2->destroy();
    }

    $memAfter = memory_get_usage();
    $delta = $memAfter - $memBefore;

    // Allow 500KB tolerance for PHP internal allocations
    if ($delta > 500 * 1024) {
        echo "FAIL: Memory grew by {$delta} bytes (threshold 500KB)\n";
        exit(1);
    }

    echo "50x lifecycle OK (delta: {$delta} bytes)\n";
    echo "PASS: Collection lifecycle does not leak\n";
} finally {
    foreach ($paths as $p) {
        exec("rm -rf " . escapeshellarg($p));
    }
}
?>
--EXPECTF--
50x lifecycle OK (delta: %d bytes)
PASS: Collection lifecycle does not leak
