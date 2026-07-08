<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);
$path = __DIR__ . '/../test_dbs/sparsefp16_' . uniqid();
try {
    $schema = new ZVecSchema('test');
    $schema->addSparseVectorFp16('sparse', metricType: ZVecSchema::METRIC_IP);
    $coll = ZVec::create($path, $schema);

    $doc = new ZVecDoc('empty');
    $doc->setSparseVectorFp16('sparse', [], []);
    $coll->insert($doc);
    $fetched = $coll->fetch('empty');
    $sv = $fetched[0]->getSparseVectorFp16('sparse');
    echo "empty count: " . count($sv['indices']) . "\n";

    $doc2 = new ZVecDoc('d1');
    $doc2->setSparseVectorFp16('sparse', [0, 2], [15360, 0]);
    $coll->insert($doc2);
    $fetched2 = $coll->fetch('d1');
    $sv2 = $fetched2[0]->getSparseVectorFp16('sparse');
    echo "indices: " . implode(',', $sv2['indices']) . "\n";
    echo "values: " . implode(',', $sv2['values']) . "\n";

    echo "OK\n";
} finally {
    if (isset($coll)) { try { $coll->destroy(); } catch (Exception $e) {} }
    exec("rm -rf " . escapeshellarg($path));
}
?>
