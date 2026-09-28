--TEST--
include_doc_id: query returns internal numeric doc id alongside PK (issue #178)
--SKIPIF--
<?php if (extension_loaded('zvec')) die('skip This test uses ZVecVectorQuery which only works via FFI'); ?>
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/include_doc_id_' . uniqid();
try {
    $schema = new ZVecSchema('docid_test');
    $schema->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    foreach ([['doc1', [1.0, 0.0, 0.0, 0.0]], ['doc2', [0.9, 0.1, 0.0, 0.0]], ['doc3', [0.0, 1.0, 0.0, 0.0]]] as [$pk, $vec]) {
        $c->insert((new ZVecDoc($pk))->setVectorFp32('v', $vec));
    }
    $c->flush();

    // Flag off (default): doc id stays 0, PK unaffected
    $vq = new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]);
    $vq->setTopk(3);
    $off = $c->queryVector($vq);
    echo "flag off: ids=";
    foreach ($off as $d) { echo $d->getDocId() . ','; }
    echo " pks=";
    foreach ($off as $d) { echo $d->getPk() . ','; }
    echo "\n";

    // Flag on: doc ids populated
    $vq2 = new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]);
    $vq2->setTopk(3)->setIncludeDocId(true);
    echo "includeDocId property: " . var_export($vq2->includeDocId, true) . "\n";

    $on = $c->queryVector($vq2);
    echo "flag on:  ids=";
    foreach ($on as $d) { echo $d->getDocId() . ','; }
    echo " pks=";
    foreach ($on as $d) { echo $d->getPk() . ','; }
    echo "\n";

    // Ranking must be identical regardless of the flag
    $pksOff = array_map(fn($d) => $d->getPk(), $off);
    $pksOn  = array_map(fn($d) => $d->getPk(), $on);
    echo "same ranking: " . ($pksOff === $pksOn ? 'yes' : 'NO') . "\n";

    // Doc ids must be distinct. Upstream assigns 0-based ids, so doc1 is 0 --
    // do not assert non-zeroness, the base is an upstream implementation detail.
    $ids = array_map(fn($d) => $d->getDocId(), $on);
    echo "ids distinct:  " . (count(array_unique($ids)) === count($ids) ? 'yes' : 'NO') . "\n";

    // Explicitly disabling again must clear the flag
    $vq2->setIncludeDocId(false);
    echo "toggled off: " . var_export($vq2->includeDocId, true) . "\n";
    $offAgain = $c->queryVector($vq2);
    echo "after toggle: ids=";
    foreach ($offAgain as $d) { echo $d->getDocId() . ','; }
    echo "\n";

    $c->close();
    echo "PASS: include_doc_id works\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
flag off: ids=0,0,0, pks=doc1,doc2,doc3,
includeDocId property: true
flag on:  ids=0,1,2, pks=doc1,doc2,doc3,
same ranking: yes
ids distinct:  yes
toggled off: false
after toggle: ids=0,0,0,
PASS: include_doc_id works
