--TEST--
Query operations: queryById with non-existent document throws ZVecException
--SKIPIF--
<?php
if (extension_loaded('zvec')) die('skip Native zvec extension loaded (use FFI)');
if (!extension_loaded('ffi')) die('skip FFI extension not available');
?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/qbid_nonexist_' . uniqid();
try {
    $schema = new ZVecSchema('test');
    $schema->addVectorFp32('v', dimension: 2, metricType: ZVecSchema::METRIC_IP);
    $schema->addInt64('id');
    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('v');

    $doc = new ZVecDoc('existing');
    $doc->setVectorFp32('v', [1.0, 0.0])->setInt64('id', 1);
    $c->insert($doc);
    $c->optimize();

    // Test 1: queryById with existing doc works
    $results = $c->queryById('v', 'existing', topk: 3);
    echo 'Existing doc query: ' . count($results) . " results\n";

    // Test 2: queryById with non-existent doc throws
    try {
        $c->queryById('v', 'nonexistent_doc', topk: 3);
        echo "FAIL: Expected ZVecException for non-existent doc\n";
    } catch (ZVecException $e) {
        echo "Non-existent doc throws: " . $e->getMessage() . "\n";
    }

    // Test 3: queryById with empty docId throws
    try {
        $c->queryById('v', '', topk: 3);
        echo "FAIL: Expected ZVecException for empty docId\n";
    } catch (ZVecException $e) {
        echo "Empty docId throws: " . $e->getMessage() . "\n";
    }

    // Test 4: queryById with empty fieldName throws
    try {
        $c->queryById('', 'existing', topk: 3);
        echo "FAIL: Expected ZVecException for empty field name\n";
    } catch (ZVecException $e) {
        echo "Empty field name throws: " . $e->getMessage() . "\n";
    }

    echo "DONE\n";
} finally {
    if (isset($c)) { try { $c->destroy(); } catch (Exception $e) {} }
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Existing doc query: 1 results
Non-existent doc throws: Document not found: nonexistent_doc
Empty docId throws: Document ID must not be empty
Empty field name throws: Field name must not be empty
DONE