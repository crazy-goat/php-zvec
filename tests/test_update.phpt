--TEST--
Data operations: update partial fields in documents
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/update_' . uniqid();

try {
    $schema = new ZVecSchema('update_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('name', nullable: true, withInvertIndex: true)
        ->addFloat('score', nullable: true)
        ->addDouble('rating', nullable: true)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 200);

    // Insert initial document
    $doc = new ZVecDoc('doc1');
    $doc->setInt64('id', 1)
        ->setString('name', 'Alice')
        ->setFloat('score', 95.5)
        ->setDouble('rating', 4.5)
        ->setVectorFp32('embedding', [1.0, 0.0, 0.0, 0.0]);
    $c->insert($doc);

    // Test: Update partial fields (scalar only)
    $update = new ZVecDoc('doc1');
    $update->setFloat('score', 98.0);
    $c->update($update);
    echo "Partial update OK\n";

    // Verify update preserved other fields
    $fetched = $c->fetch('doc1');
    assert($fetched[0]->getFloat('score') === 98.0, 'Score should be updated');
    assert($fetched[0]->getString('name') === 'Alice', 'Name should be preserved');
    assert($fetched[0]->getDouble('rating') === 4.5, 'Rating should be preserved');
    assert($fetched[0]->getInt64('id') === 1, 'ID should be preserved');
    echo "Fields preserved OK\n";

    // Test: Update multiple fields at once
    $update2 = new ZVecDoc('doc1');
    $update2->setString('name', 'Alice Updated')
        ->setDouble('rating', 4.8);
    $c->update($update2);

    $fetched = $c->fetch('doc1');
    assert($fetched[0]->getString('name') === 'Alice Updated', 'Name should be updated');
    assert($fetched[0]->getDouble('rating') === 4.8, 'Rating should be updated');
    assert($fetched[0]->getFloat('score') === 98.0, 'Score should still be 98.0');
    echo "Multiple fields update OK\n";

    // Test: Update non-existent document
    $nonExistent = new ZVecDoc('nonexistent');
    $nonExistent->setFloat('score', 50.0);
    try {
        $c->update($nonExistent);
        // Behavior may vary - could fail silently or succeed
        echo "Non-existent update handled\n";
    } catch (ZVecException $e) {
        echo "Non-existent document rejected\n";
    }

    // Test: Batch update
    $doc2 = new ZVecDoc('doc2');
    $doc2->setInt64('id', 2)
        ->setString('name', 'Bob')
        ->setFloat('score', 80.0)
        ->setVectorFp32('embedding', [0.0, 1.0, 0.0, 0.0]);
    $c->insert($doc2);

    $updateDoc1 = new ZVecDoc('doc1');
    $updateDoc1->setFloat('score', 100.0);
    
    $updateDoc2 = new ZVecDoc('doc2');
    $updateDoc2->setFloat('score', 85.0);
    
    $c->update($updateDoc1, $updateDoc2);
    echo "Batch update OK\n";

    // Verify batch update
    $fetched = $c->fetch('doc1', 'doc2');
    foreach ($fetched as $doc) {
        if ($doc->getPk() === 'doc1') {
            assert($doc->getFloat('score') === 100.0, 'doc1 score should be 100.0');
        } else if ($doc->getPk() === 'doc2') {
            assert($doc->getFloat('score') === 85.0, 'doc2 score should be 85.0');
        }
    }
    echo "Verify batch update OK\n";

    $c->close();
    echo "PASS: Update operations work\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Partial update OK
Fields preserved OK
Multiple fields update OK
Non-existent document rejected
Batch update OK
Verify batch update OK
PASS: Update operations work
