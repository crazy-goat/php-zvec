--TEST--
Data operations: fetch with outputFields parameter
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
set_error_handler(static fn(int $errno, string $message): bool => $errno === E_USER_DEPRECATED && str_contains($message, ' is deprecated, use createIndex()'));
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/fetch_output_fields_' . uniqid();
try {
    $schema = new ZVecSchema('fetch_output_fields_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('name', nullable: true, withInvertIndex: true)
        ->addFloat('score', nullable: true)
        ->addDouble('rating', nullable: true)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 200);

    for ($i = 1; $i <= 3; $i++) {
        $doc = new ZVecDoc("doc$i");
        $doc->setInt64('id', $i)
            ->setString('name', "User$i")
            ->setFloat('score', 80.0 + $i * 2)
            ->setDouble('rating', 3.0 + $i * 0.5)
            ->setVectorFp32('embedding', [1.0 * $i, 0.0, 0.0, 0.0]);
        $c->insert($doc);
    }
    echo "Inserted 3 documents\n";

    // outputFields subset: only requested scalar fields returned (vector
    // included by default, pass includeVector: false to omit it)
    $fetched = $c->fetch(['doc1', 'doc2'], ['name']);
    assert(count($fetched) === 2, 'Should fetch 2 documents');
    foreach ($fetched as $d) {
        assert($d->getString('name') === 'User' . substr($d->getPk(), 3), 'name should be present');
        assert($d->getInt64('id') === null, 'id should be excluded');
        assert($d->getFloat('score') === null, 'score should be excluded');
        assert($d->getDouble('rating') === null, 'rating should be excluded');
        assert($d->getVectorFp32('embedding') !== null, 'vector should always be present (include_vector)');
    }
    echo "outputFields subset OK\n";

    // fetch without outputFields still returns all fields (BC)
    $fetched = $c->fetch('doc1');
    assert(count($fetched) === 1, 'Should fetch 1 document');
    assert($fetched[0]->getInt64('id') === 1, 'id should be present');
    assert($fetched[0]->getString('name') === 'User1', 'name should be present');
    assert($fetched[0]->getFloat('score') === 82.0, 'score should be present');
    assert($fetched[0]->getDouble('rating') === 3.5, 'rating should be present');
    assert($fetched[0]->getVectorFp32('embedding') !== null, 'vector should be present');
    echo "Fetch all fields BC OK\n";

    // array form fetch(['a', 'b'], ['field'])
    $fetched = $c->fetch(['doc1', 'doc3'], ['score']);
    assert(count($fetched) === 2, 'Should fetch 2 documents');
    foreach ($fetched as $d) {
        assert($d->getFloat('score') === 80.0 + substr($d->getPk(), 3) * 2, 'score should be present');
        assert($d->getString('name') === null, 'name should be excluded');
    }
    echo "Array form with outputFields OK\n";

    // unknown output field names are silently ignored (only existing fields returned)
    $fetched = $c->fetch(['doc1'], ['nonexistent_field', 'also_missing']);
    assert(count($fetched) === 1, 'Should fetch 1 document');
    assert($fetched[0]->getString('name') === null, 'unrequested field should be null');
    assert($fetched[0]->getInt64('id') === null, 'unrequested field should be null');
    echo "Unknown output fields ignored OK\n";

    // invalid outputFields (non-string entries) -> ZVecException
    try {
        $c->fetch(['doc1'], ['name', 123]);
        assert(false, 'Should throw ZVecException for non-string output field');
    } catch (ZVecException $e) {
        echo "Invalid outputFields type throws OK\n";
    }

    // invalid outputFields (empty string entry) -> ZVecException
    try {
        $c->fetch(['doc1'], ['']);
        assert(false, 'Should throw ZVecException for empty output field');
    } catch (ZVecException $e) {
        echo "Invalid outputFields empty string throws OK\n";
    }

    // invalid PK entry -> ZVecException
    try {
        $c->fetch(['doc1', 42]);
        assert(false, 'Should throw ZVecException for non-string PK');
    } catch (ZVecException $e) {
        echo "Invalid PK throws OK\n";
    }

    // extra arguments after outputFields -> ZVecException
    try {
        $c->fetch(['doc1'], ['name'], 'extra');
        assert(false, 'Should throw ZVecException for extra arguments');
    } catch (ZVecException $e) {
        echo "Extra arguments throw OK\n";
    }

    $c->close();
    echo "PASS: fetch with outputFields works\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Inserted 3 documents
outputFields subset OK
Fetch all fields BC OK
Array form with outputFields OK
Unknown output fields ignored OK
Invalid outputFields type throws OK
Invalid outputFields empty string throws OK
Invalid PK throws OK
Extra arguments throw OK
PASS: fetch with outputFields works
