--TEST--
CollectionStats: structured stats access with typed getters
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/collstats_' . uniqid();
try {
    $schema = new ZVecSchema('test');
    $schema->addInt64('id')
        ->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $c = ZVec::create($path, $schema);

    // Test 1: Stats with no docs (index may already exist from schema)
    $stats = $c->getStatsStruct();
    echo "docCount (empty): " . $stats->getDocCount() . "\n";

    // Test 2: Create explicit index and verify
    $c->createIndex('vec', ZVecIndexParams::forHnsw(ZVecSchema::METRIC_IP));
    $stats2 = $c->getStatsStruct();
    $ic = $stats2->getIndexCount();
    echo "indexCount (after create): " . $ic . "\n";
    for ($i = 0; $i < $ic; $i++) {
        echo "  index[$i] name: " . $stats2->getIndexName($i) . "\n";
    }

    // Test 3: Insert docs, optimize, check completeness
    $c->insert(
        (new ZVecDoc('d1'))->setInt64('id', 1)->setVectorFp32('vec', [1.0, 0.0, 0.0, 0.0]),
        (new ZVecDoc('d2'))->setInt64('id', 2)->setVectorFp32('vec', [0.0, 1.0, 0.0, 0.0]),
    );
    echo "docCount (after insert): " . $c->getStatsStruct()->getDocCount() . "\n";

    $c->optimize();
    $stats3 = $c->getStatsStruct();
    echo "docCount (after optimize): " . $stats3->getDocCount() . "\n";
    echo "completeness (after optimize): " . $stats3->getIndexCompleteness(0) . "\n";

    // Test 4: getAllIndexCompleteness and toArray
    $allIc = $stats3->getAllIndexCompleteness();
    echo "getAllIndexCompleteness keys: " . implode(',', array_keys($allIc)) . "\n";

    $arr = $stats3->toArray();
    echo "toArray doc_count: " . $arr['doc_count'] . "\n";
    echo "toArray has vec: " . (isset($arr['index_completeness']['vec']) ? '1' : '0') . "\n";

    // Test 5: JSON stats backward compat
    $json = $c->stats();
    echo "JSON stats contains doc_count: " . (str_contains($json, 'doc_count') ? '1' : '0') . "\n";

    // Test 6: Error on out of range index
    $stats4 = $c->getStatsStruct();
    try {
        $stats4->getIndexName(999);
        echo "UNEXPECTED: Should have thrown\n";
    } catch (ZVecException $e) {
        echo "Out of range index correctly caught\n";
    }

    $c->close();

    // Test 7: Error on closed collection
    try {
        $c->getStatsStruct();
        echo "UNEXPECTED: Should have thrown\n";
    } catch (ZVecException $e) {
        echo "Closed collection error correctly caught\n";
    }

    echo "PASS\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECTF--
docCount (empty): 0
indexCount (after create): %d
%s index[0] name: vec
docCount (after insert): 2
docCount (after optimize): 2
completeness (after optimize): 1
getAllIndexCompleteness keys: vec
toArray doc_count: 2
toArray has vec: 1
JSON stats contains doc_count: 1
Out of range index correctly caught
Closed collection error correctly caught
PASS
