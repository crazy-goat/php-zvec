--TEST--
Data operations: upsert new and existing documents
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/upsert_' . uniqid();

try {
    $schema = new ZVecSchema('upsert_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('name', nullable: true, withInvertIndex: true)
        ->addFloat('score', nullable: true)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 200);

    // Test: Upsert new document (acts as insert)
    $doc1 = new ZVecDoc('doc1');
    $doc1->setInt64('id', 1)
        ->setString('name', 'Alice')
        ->setFloat('score', 95.5)
        ->setVectorFp32('embedding', [1.0, 0.0, 0.0, 0.0]);
    $c->upsert($doc1);
    echo "Upsert new document OK\n";

    // Verify it was inserted
    $fetched = $c->fetch('doc1');
    assert(count($fetched) === 1, 'Should fetch 1 document');
    assert($fetched[0]->getString('name') === 'Alice', 'Name should be Alice');
    echo "Verify new document OK\n";

    // Test: Upsert existing document (acts as update)
    $doc1Updated = new ZVecDoc('doc1');
    $doc1Updated->setInt64('id', 1)
        ->setString('name', 'Alice Updated')
        ->setFloat('score', 98.0)
        ->setVectorFp32('embedding', [0.9, 0.1, 0.0, 0.0]);
    $c->upsert($doc1Updated);
    echo "Upsert existing document OK\n";

    // Verify it was updated
    $fetched = $c->fetch('doc1');
    assert($fetched[0]->getString('name') === 'Alice Updated', 'Name should be updated');
    assert($fetched[0]->getFloat('score') === 98.0, 'Score should be updated');
    echo "Verify updated document OK\n";

    // Test: Upsert batch mix of new and existing
    $doc2 = new ZVecDoc('doc2');
    $doc2->setInt64('id', 2)
        ->setString('name', 'Bob')
        ->setFloat('score', 87.0)
        ->setVectorFp32('embedding', [0.0, 1.0, 0.0, 0.0]);
    
    $doc1Reupdated = new ZVecDoc('doc1');
    $doc1Reupdated->setInt64('id', 1)
        ->setString('name', 'Alice Final')
        ->setFloat('score', 99.0)
        ->setVectorFp32('embedding', [0.8, 0.2, 0.0, 0.0]);
    
    $doc3 = new ZVecDoc('doc3');
    $doc3->setInt64('id', 3)
        ->setString('name', 'Charlie')
        ->setFloat('score', 92.0)
        ->setVectorFp32('embedding', [0.0, 0.0, 1.0, 0.0]);
    
    $c->upsert($doc2, $doc1Reupdated, $doc3);
    echo "Upsert batch mix OK\n";

    // Verify all 3 documents
    $fetched = $c->fetch('doc1', 'doc2', 'doc3');
    assert(count($fetched) === 3, 'Should fetch 3 documents');
    
    // Check doc1 was updated
    foreach ($fetched as $doc) {
        if ($doc->getPk() === 'doc1') {
            assert($doc->getString('name') === 'Alice Final', 'doc1 name should be Alice Final');
            assert($doc->getFloat('score') === 99.0, 'doc1 score should be 99.0');
        }
    }
    echo "Verify batch results OK\n";

    $c->close();
    echo "PASS: Upsert operations work\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Upsert new document OK
Verify new document OK
Upsert existing document OK
Verify updated document OK
Upsert batch mix OK
Verify batch results OK
PASS: Upsert operations work
