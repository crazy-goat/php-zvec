--TEST--
Bug 0056: no heap corruption at exit from a duplicated GlobalConfig singleton (GH#215)
--DESCRIPTION--
libzvec exports ailego::Singleton<GlobalConfig>::Instance()::obj but keeps its
init guard local. When the adapter instantiated the inline Instance() itself,
the object was constructed twice (dropping the ZVec::init() settings) and
destroyed twice at exit: "free(): chunks in smallbin corrupted", SIGABRT.
run-tests.php reports a signal-terminated process as a failure.
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';

$status = ZVec::ffi()->zvec_ffi_initialize(null);
echo "initialize(null): code=" . $status->code . "\n";

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN, queryThreads: 7, optimizeThreads: 3);

$path = __DIR__ . '/../test_dbs/bug_0056_' . uniqid();
try {
    $schema = new ZVecSchema('bug_0056');
    $schema->addInt64('id', nullable: false)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $collection = ZVec::create($path, $schema);
    $collection->insert((new ZVecDoc('doc1'))->setInt64('id', 1)->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]));
    $collection->optimize();
    $results = $collection->query('v', [0.1, 0.2, 0.3, 0.4], topk: 1);
    echo "query: " . $results[0]->getPk() . "\n";
    $collection->close();
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
echo "exit\n";
?>
--EXPECT--
initialize(null): code=0
query: doc1
exit
