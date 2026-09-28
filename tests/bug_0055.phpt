--TEST--
Bug 0055: getIndexType() reports Vamana as DiskANN (enum value mismatch, GH#215)
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/bug_0055_' . uniqid();

try {
    $schema = new ZVecSchema('bug_0055');
    $schema->addInt64('id', nullable: false)
        ->addVectorFp32('v_vamana', dimension: 4, metricType: ZVecSchema::METRIC_IP)
        ->addVectorFp32('v_diskann', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $collection = ZVec::create($path, $schema);

    $collection->createIndex('v_vamana', ZVecIndexParams::forVamana(ZVecSchema::METRIC_IP));
    $collection->createIndex('v_diskann', ZVecIndexParams::forDiskAnn(ZVecSchema::METRIC_IP));

    $vamanaType = $collection->getFieldSchema('v_vamana')->getIndexType();
    $diskAnnType = $collection->getFieldSchema('v_diskann')->getIndexType();

    echo "vamana index type: $vamanaType\n";
    echo "diskann index type: $diskAnnType\n";

    assert($vamanaType === ZVec::INDEX_TYPE_VAMANA, "expected INDEX_TYPE_VAMANA, got $vamanaType");
    assert($diskAnnType === ZVec::INDEX_TYPE_DISKANN, "expected INDEX_TYPE_DISKANN, got $diskAnnType");

    $collection->close();

    echo "OK: Vamana/DiskANN index types round-trip correctly\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
vamana index type: 5
diskann index type: 6
OK: Vamana/DiskANN index types round-trip correctly
