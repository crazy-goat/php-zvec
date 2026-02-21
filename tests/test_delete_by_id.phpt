--TEST--
Data operations: delete documents by primary key
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/delete_id_' . uniqid();

try {
    $schema = new ZVecSchema('delete_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('name', nullable: true, withInvertIndex: true)
        ->addFloat('score', nullable: true)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 200);

    // Insert test documents
    $docs = [];
    for ($i = 1; $i <= 5; $i++) {
        $doc = new ZVecDoc("doc$i");
        $doc->setInt64('id', $i)
            ->setString('name', "User$i")
            ->setFloat('score', 80.0 + $i)
            ->setVectorFp32('embedding', [1.0 * $i, 0.0, 0.0, 0.0]);
        $docs[] = $doc;
    }
    $c->insert(...$docs);
    echo "Inserted 5 documents\n";

    // Test: Delete single document by ID
    $c->delete('doc1');
    echo "Delete single OK\n";

    // Verify document was removed
    $fetched = $c->fetch('doc1');
    assert(count($fetched) === 0, 'doc1 should be removed');
    echo "Verify single removal OK\n";

    // Test: Delete multiple documents by IDs
    $c->delete('doc2', 'doc3');
    echo "Delete multiple OK\n";

    // Verify documents were removed
    $fetched = $c->fetch('doc2', 'doc3', 'doc4', 'doc5');
    assert(count($fetched) === 2, 'Should have 2 documents remaining');
    $pks = array_map(fn($d) => $d->getPk(), $fetched);
    assert(in_array('doc4', $pks), 'doc4 should exist');
    assert(in_array('doc5', $pks), 'doc5 should exist');
    assert(!in_array('doc2', $pks), 'doc2 should be removed');
    assert(!in_array('doc3', $pks), 'doc3 should be removed');
    echo "Verify multiple removal OK\n";

    // Test: Delete non-existent ID (should not error)
    try {
        $c->delete('nonexistent');
        echo "Non-existent delete handled gracefully\n";
    } catch (ZVecException $e) {
        echo "Non-existent delete raised exception\n";
    }

    // Test: Delete mix of existing and non-existent
    try {
        $c->delete('doc4', 'nonexistent2');
        $fetched = $c->fetch('doc4', 'doc5');
        assert(count($fetched) === 1, 'Should have 1 document remaining');
        assert($fetched[0]->getPk() === 'doc5', 'Only doc5 should remain');
        echo "Mixed delete OK\n";
    } catch (ZVecException $e) {
        // Some implementations may fail on non-existent IDs
        echo "Mixed delete handled\n";
    }

    $c->close();
    echo "PASS: Delete by ID operations work\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Inserted 5 documents
Delete single OK
Verify single removal OK
Delete multiple OK
Verify multiple removal OK
Non-existent delete handled gracefully
Mixed delete OK
PASS: Delete by ID operations work
