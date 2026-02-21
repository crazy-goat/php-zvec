--TEST--
Data operations: insert single and batch documents
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/insert_' . uniqid();

try {
    $schema = new ZVecSchema('insert_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('name', nullable: true, withInvertIndex: true)
        ->addFloat('score', nullable: true)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 200);

    // Test: Insert single document
    $doc1 = new ZVecDoc('doc1');
    $doc1->setInt64('id', 1)
        ->setString('name', 'Alice')
        ->setFloat('score', 95.5)
        ->setVectorFp32('embedding', [1.0, 0.0, 0.0, 0.0]);
    $c->insert($doc1);
    echo "Single insert OK\n";

    // Test: Insert multiple documents (batch)
    $doc2 = new ZVecDoc('doc2');
    $doc2->setInt64('id', 2)
        ->setString('name', 'Bob')
        ->setFloat('score', 87.0)
        ->setVectorFp32('embedding', [0.0, 1.0, 0.0, 0.0]);
    
    $doc3 = new ZVecDoc('doc3');
    $doc3->setInt64('id', 3)
        ->setString('name', 'Charlie')
        ->setFloat('score', 92.0)
        ->setVectorFp32('embedding', [0.0, 0.0, 1.0, 0.0]);
    
    $c->insert($doc2, $doc3);
    echo "Batch insert OK\n";

    // Test: Verify documents were inserted by fetching
    $fetched = $c->fetch('doc1', 'doc2', 'doc3');
    assert(count($fetched) === 3, 'Should fetch 3 documents');
    $fetchedPks = [];
    foreach ($fetched as $doc) {
        $fetchedPks[$doc->getPk()] = $doc;
    }
    assert(isset($fetchedPks['doc1']), 'doc1 should be present');
    assert($fetchedPks['doc1']->getInt64('id') === 1, 'doc1 id should be 1');
    assert(isset($fetchedPks['doc2']), 'doc2 should be present');
    assert($fetchedPks['doc2']->getString('name') === 'Bob', 'doc2 name should be Bob');
    assert(isset($fetchedPks['doc3']), 'doc3 should be present');
    echo "Fetch verification OK\n";

    // Test: Insert duplicate (should fail with exception)
    $dupDoc = new ZVecDoc('doc1');
    $dupDoc->setInt64('id', 99)
        ->setString('name', 'Duplicate')
        ->setVectorFp32('embedding', [0.0, 0.0, 0.0, 1.0]);
    try {
        $c->insert($dupDoc);
        echo "FAIL: Duplicate insert should throw exception\n";
        exit(1);
    } catch (ZVecException $e) {
        echo "Duplicate insert correctly rejected\n";
    }

    // Test: Insert with missing required field (nullable=false)
    $badDoc = new ZVecDoc('doc4');
    $badDoc->setString('name', 'Missing ID')
        ->setVectorFp32('embedding', [1.0, 1.0, 1.0, 1.0]);
    // Missing 'id' field which is not nullable
    try {
        $c->insert($badDoc);
        // Some implementations may allow this with default values
        echo "Missing required field handled\n";
    } catch (ZVecException $e) {
        echo "Missing required field correctly rejected\n";
    }

    $c->close();
    echo "PASS: Insert operations work\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECTF--
Single insert OK
Batch insert OK
Fetch verification OK
Duplicate insert correctly rejected
Missing required field correctly rejected
PASS: Insert operations work
