--TEST--
FP16 Vector Support: Create collection with FP16 vectors, insert, query, retrieve
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/fp16_' . uniqid();
try {
    $schema = new ZVecSchema('fp16_test');
    $schema->addVectorFp16('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $schema->addInt64('id', nullable: false);
    
    $c = ZVec::create($path, $schema);
    
    $doc1 = new ZVecDoc('doc1');
    $doc1->setInt64('id', 1);
    $doc1->setVectorFp16('embedding', [0x3C00, 0x4000, 0x4200, 0x4400]);
    
    $doc2 = new ZVecDoc('doc2');
    $doc2->setInt64('id', 2);
    $doc2->setVectorFp16('embedding', [0x3800, 0x3C00, 0x4000, 0x4200]);
    
    $c->insert($doc1, $doc2);
    $c->flush();
    $c->createHnswIndex('embedding');
    $c->optimize();
    
    $queryVec = [0x3C00, 0x4000, 0x4200, 0x4400];
    $results = $c->queryFp16('embedding', $queryVec, topk: 2, includeVector: true);
    
    if (count($results) !== 2) {
        echo "FAIL: Expected 2 results, got " . count($results) . "\n";
    } else {
        $firstDoc = $results[0];
        if ($firstDoc->getPk() !== 'doc1') {
            echo "FAIL: Expected first result to be doc1, got " . $firstDoc->getPk() . "\n";
        } else {
            $retrieved = $firstDoc->getVectorFp16('embedding');
            if ($retrieved === null || count($retrieved) !== 4) {
                echo "FAIL: Could not retrieve FP16 vector\n";
            } else {
                echo "FP16 vector support works\n";
            }
        }
    }
    
    $c->close();
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
FP16 vector support works
