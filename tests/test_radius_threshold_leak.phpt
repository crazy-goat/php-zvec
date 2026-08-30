--TEST--
Radius threshold leak: radius query poisons subsequent refiner/plain queries, resetRadiusThreshold() restores them (#200)
--SKIPIF--
<?php
if (extension_loaded('zvec')) die('skip Native zvec extension loaded (use FFI)');
if (!extension_loaded('ffi')) die('skip FFI extension not available');
?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/radius_leak_' . uniqid();

/** @return string */
function pks(array $results): string
{
    return implode(',', array_map(fn($d) => $d->getPk(), $results));
}

try {
    $schema = new ZVecSchema('radius_leak');
    $schema->addVectorFp32('vf', dimension: 4, metricType: ZVecSchema::METRIC_L2);

    $c = ZVec::create($path, $schema);
    $c->createIndex('vf', ZVecIndexParams::forFlat(ZVecSchema::METRIC_L2));

    // Squared L2 distances from query [1,0,0,0]: doc1=0, doc2=0.25, doc3=4
    $vecs = [
        'doc1' => [1.0, 0.0, 0.0, 0.0],
        'doc2' => [1.5, 0.0, 0.0, 0.0],
        'doc3' => [3.0, 0.0, 0.0, 0.0],
    ];
    $docs = [];
    foreach ($vecs as $pk => $v) {
        $docs[] = (new ZVecDoc($pk))->setVectorFp32('vf', $v);
    }
    $c->insert(...$docs);
    $c->flush();
    $c->optimize();

    $qv = [1.0, 0.0, 0.0, 0.0];

    // Sanity: radius query filters as expected
    $q = (new ZVecVectorQuery('vf', $qv))->setTopk(10)->setFlatParams()->setRadius(0.3);
    echo 'radius 0.3:            ', pks($c->queryVector($q)), "\n";

    // The leak (#200): after a radius query, refiner and plain queries on the
    // same thread return only the radius-filtered subset.
    $q = (new ZVecVectorQuery('vf', $qv))->setTopk(10)->setFlatParams()->setUsingRefiner(true);
    echo 'refiner after radius:  ', pks($c->queryVector($q)), "\n";

    $q = (new ZVecVectorQuery('vf', $qv))->setTopk(10)->setFlatParams();
    echo 'plain after radius:    ', pks($c->queryVector($q)), "\n";

    // resetRadiusThreshold() purges the stale threshold
    $c->resetRadiusThreshold('vf', $qv);

    $q = (new ZVecVectorQuery('vf', $qv))->setTopk(10)->setFlatParams()->setUsingRefiner(true);
    echo 'refiner after reset:   ', pks($c->queryVector($q)), "\n";

    $q = (new ZVecVectorQuery('vf', $qv))->setTopk(10)->setFlatParams();
    echo 'plain after reset:     ', pks($c->queryVector($q)), "\n";

    // Radius filtering still works after the reset
    $q = (new ZVecVectorQuery('vf', $qv))->setTopk(10)->setFlatParams()->setRadius(0.3);
    echo 'radius after reset:    ', pks($c->queryVector($q)), "\n";

    // ... and the reset clears a fresh leak again
    $q = (new ZVecVectorQuery('vf', $qv))->setTopk(10)->setFlatParams();
    echo 'plain after 2nd leak:  ', pks($c->queryVector($q)), "\n";

    $c->resetRadiusThreshold('vf', $qv);
    $q = (new ZVecVectorQuery('vf', $qv))->setTopk(10)->setFlatParams();
    echo 'plain after 2nd reset: ', pks($c->queryVector($q)), "\n";

    echo "ALL TESTS PASSED\n";
} finally {
    if (isset($c)) { try { $c->destroy(); } catch (Exception $e) {} }
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
radius 0.3:            doc1,doc2
refiner after radius:  doc1,doc2
plain after radius:    doc1,doc2
refiner after reset:   doc1,doc2,doc3
plain after reset:     doc1,doc2,doc3
radius after reset:    doc1,doc2
plain after 2nd leak:  doc1,doc2
plain after 2nd reset: doc1,doc2,doc3
ALL TESTS PASSED
