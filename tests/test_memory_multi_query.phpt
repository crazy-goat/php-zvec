--TEST--
Memory leak: multiQuery() does not leak the multi-query handle or C strings
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_FATAL);

$path = __DIR__ . '/../test_dbs/mem_multi_' . uniqid();
$THRESHOLD = 500 * 1024;

try {
    $schema = new ZVecSchema('mem_multi');
    $schema->setMaxDocCountPerSegment(1000)
        ->addString('tag', nullable: true)
        ->addString('body')
        ->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createIndex('body', ZVecIndexParams::forFts());
    for ($i = 0; $i < 20; $i++) {
        $c->insert(
            (new ZVecDoc("d{$i}"))
                ->setString('tag', "t{$i}")
                ->setString('body', 'brown fox number ' . $i)
                ->setVectorFp32('vec', [(float)$i, 1.0, 0.0, 0.0])
        );
    }
    $c->flush();

    $run = static function () use ($c): int {
        $fts = (new ZVecVectorQuery('body', []))->setFts('body', 'brown');
        $vec = new ZVecVectorQuery('vec', [1.0, 0.0, 0.0, 0.0]);
        return count($c->multiQuery([$fts, $vec], new ZVecRrfReRanker(), topk: 3, outputFields: ['tag']));
    };

    // Warm up so one-off allocations are not counted as a leak.
    for ($i = 0; $i < 5; $i++) {
        $run();
    }

    $before = memory_get_usage();
    for ($i = 0; $i < 100; $i++) {
        $run();
    }
    $delta = memory_get_usage() - $before;

    printf("delta: %d bytes\n", $delta);
    echo $delta < $THRESHOLD ? "PASS: no leak\n" : "FAIL: leak above threshold\n";
} finally {
    exec('rm -rf ' . escapeshellarg($path));
}
?>
--EXPECTF--
delta: %d bytes
PASS: no leak
