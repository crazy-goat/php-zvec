--TEST--
HNSW prefetch params: setHnswPrefetch() is accepted and does not break HNSW queries (issue #181)
--SKIPIF--
<?php if (extension_loaded('zvec')) die('skip This test uses ZVecVectorQuery which only works via FFI'); ?>
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/hnsw_prefetch_' . uniqid();
try {
    $schema = new ZVecSchema('prefetch_test');
    $schema->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createIndex('v', ZVecIndexParams::forHnsw(metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 100));

    foreach ([['doc1', [1.0, 0.0, 0.0, 0.0]], ['doc2', [0.9, 0.1, 0.0, 0.0]], ['doc3', [0.0, 1.0, 0.0, 0.0]]] as [$pk, $vec]) {
        $c->insert((new ZVecDoc($pk))->setVectorFp32('v', $vec));
    }
    $c->optimize();

    // Baseline: no prefetch tuning
    $base = (new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]))->setTopk(3);
    $basePks = array_map(fn($d) => $d->getPk(), $c->queryVector($base));
    echo "baseline: " . implode(',', $basePks) . "\n";

    // Prefetch before params -- order must not matter
    $q1 = new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]);
    $q1->setTopk(3)->setHnswPrefetch(prefetchOffset: 256, prefetchLines: 4);
    $q1->setHnswParams(ef: 64);
    $pks1 = array_map(fn($d) => $d->getPk(), $c->queryVector($q1));
    echo "prefetch-then-ef:  " . implode(',', $pks1) . "\n";

    // Prefetch after params
    $q2 = new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]);
    $q2->setTopk(3)->setHnswParams(ef: 64)->setHnswPrefetch(prefetchOffset: 256, prefetchLines: 4);
    $pks2 = array_map(fn($d) => $d->getPk(), $c->queryVector($q2));
    echo "ef-then-prefetch:  " . implode(',', $pks2) . "\n";

    // Prefetch must not change which documents match
    echo "ranking preserved: " . ($pks1 === $basePks && $pks2 === $basePks ? 'yes' : 'NO') . "\n";

    // Offsets are readable back
    echo "offset: " . var_export($q1->prefetchOffset, true) . " lines: " . var_export($q1->prefetchLines, true) . "\n";

    // 0/0 is a legal no-prefetch configuration
    $q3 = (new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]))->setTopk(3)->setHnswPrefetch(0, 0);
    $pks3 = array_map(fn($d) => $d->getPk(), $c->queryVector($q3));
    echo "prefetch 0/0:      " . implode(',', $pks3) . "\n";

    // Negative values rejected
    try {
        (new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]))->setHnswPrefetch(-1, 4);
        echo "UNEXPECTED: negative offset accepted\n";
    } catch (ZVecException $e) {
        echo "negative offset rejected\n";
    }
    try {
        (new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]))->setHnswPrefetch(256, -1);
        echo "UNEXPECTED: negative lines accepted\n";
    } catch (ZVecException $e) {
        echo "negative lines rejected\n";
    }

    $c->close();
    echo "PASS: HNSW prefetch options work\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
baseline: doc1,doc2,doc3
prefetch-then-ef:  doc1,doc2,doc3
ef-then-prefetch:  doc1,doc2,doc3
ranking preserved: yes
offset: 256 lines: 4
prefetch 0/0:      doc1,doc2,doc3
negative offset rejected
negative lines rejected
PASS: HNSW prefetch options work
