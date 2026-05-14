--TEST--
QUANTIZE_RABITQ and METRIC_MIPSL2 constants
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

// Verify constant values match C++ enum definitions
assert(ZVec::QUANTIZE_RABITQ === 4, "QUANTIZE_RABITQ should be 4");
echo "QUANTIZE_RABITQ = " . ZVec::QUANTIZE_RABITQ . "\n";

assert(ZVecSchema::METRIC_MIPSL2 === 4, "METRIC_MIPSL2 should be 4");
echo "METRIC_MIPSL2 = " . ZVecSchema::METRIC_MIPSL2 . "\n";

$path = __DIR__ . '/../test_dbs/rabitq_mipsl2_' . uniqid();
try {
    $schema = new ZVecSchema('test');
    $schema->addInt64('id', nullable: false, withInvertIndex: true)
        ->addVectorFp32('vec', dimension: 8, metricType: ZVecSchema::METRIC_MIPSL2);

    $collection = ZVec::create($path, $schema);

    // Insert docs
    for ($i = 1; $i <= 10; $i++) {
        $vec = [];
        for ($j = 0; $j < 8; $j++) {
            $vec[] = 0.01 * $i + 0.001 * $j;
        }
        $doc = new ZVecDoc("doc$i");
        $doc->setInt64('id', $i)->setVectorFp32('vec', $vec);
        $collection->insert($doc);
    }
    $collection->optimize();

    // Query with METRIC_MIPSL2 field
    $queryVec = [];
    for ($j = 0; $j < 8; $j++) {
        $queryVec[] = 0.01 + 0.001 * $j;
    }
    $results = $collection->query('vec', $queryVec, topk: 5);
    assert(count($results) === 5, "Expected 5 results with METRIC_MIPSL2");
    echo "Query with METRIC_MIPSL2 field returns " . count($results) . " results\n";

    // Create HNSW index with QUANTIZE_RABITQ
    $collection->createHnswIndex(
        'vec',
        metricType: ZVecSchema::METRIC_MIPSL2,
        m: 16,
        efConstruction: 200,
        quantizeType: ZVec::QUANTIZE_RABITQ
    );
    $collection->flush();
    $collection->optimize();
    echo "HNSW index with QUANTIZE_RABITQ created\n";

    $rabitqResults = $collection->query('vec', $queryVec, topk: 5);
    assert(count($rabitqResults) === 5, "Expected 5 results with QUANTIZE_RABITQ");
    echo "Query with QUANTIZE_RABITQ HNSW returns " . count($rabitqResults) . " results\n";

    // Create Flat index with QUANTIZE_RABITQ
    $collection->dropIndex('vec');
    $collection->flush();
    $collection->createFlatIndex(
        'vec',
        metricType: ZVecSchema::METRIC_MIPSL2,
        quantizeType: ZVec::QUANTIZE_RABITQ
    );
    $collection->flush();
    $collection->optimize();
    echo "Flat index with QUANTIZE_RABITQ created\n";

    $flatResults = $collection->query('vec', $queryVec, topk: 5);
    assert(count($flatResults) === 5, "Expected 5 results with QUANTIZE_RABITQ Flat");
    echo "Query with QUANTIZE_RABITQ Flat returns " . count($flatResults) . " results\n";

    echo "PASS: All constants and index operations work\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
QUANTIZE_RABITQ = 4
METRIC_MIPSL2 = 4
Query with METRIC_MIPSL2 field returns 5 results
HNSW index with QUANTIZE_RABITQ created
Query with QUANTIZE_RABITQ HNSW returns 5 results
Flat index with QUANTIZE_RABITQ created
Query with QUANTIZE_RABITQ Flat returns 5 results
PASS: All constants and index operations work
