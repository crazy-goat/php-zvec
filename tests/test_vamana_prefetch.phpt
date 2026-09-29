--TEST--
Vamana prefetch: setVamanaPrefetch() is accepted and does not break Vamana queries
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/vamana_prefetch_' . uniqid();
try {
    $schema = new ZVecSchema('vamana_prefetch_test');
    $schema->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $c = ZVec::create($path, $schema);
    $c->createIndex('v', ZVecIndexParams::forVamana(
        metricType: ZVecSchema::METRIC_IP,
        maxDegree: 32,
        searchListSize: 50,
    ));
    foreach ([['doc1', [1.0, 0.0, 0.0, 0.0]], ['doc2', [0.9, 0.1, 0.0, 0.0]], ['doc3', [0.0, 1.0, 0.0, 0.0]]] as [$pk, $vec]) {
        $c->insert((new ZVecDoc($pk))->setVectorFp32('v', $vec));
    }
    $c->optimize();

    /** @return string[] PKs for a query configured by $configure */
    $query = function (callable $configure) use ($c): array {
        $q = new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]);
        $q->setTopk(3);
        $configure($q);
        return array_map(static fn(ZVecDoc $d): string => $d->getPk(), $c->queryVector($q));
    };
    $fmt = static fn(array $pks): string => implode(',', $pks);

    $baseline = $query(static fn(ZVecVectorQuery $q) => $q);
    echo 'baseline: ' . $fmt($baseline) . "\n";

    // Call order must not matter: the stored prefetch values are re-applied
    // whichever setter runs last.
    echo 'prefetch-then-ef:  ' . $fmt($query(static fn(ZVecVectorQuery $q) => $q->setVamanaPrefetch(256, 4)->setVamanaParams(64))) . "\n";
    echo 'ef-then-prefetch:  ' . $fmt($query(static fn(ZVecVectorQuery $q) => $q->setVamanaParams(64)->setVamanaPrefetch(256, 4))) . "\n";

    // Prefetch with no setVamanaParams() at all. This is the case that proves
    // the setter creates VamanaQueryParams: if it built HnswQueryParams instead,
    // upstream would reject the query with a params-type mismatch.
    echo 'prefetch-only:     ' . $fmt($query(static fn(ZVecVectorQuery $q) => $q->setVamanaPrefetch(256, 4))) . "\n";

    echo 'ranking preserved: ' . ($query(static fn(ZVecVectorQuery $q) => $q->setVamanaPrefetch(256, 4)) === $baseline ? 'yes' : 'no') . "\n";

    $q = new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]);
    $q->setVamanaPrefetch(256, 4);
    echo "offset: {$q->prefetchOffset} lines: {$q->prefetchLines}\n";

    // 0/0 is the "disabled" combination and must not throw.
    echo 'prefetch 0/0:      ' . $fmt($query(static fn(ZVecVectorQuery $q) => $q->setVamanaPrefetch(0, 0))) . "\n";

    try {
        (new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]))->setVamanaPrefetch(-1, 4);
        echo "negative offset accepted\n";
    } catch (ZVecException $e) {
        echo "negative offset rejected\n";
    }
    try {
        (new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]))->setVamanaPrefetch(256, -1);
        echo "negative lines accepted\n";
    } catch (ZVecException $e) {
        echo "negative lines rejected\n";
    }

    echo "PASS: Vamana prefetch options work\n";
} finally {
    exec('rm -rf ' . escapeshellarg($path));
}
?>
--EXPECT--
baseline: doc1,doc2,doc3
prefetch-then-ef:  doc1,doc2,doc3
ef-then-prefetch:  doc1,doc2,doc3
prefetch-only:     doc1,doc2,doc3
ranking preserved: yes
offset: 256 lines: 4
prefetch 0/0:      doc1,doc2,doc3
negative offset rejected
negative lines rejected
PASS: Vamana prefetch options work
