--TEST--
Config init: opaque config API with isInitialized and shutdown
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

// isInitialized should be false before init
echo "pre-init: " . (ZVec::isInitialized() ? '1' : '0') . "\n";

// Init with console logging and custom thread counts
ZVec::init(
    logType: ZVec::LOG_CONSOLE,
    logLevel: ZVec::LOG_WARN,
    queryThreads: 4,
    optimizeThreads: 2,
);
echo "post-init: " . (ZVec::isInitialized() ? '1' : '0') . "\n";

// After init, operations work
$path = __DIR__ . '/../test_dbs/config_init_' . uniqid();
try {
    $schema = new ZVecSchema('test');
    $schema->addInt64('id');
    $schema->addVectorFp32('vec', dimension: 4);
    $coll = ZVec::create($path, $schema);
    $doc = new ZVecDoc('d1');
    $doc->setInt64('id', 42);
    $doc->setVectorFp32('vec', [0.1, 0.2, 0.3, 0.4]);
    $coll->insert($doc);
    $coll->flush();
    $fetched = $coll->fetch('d1');
    echo "fetched id: " . $fetched[0]->getInt64('id') . "\n";
    $coll->close();
    echo "collection ops ok\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}

// Shutdown
ZVec::shutdown();
echo "shutdown: " . (ZVec::isInitialized() ? '1' : '0') . "\n";

// Re-init after shutdown
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);
echo "re-init: " . (ZVec::isInitialized() ? '1' : '0') . "\n";

echo "OK\n";
?>
--EXPECTF--
pre-init: 0
post-init: 1
fetched id: 42
collection ops ok
shutdown: 0
re-init: 1
OK
