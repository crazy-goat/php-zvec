--TEST--
Query params order: setRadius/setLinear/setUsingRefiner before set*Params() are honored (#197)
--SKIPIF--
<?php
if (extension_loaded('zvec')) die('skip Native zvec extension loaded (use FFI)');
if (!extension_loaded('ffi')) die('skip FFI extension not available');
?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/qparams_order_' . uniqid();

/** @return string[] */
function pks(array $results): array
{
    return array_map(fn($d) => $d->getPk(), $results);
}

try {
    $schema = new ZVecSchema('test');
    $schema->addVectorFp32('vf', dimension: 4, metricType: ZVecSchema::METRIC_L2);
    $schema->addVectorFp32('vh', dimension: 4, metricType: ZVecSchema::METRIC_L2);

    $c = ZVec::create($path, $schema);
    $c->createIndex('vf', ZVecIndexParams::forFlat(ZVecSchema::METRIC_L2));
    $c->createIndex('vh', ZVecIndexParams::forHnsw(ZVecSchema::METRIC_L2));

    // Squared L2 distances from query [1,0,0,0]: doc1=0, doc2=0.25, doc3=4
    $vecs = [
        'doc1' => [1.0, 0.0, 0.0, 0.0],
        'doc2' => [1.5, 0.0, 0.0, 0.0],
        'doc3' => [3.0, 0.0, 0.0, 0.0],
    ];
    $docs = [];
    foreach ($vecs as $pk => $v) {
        $docs[] = (new ZVecDoc($pk))->setVectorFp32('vf', $v)->setVectorFp32('vh', $v);
    }
    $c->insert(...$docs);
    $c->flush();
    $c->optimize();
    echo "Inserted 3 docs\n";

    $qv = [1.0, 0.0, 0.0, 0.0];

    // setUsingRefiner before/after params (flat index acts as its own reference).
    // Must run before any radius query: a radius-filtered query leaks its
    // threshold into subsequent refiner queries (upstream zvec db-layer issue,
    // unrelated to #197).
    $q = (new ZVecVectorQuery('vf', $qv))->setTopk(10)->setUsingRefiner(true)->setFlatParams();
    echo 'flat refiner-before-params: ', implode(',', pks($c->queryVector($q))), "\n";

    $q = (new ZVecVectorQuery('vf', $qv))->setTopk(10)->setFlatParams()->setUsingRefiner(true);
    echo 'flat refiner-after-params: ', implode(',', pks($c->queryVector($q))), "\n";

    // Baseline (params first, then radius) vs bug order (radius first, then params)
    $q = (new ZVecVectorQuery('vf', $qv))->setTopk(10)->setFlatParams()->setRadius(0.3);
    echo 'flat params-then-radius: ', implode(',', pks($c->queryVector($q))), "\n";

    $q = (new ZVecVectorQuery('vf', $qv))->setTopk(10)->setRadius(0.3)->setFlatParams();
    echo 'flat radius-then-params: ', implode(',', pks($c->queryVector($q))), "\n";

    $q = (new ZVecVectorQuery('vh', $qv))->setTopk(10)->setHnswParams(200)->setRadius(0.3);
    echo 'hnsw params-then-radius: ', implode(',', pks($c->queryVector($q))), "\n";

    $q = (new ZVecVectorQuery('vh', $qv))->setTopk(10)->setRadius(0.3)->setHnswParams(200);
    echo 'hnsw radius-then-params: ', implode(',', pks($c->queryVector($q))), "\n";

    // Wide radius before params keeps all docs (doc3 dist^2 = 4 <= 5)
    $q = (new ZVecVectorQuery('vh', $qv))->setTopk(10)->setRadius(5.0)->setHnswParams(200);
    echo 'hnsw wide-radius-before-params: ', implode(',', pks($c->queryVector($q))), "\n";

    // setLinear before params
    $q = (new ZVecVectorQuery('vh', $qv))->setTopk(10)->setLinear(true)->setHnswParams(200);
    echo 'hnsw linear-before-params: ', implode(',', pks($c->queryVector($q))), "\n";

    // setRadius + setLinear before params
    $q = (new ZVecVectorQuery('vh', $qv))->setTopk(10)->setRadius(0.3)->setLinear(true)->setHnswParams(200);
    echo 'hnsw radius+linear-before-params: ', implode(',', pks($c->queryVector($q))), "\n";

    // Params-first path still works with no radius set (unchanged behavior)
    $q = (new ZVecVectorQuery('vh', $qv))->setTopk(10)->setHnswParams(200);
    echo 'hnsw no-radius: ', implode(',', pks($c->queryVector($q))), "\n";

    echo "ALL TESTS PASSED\n";
} finally {
    if (isset($c)) { try { $c->destroy(); } catch (Exception $e) {} }
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Inserted 3 docs
flat refiner-before-params: doc1,doc2,doc3
flat refiner-after-params: doc1,doc2,doc3
flat params-then-radius: doc1,doc2
flat radius-then-params: doc1,doc2
hnsw params-then-radius: doc1,doc2
hnsw radius-then-params: doc1,doc2
hnsw wide-radius-before-params: doc1,doc2,doc3
hnsw linear-before-params: doc1,doc2,doc3
hnsw radius+linear-before-params: doc1,doc2
hnsw no-radius: doc1,doc2,doc3
ALL TESTS PASSED
