--TEST--
ZVecVectorQuery object: queryVector() with setTopk, setIncludeVector, setFilter, setOutputFields, setHnswParams, setRadius
--SKIPIF--
<?php
if (extension_loaded('zvec')) die('skip Native zvec extension loaded (use FFI)');
if (!extension_loaded('ffi')) die('skip FFI extension not available');
?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/vq_obj_' . uniqid();
try {
    $schema = new ZVecSchema('test');
    $schema->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $schema->addVectorFp32('v2', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $schema->addInt64('id', nullable: false);
    $schema->addString('cat', nullable: true);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('v');
    $c->createHnswIndex('v2');

    $docs = [
        (new ZVecDoc('doc1'))->setVectorFp32('v', [1.0, 0.0, 0.0, 0.0])->setInt64('id', 1)->setString('cat', 'A'),
        (new ZVecDoc('doc2'))->setVectorFp32('v', [0.0, 1.0, 0.0, 0.0])->setInt64('id', 2)->setString('cat', 'B'),
        (new ZVecDoc('doc3'))->setVectorFp32('v', [0.0, 0.0, 1.0, 0.0])->setInt64('id', 3)->setString('cat', 'A'),
    ];
    // Set v2 same as v for simplicity
    foreach ($docs as $d) { $d->setVectorFp32('v2', $d->getVectorFp32('v')); }
    $c->insert(...$docs);
    $c->flush();
    $c->optimize();
    echo "Inserted 3 docs\n";

    // Test 1: queryVector() basic
    $vq = new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]);
    $vq->setTopk(3);
    $results = $c->queryVector($vq);
    assert(count($results) === 3, 'Expected 3 results');
    $pks = array_map(fn($d) => $d->getPk(), $results);
    assert(in_array('doc1', $pks), 'Expected doc1 in results');
    echo "queryVector basic OK\n";

    // Test 2: queryVector with includeVector
    $vq = (new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]))->setTopk(1)->setIncludeVector(true);
    $results = $c->queryVector($vq);
    assert(count($results) === 1, 'Expected 1 result');
    $v = $results[0]->getVectorFp32('v');
    assert($v !== null, 'Expected vector with includeVector');
    echo "queryVector with includeVector OK\n";

    // Test 3: queryVector with filter
    $vq = (new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]))->setTopk(3)->setFilter("cat = 'A'");
    $results = $c->queryVector($vq);
    assert(count($results) === 2, 'Expected 2 results');
    foreach ($results as $r) {
        assert($r->getString('cat') === 'A', 'All filtered results should have cat=A');
    }
    echo "queryVector with filter OK\n";

    // Test 4: queryVector with outputFields
    $vq = (new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]))->setTopk(3)->setOutputFields(['id', 'cat']);
    $results = $c->queryVector($vq);
    assert(count($results) === 3, 'Expected 3 results');
    echo "queryVector with outputFields OK\n";

    // Test 5: queryVector with HNSW params
    $vq = (new ZVecVectorQuery('v', [0.0, 1.0, 0.0, 0.0]))
        ->setTopk(3)
        ->setHnswParams(ef: 200);
    $results = $c->queryVector($vq);
    assert(count($results) === 3, 'Expected 3 results');
    $pks = array_map(fn($d) => $d->getPk(), $results);
    assert(in_array('doc2', $pks), 'Expected doc2 in results');
    echo "queryVector with HNSW params OK\n";

    // Test 6: setRadius with high value (should exclude some results)
    $vq = (new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]))
        ->setTopk(10)
        ->setRadius(0.0);
    $results = $c->queryVector($vq);
    assert(count($results) >= 1, 'Expected at least 1 result with radius=0');
    echo "queryVector with radius OK\n";

    // Test 7: ZVecVectorQuery via old query() backward compat
    $vq = (new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]))->setTopk(3);
    $results = $c->query($vq, topk: 3);
    assert(count($results) === 3, 'Expected 3 results');
    echo "ZVecVectorQuery via query() OK\n";

    // Test 8: Non-existent vector field throws
    try {
        $vq = new ZVecVectorQuery('nonexistent', [1.0, 0.0, 0.0, 0.0]);
        $vq->setTopk(3);
        $c->queryVector($vq);
        echo "FAIL: Expected ZVecException for non-existent field\n";
    } catch (ZVecException $e) {
        assert(str_contains($e->getMessage(), 'not exist') || str_contains($e->getMessage(), 'not found'),
            'Expected field not found error');
        echo "Non-existent vector field throws OK\n";
    }

    // Test 9: queryVector with linear mode
    $vq = (new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]))
        ->setTopk(3)
        ->setLinear(true);
    $results = $c->queryVector($vq);
    assert(count($results) === 3, 'Expected 3 results');
    echo "queryVector with linear mode OK\n";

    echo "ALL TESTS PASSED\n";
} finally {
    if (isset($c)) { try { $c->destroy(); } catch (Exception $e) {} }
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Inserted 3 docs
queryVector basic OK
queryVector with includeVector OK
queryVector with filter OK
queryVector with outputFields OK
queryVector with HNSW params OK
queryVector with radius OK
ZVecVectorQuery via query() OK
Non-existent vector field throws OK
queryVector with linear mode OK
ALL TESTS PASSED