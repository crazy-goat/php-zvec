--TEST--
Data operations: fetch documents by primary key
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/fetch_' . uniqid();

try {
    $schema = new ZVecSchema('fetch_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('name', nullable: true, withInvertIndex: true)
        ->addFloat('score', nullable: true)
        ->addDouble('rating', nullable: true)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 200);

    // Insert test documents
    for ($i = 1; $i <= 5; $i++) {
        $doc = new ZVecDoc("doc$i");
        $doc->setInt64('id', $i)
            ->setString('name', "User$i")
            ->setFloat('score', 80.0 + $i * 2)
            ->setDouble('rating', 3.0 + $i * 0.5)
            ->setVectorFp32('embedding', [1.0 * $i, 0.0, 0.0, 0.0]);
        $c->insert($doc);
    }
    echo "Inserted 5 documents\n";

    // Test: Fetch single document by ID
    $fetched = $c->fetch('doc1');
    assert(count($fetched) === 1, 'Should fetch 1 document');
    assert($fetched[0]->getPk() === 'doc1', 'PK should be doc1');
    assert($fetched[0]->getInt64('id') === 1, 'id should be 1');
    assert($fetched[0]->getString('name') === 'User1', 'name should be User1');
    assert($fetched[0]->getFloat('score') === 82.0, 'score should be 82.0');
    assert($fetched[0]->getDouble('rating') === 3.5, 'rating should be 3.5');
    echo "Fetch single OK\n";

    // Test: Fetch multiple documents by IDs
    $fetched = $c->fetch('doc1', 'doc3', 'doc5');
    assert(count($fetched) === 3, 'Should fetch 3 documents');
    $pks = array_map(fn($d) => $d->getPk(), $fetched);
    assert(in_array('doc1', $pks), 'Should have doc1');
    assert(in_array('doc3', $pks), 'Should have doc3');
    assert(in_array('doc5', $pks), 'Should have doc5');
    echo "Fetch multiple OK\n";

    // Test: Fetch non-existent ID (should return empty or null)
    $fetched = $c->fetch('nonexistent');
    assert(count($fetched) === 0, 'Should return empty for non-existent');
    echo "Fetch non-existent OK\n";

    // Test: Fetch mix of existing and non-existent
    $fetched = $c->fetch('doc1', 'nonexistent', 'doc5');
    assert(count($fetched) === 2, 'Should fetch 2 existing documents');
    $pks = array_map(fn($d) => $d->getPk(), $fetched);
    assert(in_array('doc1', $pks), 'Should have doc1');
    assert(in_array('doc5', $pks), 'Should have doc5');
    assert(!in_array('nonexistent', $pks), 'Should not have nonexistent');
    echo "Fetch mixed OK\n";

    // Test: Verify vector field is accessible
    $fetched = $c->fetch('doc2');
    $vector = $fetched[0]->getVectorFp32('embedding');
    assert($vector !== null, 'Vector should not be null');
    assert(count($vector) === 4, 'Vector dimension should be 4');
    assert(abs($vector[0] - 2.0) < 0.01, 'First component should be 2.0');
    echo "Fetch with vector OK\n";

    // Test: Fetch with missing/invalid fields returns null
    $fetched = $c->fetch('doc1');
    assert($fetched[0]->getInt64('nonexistent_field') === null, 'Invalid field should return null');
    assert($fetched[0]->getString('nonexistent_field') === null, 'Invalid field should return null');
    echo "Invalid fields handled OK\n";

    // Test: Verify field preservation after operations
    $fetched = $c->fetch('doc4');
    assert($fetched[0]->getString('name') === 'User4', 'name preserved');
    assert($fetched[0]->getFloat('score') === 88.0, 'score preserved');
    assert($fetched[0]->getDouble('rating') === 5.0, 'rating preserved');
    echo "Field preservation OK\n";

    $c->close();
    echo "PASS: Fetch operations work\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Inserted 5 documents
Fetch single OK
Fetch multiple OK
Fetch non-existent OK
Fetch mixed OK
Fetch with vector OK
Invalid fields handled OK
Field preservation OK
PASS: Fetch operations work
