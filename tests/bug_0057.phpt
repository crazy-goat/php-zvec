--TEST--
Bug 0057: queryVector() with radius/linear/refiner on a DiskANN field sent HNSW params
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
/**
 * Bug reproduction: radius/linear/refiner on a DiskANN field failed.
 *
 * Expected: radius and linear work; the refiner, which DiskANN does not
 *           support upstream, reports that rather than a params-type mismatch.
 * Actual:   every one of the three failed with
 *           "query params type does not match the index type of vector
 *           field[v], expected DISKANN but got HNSW".
 *
 * Cause:    ensure_query_params_for_field() had no IndexType::DISKANN case, so
 *           it fell through to a hardcoded HNSW fallback. Upstream then
 *           rejected the params because their type did not match the field's
 *           index type. The fallback ran even when the index type *was*
 *           resolved, so the error blamed HNSW for a field that is DiskANN.
 *
 *           Radius and linear are genuinely supported by the DiskANN core, so
 *           they were broken purely by the wrong param type. The refiner is
 *           an upstream limitation, but it should say so.
 *
 * Status: Fixed -- a DISKANN case builds DiskAnnQueryParams, and an index
 *         type with no case gets no params at all instead of wrong ones.
 *
 * Location: ffi/zvec_ffi.cc, ensure_query_params_for_field() and the group-by
 *           switch in zvec_collection_group_by_query_vector().
 */

declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';
// LOG_FATAL: the refiner case is *expected* to fail, and upstream logs it at
// ERROR on stderr, which would otherwise land in the expected output.
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_FATAL);

$path = __DIR__ . '/../test_dbs/bug_0057_' . uniqid();

try {
    $schema = new ZVecSchema('bug_0057');
    $schema->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_L2);
    $c = ZVec::create($path, $schema);
    $c->createIndex('v', ZVecIndexParams::forDiskAnn(
        metricType: ZVecSchema::METRIC_L2,
        maxDegree: 32,
        listSize: 100,
    ));
    foreach ([['doc1', [1.0, 0.0, 0.0, 0.0]], ['doc2', [0.9, 0.1, 0.0, 0.0]], ['doc3', [0.0, 1.0, 0.0, 0.0]]] as [$pk, $vec]) {
        $c->insert((new ZVecDoc($pk))->setVectorFp32('v', $vec));
    }
    $c->optimize();

    $cases = [
        // doc3 is at squared L2 distance 2.0, so a radius of 0.5 excludes it.
        'radius only' => static fn(ZVecVectorQuery $q) => $q->setRadius(0.5),
        'linear only' => static fn(ZVecVectorQuery $q) => $q->setLinear(true),
        'explicit+radius' => static fn(ZVecVectorQuery $q) => $q->setDiskAnnParams(300)->setRadius(0.5),
    ];
    foreach ($cases as $name => $apply) {
        $q = (new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]))->setTopk(3);
        $apply($q);
        try {
            $pks = array_map(static fn(ZVecDoc $d): string => $d->getPk(), $c->queryVector($q));
            echo "$name: " . implode(',', $pks) . "\n";
        } catch (ZVecException $e) {
            echo "$name: ERROR code={$e->getCode()} {$e->getMessage()}\n";
        }
    }

    // The refiner is not supported by the DiskANN core upstream. What matters
    // is that the failure is now upstream's own, not "expected DISKANN but
    // got HNSW" -- that message blamed the wrong thing entirely.
    $refiner = (new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]))->setTopk(3)->setUsingRefiner(true);
    try {
        $c->queryVector($refiner);
        echo "refiner: accepted\n";
    } catch (ZVecException $e) {
        echo 'refiner: ' . (str_contains($e->getMessage(), 'expected DISKANN but got HNSW') ? 'WRONG PARAMS' : 'upstream error') . "\n";
    }

    $c->close();
} finally {
    exec('rm -rf ' . escapeshellarg($path));
}
?>
--EXPECT--
radius only: doc1,doc2
linear only: doc1,doc2,doc3
explicit+radius: doc1,doc2
refiner: upstream error
