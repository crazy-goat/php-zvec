--TEST--
Collection close(): calls upstream close(), flushes, stays idempotent, destructor stays safe
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$schema = new ZVecSchema('close_real');
$schema->addInt64('id', nullable: false, withInvertIndex: true)
    ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

$path = __DIR__ . '/../test_dbs/close_real_' . uniqid();
$path2 = $path . '_d';

try {
    $collection = ZVec::create($path, $schema);
    $collection->insert(
        (new ZVecDoc('doc1'))->setInt64('id', 1)->setVectorFp32('v', [1.0, 0.0, 0.0, 0.0])
    );

    // No flush() before close(): close() has to flush, and the lock has to be
    // released, or reopening the same path below would fail.
    $collection->close();

    $reopened = ZVec::open($path);
    $docs = $reopened->fetch('doc1');
    echo 'count=' . count($docs) . ' id=' . $docs[0]->getInt64('id') . "\n";

    $reopened->close();
    try {
        $reopened->close();
        echo "second close: ok\n";
    } catch (ZVecException $e) {
        echo "second close: THREW {$e->getMessage()}\n";
    }

    try {
        $reopened->insert(
            (new ZVecDoc('doc2'))->setInt64('id', 2)->setVectorFp32('v', [0.0, 1.0, 0.0, 0.0])
        );
        echo "closed insert: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo 'closed: ' . $e->getMessage() . "\n";
    }

    $reopened->destroy();
    try {
        $reopened->close();
        echo "close after destroy: ok\n";
    } catch (ZVecException $e) {
        echo "close after destroy: THREW {$e->getMessage()}\n";
    }
} finally {
    exec('rm -rf ' . escapeshellarg($path));
    exec('rm -rf ' . escapeshellarg($path2));
}

// Destructor path: the collection goes out of scope without an explicit close(),
// so __destruct() is the thing that has to flush and release the lock.
try {
    $build = static function (string $p, ZVecSchema $s): void {
        $c = ZVec::create($p, $s);
        $c->insert(
            (new ZVecDoc('a'))->setInt64('id', 7)->setVectorFp32('v', [1.0, 0.0, 0.0, 0.0])
        );
    };
    $build($path2, $schema);

    $reopened2 = ZVec::open($path2);
    echo 'destructor flushed: ' . $reopened2->fetch('a')[0]->getInt64('id') . "\n";
    $reopened2->close();
} finally {
    exec('rm -rf ' . escapeshellarg($path2));
}
?>
--EXPECT--
count=1 id=1
second close: ok
closed: Collection is closed. Open with ZVec::open() to continue.
close after destroy: ok
destructor flushed: 7
