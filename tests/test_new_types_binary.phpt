--TEST--
New types: BINARY scalar field insert and retrieve
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);
$path = __DIR__ . '/../test_dbs/binary_' . uniqid();
try {
    $schema = new ZVecSchema('test');
    $schema->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $schema->addBinary('bin');
    $coll = ZVec::create($path, $schema);

    $doc = new ZVecDoc('d1');
    $doc->setVectorFp32('vec', [1.0, 0.0, 0.0, 0.0]);
    $doc->setBinary('bin', "\x00\x01\x02\xff\xfe");
    $coll->insert($doc);

    $fetched = $coll->fetch('d1');
    $d = $fetched[0];
    $bin = $d->getBinary('bin');
    echo "bin len: " . strlen($bin) . "\n";
    echo "bin hex: " . bin2hex($bin) . "\n";

    echo "OK\n";
} finally {
    if (isset($coll)) { try { $coll->destroy(); } catch (Exception $e) {} }
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
bin len: 5
bin hex: 000102fffe
OK
