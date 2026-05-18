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
    $opts = ZVecCollectionOptions::readWrite()
        ->setEnableMmap(false)
        ->setMaxBufferSize(64 * 1024 * 1024);
    $c = ZVec::createWith($path, $schema, $opts);

    $ro = $c->getOptions();
    echo "readOnly: " . ($ro->getReadOnly() ? 'true' : 'false') . "\n";
    echo "mmap: " . ($ro->getEnableMmap() ? 'true' : 'false') . "\n";
    echo "buffer: " . $ro->getMaxBufferSize() . "\n";

    $c->close();

    // Test 2: Reopen read-only
    $opts2 = ZVecCollectionOptions::readOnly()->setEnableMmap(true);
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

    // Test 4: defaults() factory
    $defaults = ZVecCollectionOptions::defaults();
    echo "defaults readOnly: " . ($defaults->getReadOnly() ? 'true' : 'false') . "\n";
    echo "defaults mmap: " . ($defaults->getEnableMmap() ? 'true' : 'false') . "\n";

    // Test 5: Fluent setters return $this
    $opts5 = ZVecCollectionOptions::readWrite()
        ->setReadOnly(true)
        ->setEnableMmap(false)
        ->setMaxBufferSize(1024);
    echo "fluent readOnly: " . ($opts5->getReadOnly() ? 'true' : 'false') . "\n";
    echo "fluent mmap: " . ($opts5->getEnableMmap() ? 'true' : 'false') . "\n";
    echo "fluent buffer: " . $opts5->getMaxBufferSize() . "\n";

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
fluent readOnly: true
fluent mmap: false
fluent buffer: 1024
OK
