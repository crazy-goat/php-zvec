--TEST--
DiskANN index: forDiskAnn() params + setDiskAnnParams() query (issue #179)
--SKIPIF--
<?php if (extension_loaded('zvec')) die('skip This test uses ZVecIndexParams which only works via FFI'); ?>
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/diskann_' . uniqid();
try {
    // Constants must be distinct from the Vamana ones
    echo "INDEX_TYPE_DISKANN=" . ZVec::INDEX_TYPE_DISKANN . " (vamana=" . ZVec::INDEX_TYPE_VAMANA . ")\n";
    echo "QUERY_PARAM_DISKANN=" . ZVec::QUERY_PARAM_DISKANN . " (vamana=" . ZVec::QUERY_PARAM_VAMANA . ")\n";

    // Validation
    foreach ([['maxDegree', 0], ['listSize', 0], ['pqChunkNum', -1]] as [$arg, $bad]) {
        try {
            match ($arg) {
                'maxDegree'   => ZVecIndexParams::forDiskAnn(metricType: ZVecSchema::METRIC_IP, maxDegree: $bad),
                'listSize'    => ZVecIndexParams::forDiskAnn(metricType: ZVecSchema::METRIC_IP, listSize: $bad),
                'pqChunkNum'  => ZVecIndexParams::forDiskAnn(metricType: ZVecSchema::METRIC_IP, pqChunkNum: $bad),
            };
            echo "UNEXPECTED: $arg=$bad accepted\n";
        } catch (ZVecException $e) {
            echo "$arg=$bad rejected\n";
        }
    }

    $schema = new ZVecSchema('diskann_test');
    $schema->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $c = ZVec::create($path, $schema);

    $c->createIndex('v', ZVecIndexParams::forDiskAnn(
        metricType: ZVecSchema::METRIC_IP,
        maxDegree: 32,
        listSize: 100,
        pqChunkNum: 0,
    ));

    foreach ([['doc1', [1.0, 0.0, 0.0, 0.0]], ['doc2', [0.9, 0.1, 0.0, 0.0]], ['doc3', [0.0, 1.0, 0.0, 0.0]]] as [$pk, $vec]) {
        $c->insert((new ZVecDoc($pk))->setVectorFp32('v', $vec));
    }
    $c->optimize();

    // Default query works
    $base = (new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]))->setTopk(3);
    $basePks = array_map(fn($d) => $d->getPk(), $c->queryVector($base));
    echo "default: " . implode(',', $basePks) . "\n";

    // With explicit list size
    $q = new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]);
    $q->setTopk(3)->setDiskAnnParams(listSize: 200);
    echo "paramType: " . $q->queryParamType . " (expect " . ZVec::QUERY_PARAM_DISKANN . ")\n";
    echo "listSize readback: " . var_export($q->diskAnnListSize, true) . "\n";
    $pks = array_map(fn($d) => $d->getPk(), $c->queryVector($q));
    echo "listSize=200: " . implode(',', $pks) . "\n";
    echo "ranking preserved: " . ($pks === $basePks ? 'yes' : 'NO') . "\n";

    // DiskANN params must be rejected on a Vamana index
    $c->createIndex('v', ZVecIndexParams::forVamana(metricType: ZVecSchema::METRIC_IP, maxDegree: 32, searchListSize: 50));
    try {
        $c->queryVector((new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]))->setTopk(3)->setDiskAnnParams(200));
        echo "UNEXPECTED: DiskANN params accepted on Vamana index\n";
    } catch (ZVecException $e) {
        echo "DiskANN params on Vamana rejected: " . $e->getErrorCodeString() . "\n";
    }

    $c->close();
    echo "PASS: DiskANN index support works\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
INDEX_TYPE_DISKANN=6 (vamana=5)
QUERY_PARAM_DISKANN=6 (vamana=5)
maxDegree=0 rejected
listSize=0 rejected
pqChunkNum=-1 rejected
default: doc1,doc2,doc3
paramType: 6 (expect 6)
listSize readback: 200
listSize=200: doc1,doc2,doc3
ranking preserved: yes
DiskANN params on Vamana rejected: INVALID_ARGUMENT
PASS: DiskANN index support works
