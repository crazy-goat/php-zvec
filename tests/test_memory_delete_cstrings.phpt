--TEST--
Memory leak: delete operations free C strings properly
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/mem_delete_cstrings_' . uniqid();

try {
    $schema = new ZVecSchema('mem_delete_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 100);

    $memBefore = memory_get_usage();

    // 30x insert/delete cycle (exercises toCStringArray/freeCStringArray in delete)
    for ($i = 0; $i < 30; $i++) {
        // Insert 10 documents
        for ($j = 0; $j < 10; $j++) {
            $doc = new ZVecDoc("pk_{$i}_{$j}");
            $doc->setInt64('id', $j)
                ->setVectorFp32('embedding', [(float)$j, 1.0, 0.0, 0.0]);
            $c->insert($doc);
        }

        // Delete by PKs (multiple at once)
        $pks = [];
        for ($j = 0; $j < 10; $j++) {
            $pks[] = "pk_{$i}_{$j}";
        }
        $c->delete(...$pks);
    }

    // Also test deleteByFilter
    for ($i = 0; $i < 10; $i++) {
        $doc = new ZVecDoc("filter_pk_{$i}");
        $doc->setInt64('id', $i)
            ->setVectorFp32('embedding', [(float)$i, 0.0, 0.0, 0.0]);
        $c->insert($doc);
    }

    for ($i = 0; $i < 10; $i++) {
        $c->deleteByFilter("id = {$i}");
    }

    // Test fetch (also uses toCStringArray)
    for ($i = 0; $i < 20; $i++) {
        $doc = new ZVecDoc("fetch_pk_{$i}");
        $doc->setInt64('id', $i)
            ->setVectorFp32('embedding', [(float)$i, 0.0, 0.0, 0.0]);
        $c->insert($doc);
    }

    for ($i = 0; $i < 20; $i++) {
        $results = $c->fetch("fetch_pk_{$i}");
        assert(count($results) === 1, 'Should fetch exactly one doc');
    }

    // Cleanup fetched docs
    $fetchPks = [];
    for ($i = 0; $i < 20; $i++) {
        $fetchPks[] = "fetch_pk_{$i}";
    }
    $c->delete(...$fetchPks);

    $memAfter = memory_get_usage();
    $delta = $memAfter - $memBefore;

    // Allow 500KB tolerance
    if ($delta > 500 * 1024) {
        echo "FAIL: Memory grew by {$delta} bytes (threshold 500KB)\n";
        exit(1);
    }

    echo "Delete/fetch cycles OK (delta: {$delta} bytes)\n";
    $c->close();
    echo "PASS: Delete operations free C strings properly\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECTF--
Delete/fetch cycles OK (delta: %d bytes)
PASS: Delete operations free C strings properly
