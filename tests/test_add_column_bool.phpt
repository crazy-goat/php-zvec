--TEST--
Column ops: addColumnBool is not supported for column DDL (BOOL is schema-only)
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/add_bool_' . uniqid();
try {
    $schema = new ZVecSchema('test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false)
        ->addBool('active', nullable: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    // Insert doc with bool field set via schema
    $doc = new ZVecDoc('d1');
    $doc->setInt64('id', 1)
        ->setBool('active', true)
        ->setVectorFp32('v', [1.0, 0.0, 0.0, 0.0]);
    $c->insert($doc);
    $c->optimize();

    // Verify bool field works via schema-defined column
    $fetched = $c->fetch('d1');
    assert(count($fetched) === 1, 'Expected 1 doc');
    $active = $fetched[0]->getBool('active');
    echo "d1 active=" . ($active ? 'true' : 'false') . " (expected: true)\n";

    // Test: addColumnBool should fail — BOOL not supported for column DDL
    try {
        $c->addColumnBool('flag', nullable: true, defaultExpr: 'false');
        echo "UNEXPECTED: addColumnBool succeeded\n";
    } catch (ZVecException $e) {
        echo "addColumnBool correctly rejected: " . $e->getMessage() . "\n";
    }

    // Test: Insert doc with bool field set to false
    $doc2 = new ZVecDoc('d2');
    $doc2->setInt64('id', 2)
        ->setBool('active', false)
        ->setVectorFp32('v', [0.0, 1.0, 0.0, 0.0]);
    $c->insert($doc2);
    $c->optimize();

    $fetched2 = $c->fetch('d2');
    assert(count($fetched2) === 1, 'Expected 1 doc');
    $active2 = $fetched2[0]->getBool('active');
    echo "d2 active=" . ($active2 ? 'true' : 'false') . " (expected: false)\n";

    // Test: Query with bool in output fields
    $results = $c->query('v', [1.0, 0.0, 0.0, 0.0], topk: 3, outputFields: ['id', 'active']);
    assert(count($results) >= 2, 'Expected at least 2 results');
    echo "Query with outputFields returned " . count($results) . " results\n";

    $c->close();
    echo "PASS: addColumnBool limitation verified; BOOL works via schema\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
d1 active=true (expected: true)
addColumnBool correctly rejected: Only support basic numeric data type [int32, int64, uint32, uint64, float, double]: FieldSchema{name:'flag',data_type:BOOL,nullable:true,dimension:0,index_params:null}
d2 active=false (expected: false)
Query with outputFields returned 2 results
PASS: addColumnBool limitation verified; BOOL works via schema
