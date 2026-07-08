<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);
$path = __DIR__ . '/../test_dbs/array_' . uniqid();
try {
    $schema = new ZVecSchema('test');
    $schema->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $schema->addArrayInt32('i32');
    $schema->addArrayInt64('i64');
    $schema->addArrayUint32('u32');
    $schema->addArrayUint64('u64');
    $schema->addArrayFloat('f32');
    $schema->addArrayDouble('f64');
    $schema->addArrayString('strs');
    $schema->addArrayBool('bools');
    $coll = ZVec::create($path, $schema);

    $doc = new ZVecDoc('d1');
    $doc->setVectorFp32('vec', [1.0, 0.0, 0.0, 0.0]);
    $doc->setArrayInt32('i32', [1, -2, 3]);
    $doc->setArrayInt64('i64', [100, -200]);
    $doc->setArrayUint32('u32', [10, 20, 30]);
    $doc->setArrayUint64('u64', [1000, 2000]);
    $doc->setArrayFloat('f32', [1.5, 2.5, 3.5]);
    $doc->setArrayDouble('f64', [1.1, 2.2]);
    $doc->setArrayString('strs', ['hello', 'world', 'foo']);
    $doc->setArrayBool('bools', [true, false, true]);
    $coll->insert($doc);

    $fetched = $coll->fetch('d1');
    $d = $fetched[0];
    echo 'i32: ' . implode(',', $d->getArrayInt32('i32')) . "\n";
    echo 'i64: ' . implode(',', $d->getArrayInt64('i64')) . "\n";
    echo 'u32: ' . implode(',', $d->getArrayUint32('u32')) . "\n";
    echo 'u64: ' . implode(',', $d->getArrayUint64('u64')) . "\n";
    echo 'f32: ' . implode(',', array_map(fn($v) => round($v, 1), $d->getArrayFloat('f32'))) . "\n";
    echo 'f64: ' . implode(',', array_map(fn($v) => round($v, 1), $d->getArrayDouble('f64'))) . "\n";
    echo 'strs: ' . implode(',', $d->getArrayString('strs')) . "\n";
    $bools = $d->getArrayBool('bools');
    echo 'bools: ' . implode(',', array_map(fn($v) => $v ? '1' : '0', $bools)) . "\n";

    echo "OK\n";
} finally {
    if (isset($coll)) { try { $coll->destroy(); } catch (Exception $e) {} }
    exec("rm -rf " . escapeshellarg($path));
}
?>
