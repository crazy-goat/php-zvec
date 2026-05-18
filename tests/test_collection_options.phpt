--TEST--
CollectionOptions: createWith/openWith with ZVecCollectionOptions object
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/col_opts_' . uniqid();
try {
    $schema = new ZVecSchema('options_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    // Test 1: createWith with read-write options
    $opts = new ZVecCollectionOptions(readOnly: false, enableMmap: false, maxBufferSize: 64 * 1024 * 1024);
    $c = ZVec::createWith($path, $schema, $opts);

    $ro = $c->getOptions();
    echo "readOnly: " . ($ro->getReadOnly() ? 'true' : 'false') . "\n";
    echo "mmap: " . ($ro->getEnableMmap() ? 'true' : 'false') . "\n";
    echo "buffer: " . $ro->getMaxBufferSize() . "\n";

    $c->close();

    // Test 2: Reopen read-only
    $opts2 = new ZVecCollectionOptions(readOnly: true, enableMmap: true);
    $c2 = ZVec::openWith($path, $opts2);
    $ro2 = $c2->getOptions();
    echo "reopen readOnly: " . ($ro2->getReadOnly() ? 'true' : 'false') . "\n";
    $c2->close();

    // Test 3: Backward compat still works
    $legacyPath = __DIR__ . '/../test_dbs/col_opts_legacy_' . uniqid();
    try {
        $c3 = ZVec::create($legacyPath, $schema, readOnly: false, enableMmap: false, maxBufferSize: 33554432);
        $optsArr = $c3->options();
        echo "legacy readOnly: " . ($optsArr['read_only'] ? 'true' : 'false') . "\n";
        echo "legacy mmap: " . ($optsArr['enable_mmap'] ? 'true' : 'false') . "\n";
        echo "legacy buffer: " . $optsArr['max_buffer_size'] . "\n";
        $c3->destroy();
    } finally {
        exec("rm -rf " . escapeshellarg($legacyPath));
    }

    // Test 4: Direct property access
    $opts4 = new ZVecCollectionOptions();
    echo "defaults readOnly: " . ($opts4->readOnly ? 'true' : 'false') . "\n";
    echo "defaults mmap: " . ($opts4->enableMmap ? 'true' : 'false') . "\n";

    // Test 5: Constructor with specific values
    $opts5 = new ZVecCollectionOptions(readOnly: true, enableMmap: false, maxBufferSize: 1024);
    echo "custom readOnly: " . ($opts5->getReadOnly() ? 'true' : 'false') . "\n";
    echo "custom mmap: " . ($opts5->getEnableMmap() ? 'true' : 'false') . "\n";
    echo "custom buffer: " . $opts5->getMaxBufferSize() . "\n";

    echo "OK\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECTF--
readOnly: false
mmap: false
buffer: 67108864
reopen readOnly: true
legacy readOnly: false
legacy mmap: false
legacy buffer: 33554432
defaults readOnly: false
defaults mmap: true
custom readOnly: true
custom mmap: false
custom buffer: 1024
OK
