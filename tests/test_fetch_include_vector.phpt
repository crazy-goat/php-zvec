--TEST--
Data operations: fetch includeVector parameter and nullable field normalization
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/fetch_include_vector_' . uniqid();
try {
    $schema = new ZVecSchema('fetch_include_vector_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('name', nullable: false)
        ->addInt64('optional_value', nullable: true)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createIndex('embedding', ZVecIndexParams::forHnsw(ZVecSchema::METRIC_IP));

    $doc1 = new ZVecDoc('doc1');
    $doc1->setInt64('id', 1)
        ->setString('name', 'User1')
        ->setInt64('optional_value', 42)
        ->setVectorFp32('embedding', [1.0, 0.0, 0.0, 0.0]);
    $c->insert($doc1);

    $doc2 = new ZVecDoc('doc2');
    $doc2->setInt64('id', 2)
        ->setString('name', 'User2')
        ->setVectorFp32('embedding', [0.0, 1.0, 0.0, 0.0]);
    $c->insert($doc2);

    // BC default: vector present, scalars intact
    $fetched = $c->fetch('doc1');
    assert(count($fetched) === 1, 'Should fetch 1 document');
    assert($fetched[0]->getVectorFp32('embedding') !== null, 'vector should be present by default');
    assert($fetched[0]->getInt64('id') === 1, 'id should be present');
    assert($fetched[0]->getString('name') === 'User1', 'name should be present');
    echo "BC default includeVector OK\n";

    // includeVector: false omits the vector, scalars intact
    $fetched = $c->fetch('doc1', includeVector: false);
    assert(count($fetched) === 1, 'Should fetch 1 document');
    assert($fetched[0]->getVectorFp32('embedding') === null, 'vector should be omitted');
    assert($fetched[0]->getInt64('id') === 1, 'id should be present');
    assert($fetched[0]->getString('name') === 'User1', 'name should be present');
    echo "includeVector false OK\n";

    // includeVector: false combined with outputFields subset
    $fetched = $c->fetch(['doc1'], ['id', 'name'], includeVector: false);
    assert(count($fetched) === 1, 'Should fetch 1 document');
    assert($fetched[0]->getInt64('id') === 1, 'requested id should be present');
    assert($fetched[0]->getString('name') === 'User1', 'requested name should be present');
    assert($fetched[0]->getVectorFp32('embedding') === null, 'vector should be omitted');
    echo "includeVector false with outputFields OK\n";

    // explicit includeVector: true
    $fetched = $c->fetch('doc2', includeVector: true);
    assert(count($fetched) === 1, 'Should fetch 1 document');
    assert($fetched[0]->getVectorFp32('embedding') !== null, 'vector should be present');
    echo "explicit includeVector true OK\n";

    // nullable normalization: doc2 was inserted without optional_value
    $fetched = $c->fetch('doc2');
    assert(count($fetched) === 1, 'Should fetch 1 document');
    assert($fetched[0]->hasField('optional_value') === true, 'nullable field should be present after fetch');
    assert($fetched[0]->isFieldNull('optional_value') === true, 'nullable field should be null');
    assert($fetched[0]->getInt64('optional_value') === null, 'nullable field getter should return null');
    assert(in_array('optional_value', $fetched[0]->fieldNames(), true), 'nullable field should appear in field names');
    echo "nullable normalization OK\n";

    // nullable field present: real value, not null
    $fetched = $c->fetch('doc1');
    assert($fetched[0]->hasField('optional_value') === true, 'nullable field should be present');
    assert($fetched[0]->isFieldNull('optional_value') === false, 'stored value should not be null');
    assert($fetched[0]->getInt64('optional_value') === 42, 'stored value should be returned');
    echo "nullable present value OK\n";

    // non-nullable field excluded via outputFields stays absent
    $fetched = $c->fetch(['doc1'], ['name']);
    assert(count($fetched) === 1, 'Should fetch 1 document');
    assert($fetched[0]->hasField('name') === true, 'requested name should be present');
    assert($fetched[0]->hasField('id') === false, 'non-nullable unrequested field should stay absent');
    assert($fetched[0]->hasField('optional_value') === true, 'nullable unrequested field is normalized to null');
    assert($fetched[0]->isFieldNull('optional_value') === true, 'normalized nullable field should be null');
    assert($fetched[0]->getVectorFp32('embedding') !== null, 'vector present by default');
    echo "non-nullable stays absent OK\n";

    // nullable normalization combined with includeVector: false
    $fetched = $c->fetch(['doc2'], ['optional_value'], includeVector: false);
    assert(count($fetched) === 1, 'Should fetch 1 document');
    assert($fetched[0]->getVectorFp32('embedding') === null, 'vector should be omitted');
    assert($fetched[0]->hasField('optional_value') === true, 'nullable field should be present after fetch');
    assert($fetched[0]->isFieldNull('optional_value') === true, 'nullable field should be null');
    echo "nullable with includeVector false OK\n";

    // array form with includeVector: false
    $fetched = $c->fetch(['doc1', 'doc2'], ['name'], includeVector: false);
    assert(count($fetched) === 2, 'Should fetch 2 documents');
    foreach ($fetched as $d) {
        assert($d->getString('name') === 'User' . substr($d->getPk(), 3), 'name should be present');
        assert($d->getVectorFp32('embedding') === null, 'vector should be omitted');
        assert($d->hasField('optional_value') === true, 'nullable field normalized for both docs');
    }
    echo "array form includeVector false OK\n";

    // missing PK is omitted from the result
    $fetched = $c->fetch('doc1', 'missing_pk');
    assert(count($fetched) === 1, 'Only existing document should be returned');
    assert($fetched[0]->getPk() === 'doc1', 'Existing document should be returned');
    echo "missing PK omitted OK\n";

    // positional includeVector is rejected: only the named argument is supported
    try {
        $c->fetch('doc1', 'doc2', false);
        assert(false, 'Should throw ZVecException for positional bool argument');
    } catch (ZVecException $e) {
        echo "positional includeVector scalar form throws OK\n";
    }
    try {
        $c->fetch(['doc1'], ['name'], false);
        assert(false, 'Should throw ZVecException for positional bool argument');
    } catch (ZVecException $e) {
        echo "positional includeVector array form throws OK\n";
    }

    $c->close();
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
BC default includeVector OK
includeVector false OK
includeVector false with outputFields OK
explicit includeVector true OK
nullable normalization OK
nullable present value OK
non-nullable stays absent OK
nullable with includeVector false OK
array form includeVector false OK
missing PK omitted OK
positional includeVector scalar form throws OK
positional includeVector array form throws OK
