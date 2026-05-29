--TEST--
Column ops: alterColumn rename AND type in one call — limitation test
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/alter_rename_type_' . uniqid();
try {
    $schema = new ZVecSchema('test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false)
        ->addInt64('value', nullable: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    $doc = new ZVecDoc('doc1');
    $doc->setInt64('id', 1)
        ->setInt64('value', 100)
        ->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc);
    $c->optimize();

    // Test: Rename AND change type in one call — should fail (limitation)
    try {
        $c->alterColumn('value', newName: 'renamed_value', newDataType: ZVec::TYPE_FLOAT, nullable: true);
        echo "UNEXPECTED: rename+type in one call succeeded\n";
    } catch (ZVecException $e) {
        echo "Correctly rejected rename+type in one call: " . $e->getMessage() . "\n";
    }

    // Verify original column is unchanged
    $fetched = $c->fetch('doc1');
    assert(count($fetched) === 1, 'Expected 1 doc');
    assert($fetched[0]->getInt64('value') === 100, 'Expected value=100 unchanged');
    echo "Original column unchanged after failed alter OK\n";

    // Test: Rename first, then change type — should succeed (separate calls)
    $c->alterColumn('value', newName: 'renamed_value');
    echo "Rename in separate call OK\n";

    $c->alterColumn('renamed_value', newDataType: ZVec::TYPE_FLOAT, nullable: true);
    echo "Type change in separate call OK\n";

    // Verify the column works with new name and type
    $doc2 = new ZVecDoc('doc2');
    $doc2->setInt64('id', 2)
        ->setFloat('renamed_value', 3.14)
        ->setVectorFp32('v', [0.2, 0.3, 0.4, 0.5]);
    $c->insert($doc2);
    $c->optimize();

    $results = $c->query('v', [0.1, 0.2, 0.3, 0.4], topk: 5);
    echo "Query after separate rename+type works, returned " . count($results) . " results\n";

    $c->close();
    echo "PASS: alterColumn rename+type limitation verified\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECTF--
Correctly rejected rename+type in one call: cannot specify both rename and new column schema
Original column unchanged after failed alter OK
Rename in separate call OK
Type change in separate call OK
Query after separate rename+type works, returned %d results
PASS: alterColumn rename+type limitation verified
