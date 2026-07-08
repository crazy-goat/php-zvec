<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);
$path = __DIR__ . '/../test_dbs/e2e_' . uniqid();
try {
    $schema = new ZVecSchema('test');
    $schema->addVectorFp32('fp32', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $schema->addSparseVectorFp16('sv16', metricType: ZVecSchema::METRIC_IP);
    $schema->addBinary('bin');
    $schema->addArrayInt32('ids');
    $schema->addArrayString('tags');
    $coll = ZVec::create($path, $schema);

    $doc = new ZVecDoc('d1');
    $doc->setVectorFp32('fp32', [0.1, 0.2, 0.3, 0.4]);
    $doc->setSparseVectorFp16('sv16', [0, 3], [15360, 16384]);
    $doc->setBinary('bin', "binary\x00data");
    $doc->setArrayInt32('ids', [1, 2, 3]);
    $doc->setArrayString('tags', ['tag1', 'tag2']);
    $coll->insert($doc);
    $coll->flush();
    $coll->close();
    unset($coll);

    $coll = ZVec::open($path);
    $fetched = $coll->fetch('d1');
    $d = $fetched[0];

    $fp32 = $d->getVectorFp32('fp32');
    echo 'fp32: ' . implode(',', array_map(fn($v) => round($v, 1), $fp32)) . "\n";

    $sv16 = $d->getSparseVectorFp16('sv16');
    echo 'sv16 indices: ' . implode(',', $sv16['indices']) . "\n";
    echo 'sv16 values: ' . implode(',', $sv16['values']) . "\n";

    $bin = $d->getBinary('bin');
    echo 'bin hex: ' . bin2hex($bin) . "\n";

    echo 'ids: ' . implode(',', $d->getArrayInt32('ids')) . "\n";
    echo 'tags: ' . implode(',', $d->getArrayString('tags')) . "\n";

    echo "OK\n";
} finally {
    if (isset($coll)) { try { $coll->destroy(); } catch (Exception $e) {} }
    exec("rm -rf " . escapeshellarg($path));
}
?>
