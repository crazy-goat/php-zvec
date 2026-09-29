--TEST--
queryMulti() BC: FTS and duplicate-field sub-queries are rejected with a clear error
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_FATAL);

$path = __DIR__ . '/../test_dbs/multi_bc_' . uniqid();

try {
    $schema = new ZVecSchema('multi_bc_test');
    $schema->addString('tag', nullable: true)
        ->addString('body')
        ->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP)
        ->addVectorFp32('vec2', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createIndex('body', ZVecIndexParams::forFts());
    foreach ([
        ['d1', 'the quick brown fox', [1.0, 0.0, 0.0, 0.0], [0.0, 1.0, 0.0, 0.0]],
        ['d2', 'a fast brown rabbit', [0.0, 1.0, 0.0, 0.0], [1.0, 0.0, 0.0, 0.0]],
    ] as [$pk, $body, $v1, $v2]) {
        $c->insert(
            (new ZVecDoc($pk))->setString('tag', 't' . $pk)->setString('body', $body)
                ->setVectorFp32('vec', $v1)->setVectorFp32('vec2', $v2)
        );
    }
    $c->flush();

    $fts = (new ZVecVectorQuery('body', []))->setFts('body', 'brown');
    $vecA = new ZVecVectorQuery('vec', [1.0, 0.0, 0.0, 0.0]);
    $vecB = new ZVecVectorQuery('vec2', [1.0, 0.0, 0.0, 0.0]);

    // These used to fail deep inside FFI, or silently return fewer results.
    try {
        $c->queryMulti([$fts, $vecA], new ZVecRrfReRanker(), topk: 3);
        echo "fts sub-query: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo 'fts sub-query: ' . $e->getMessage() . "\n";
    }

    try {
        $c->queryMulti([$vecA, (new ZVecVectorQuery('vec', [0.0, 1.0, 0.0, 0.0]))], new ZVecRrfReRanker(), topk: 3);
        echo "duplicate field: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo 'duplicate field: ' . $e->getMessage() . "\n";
    }

    // Plain multi-vector still works, unchanged.
    $docs = $c->queryMulti([$vecA, $vecB], new ZVecRrfReRanker(), topk: 3);
    echo 'multi-vector: ' . count($docs) . ' results, ranks=' . (count($docs[0]->getSourceRanks()) > 0 ? 'present' : 'MISSING') . "\n";

    // The native path handles the same query.
    $native = $c->multiQuery([$vecA, $vecB], new ZVecRrfReRanker(), topk: 3);
    echo 'multiQuery same: ' . implode(',', array_map(static fn(ZVecDoc $d): string => $d->getPk(), $native)) . "\n";
} finally {
    exec('rm -rf ' . escapeshellarg($path));
}
?>
--EXPECT--
fts sub-query: queryMulti() cannot run FTS sub-queries; use multiQuery() for hybrid FTS + vector search
duplicate field: queryMulti() got two sub-queries on field 'vec'; use multiQuery() instead
multi-vector: 2 results, ranks=present
multiQuery same: d1,d2
