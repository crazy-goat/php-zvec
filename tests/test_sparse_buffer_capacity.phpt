--TEST--
Sparse vector circular buffer: verify correct retrieval after buffer expansion to 256
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/sparse_buffer_' . uniqid();
try {
    // Create schema with sparse vector field
    $schema = new ZVecSchema('test_sparse_buf');
    $schema->addSparseVectorFp32('embedding', metricType: ZVecSchema::METRIC_IP);

    $coll = ZVec::create($path, schema: $schema);

    // Insert 20 documents with different sparse vectors
    $docs = [];
    for ($i = 0; $i < 20; $i++) {
        $doc = new ZVecDoc("doc_{$i}");
        $indices = [$i, $i + 100, $i + 200];
        $values = [(float)$i, (float)($i + 50), (float)($i + 100)];
        $doc->setSparseVectorFp32('embedding', $indices, $values);
        $docs[] = $doc;
    }
    $coll->insert(...$docs);

    // Fetch all 20 docs and verify each sparse vector is correct
    // This tests that the circular buffer (now 256 slots) handles
    // multiple sequential retrievals without data corruption
    $pks = array_map(fn($d) => "doc_" . ($d - 1), range(1, 20));
    $fetched = $coll->fetch(...$pks);

    $allCorrect = true;
    foreach ($fetched as $doc) {
        $pk = $doc->getPk();
        $idx = (int)str_replace('doc_', '', $pk);
        $sparse = $doc->getSparseVectorFp32('embedding');

        if ($sparse === null) {
            echo "FAIL: {$pk} returned null\n";
            $allCorrect = false;
            continue;
        }
        if (count($sparse['indices']) !== 3) {
            echo "FAIL: {$pk} expected 3 indices, got " . count($sparse['indices']) . "\n";
            $allCorrect = false;
            continue;
        }
        if ($sparse['indices'][0] !== $idx) {
            echo "FAIL: {$pk} index[0] expected {$idx}, got " . $sparse['indices'][0] . "\n";
            $allCorrect = false;
        }
        if (abs($sparse['values'][2] - ($idx + 100)) > 0.001) {
            echo "FAIL: {$pk} value[2] expected " . ($idx + 100) . ", got " . $sparse['values'][2] . "\n";
            $allCorrect = false;
        }
    }

    if ($allCorrect) {
        echo "All 20 sparse vectors retrieved correctly\n";
    }

    $coll->close();
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
All 20 sparse vectors retrieved correctly
