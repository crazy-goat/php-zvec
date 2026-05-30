--TEST--
ZVecQueryInterface: composition over inheritance for query types
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
require_once __DIR__ . '/../src/ZVecRrfReRanker.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/query_interface_' . uniqid();
try {
    // Test 1: Interface exists and both classes implement it
    assert(interface_exists('ZVecQueryInterface'), 'ZVecQueryInterface must exist');
    assert(is_subclass_of('ZVecVectorQuery', 'ZVecQueryInterface'), 'ZVecVectorQuery must implement ZVecQueryInterface');
    assert(is_subclass_of('ZVecGroupByVectorQuery', 'ZVecQueryInterface'), 'ZVecGroupByVectorQuery must implement ZVecQueryInterface');
    echo "1. Interface exists and both classes implement it\n";

    // Test 2: ZVecGroupByVectorQuery does NOT extend ZVecVectorQuery
    assert(!is_subclass_of('ZVecGroupByVectorQuery', 'ZVecVectorQuery'), 'ZVecGroupByVectorQuery must NOT extend ZVecVectorQuery');
    echo "2. ZVecGroupByVectorQuery does not extend ZVecVectorQuery\n";

    // Test 3: getHandle() returns FFI\CData for both
    $vq = new ZVecVectorQuery('field', [1.0, 0.0]);
    assert($vq->getHandle() instanceof FFI\CData, 'ZVecVectorQuery::getHandle() must return FFI\CData');
    echo "3. ZVecVectorQuery::getHandle() returns FFI\\CData\n";

    $gbq = new ZVecGroupByVectorQuery('field', [1.0, 0.0], 'group');
    assert($gbq->getHandle() instanceof FFI\CData, 'ZVecGroupByVectorQuery::getHandle() must return FFI\CData');
    echo "4. ZVecGroupByVectorQuery::getHandle() returns FFI\\CData\n";

    // Test 4: free() is idempotent (can be called multiple times)
    $vq2 = new ZVecVectorQuery('field', [1.0, 0.0]);
    $vq2->free();
    $vq2->free(); // second call should be no-op
    echo "5. free() is idempotent on ZVecVectorQuery\n";

    $gbq2 = new ZVecGroupByVectorQuery('field', [1.0, 0.0], 'group');
    $gbq2->free();
    $gbq2->free(); // second call should be no-op
    echo "6. free() is idempotent on ZVecGroupByVectorQuery\n";

    // Test 5: Functional test - create collection and run queries
    $schema = new ZVecSchema('iface_test');
    $schema->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP)
           ->addString('category', nullable: false, withInvertIndex: true)
           ->addString('title', nullable: false, withInvertIndex: true);

    $coll = ZVec::create($path, $schema);

    $docs = [
        (new ZVecDoc('d1'))->setVectorFp32('vec', [1.0, 0.0, 0.0, 0.0])->setString('category', 'A')->setString('title', 'Alpha'),
        (new ZVecDoc('d2'))->setVectorFp32('vec', [0.0, 1.0, 0.0, 0.0])->setString('category', 'B')->setString('title', 'Beta'),
        (new ZVecDoc('d3'))->setVectorFp32('vec', [1.0, 0.0, 0.0, 0.0])->setString('category', 'A')->setString('title', 'Alpha2'),
    ];
    $coll->insert(...$docs);
    $coll->optimize();

    // Test 6: queryVector() with ZVecVectorQuery
    $vq3 = new ZVecVectorQuery('vec', [1.0, 0.0, 0.0, 0.0]);
    $vq3->setTopk(3);
    $results = $coll->queryVector($vq3);
    assert(count($results) >= 1, 'queryVector must return results');
    echo "7. queryVector() works with ZVecVectorQuery\n";

    // Test 7: groupByVectorQuery() with ZVecGroupByVectorQuery
    $gbq3 = new ZVecGroupByVectorQuery('vec', [1.0, 0.0, 0.0, 0.0], 'category', groupCount: 2, groupTopk: 2);
    $groups = $coll->groupByVectorQuery($gbq3);
    assert(count($groups) >= 1, 'groupByVectorQuery must return groups');
    echo "8. groupByVectorQuery() works with ZVecGroupByVectorQuery\n";

    // Test 8: ZVecVectorQuery via backward-compatible query()
    $vq4 = new ZVecVectorQuery('vec', [1.0, 0.0, 0.0, 0.0]);
    $results2 = $coll->query($vq4, [], 3);
    assert(count($results2) >= 1, 'query() with ZVecVectorQuery must return results');
    echo "9. query() backward-compatible with ZVecVectorQuery\n";

    // Test 9: queryMulti() with ZVecVectorQuery instances
    $vq5 = new ZVecVectorQuery('vec', [1.0, 0.0, 0.0, 0.0]);
    $reranker = new ZVecRrfReRanker(topn: 3, rankConstant: 60);
    $multiResults = $coll->queryMulti([$vq5], $reranker, topk: 3);
    assert(count($multiResults) >= 1, 'queryMulti must return results');
    echo "10. queryMulti() works with ZVecVectorQuery\n";

    // Test 10: Both types share no common base class besides ZVecQueryInterface
    $reflectionVQ = new ReflectionClass('ZVecVectorQuery');
    $reflectionGBQ = new ReflectionClass('ZVecGroupByVectorQuery');
    assert($reflectionVQ->getInterfaceNames() === $reflectionGBQ->getInterfaceNames(), 'Both classes must implement the same interfaces');
    echo "11. Both classes implement identical interface set\n";

    $coll->close();
    echo "\nAll tests passed!\n";

} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
1. Interface exists and both classes implement it
2. ZVecGroupByVectorQuery does not extend ZVecVectorQuery
3. ZVecVectorQuery::getHandle() returns FFI\CData
4. ZVecGroupByVectorQuery::getHandle() returns FFI\CData
5. free() is idempotent on ZVecVectorQuery
6. free() is idempotent on ZVecGroupByVectorQuery
7. queryVector() works with ZVecVectorQuery
8. groupByVectorQuery() works with ZVecGroupByVectorQuery
9. query() backward-compatible with ZVecVectorQuery
10. queryMulti() works with ZVecVectorQuery
11. Both classes implement identical interface set

All tests passed!
