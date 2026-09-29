--TEST--
Bug 0058: a failed destroy() must not free the native collection (use-after-free)
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
/**
 * Bug reproduction: failed destroy() freed the native collection.
 *
 * Expected: after a rejected destroy(), the object stays open and usable, and
 *           the on-disk data survives.
 * Actual:   zvec_collection_destroy() erased the handle from the registry even
 *           when upstream returned an error, deleting the C++ Collection while
 *           the PHP object still held the raw pointer. The next method call
 *           used freed memory -- observed as
 *           "ZVecException: collection is already closed." from a destroyed
 *           object, and as a use-after-free in principle.
 *
 * Trigger: destroying a read-only collection. Upstream's destroy() starts with
 * a read-only check and returns INVALID_ARGUMENT, changing nothing.
 *
 * Status: Fixed -- the registry entry is erased only on success, so the handle
 *         stays valid for the caller.
 *
 * Location: ffi/zvec_ffi.cc, zvec_collection_destroy().
 */

declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/bug_0058_' . uniqid();
try {
    $schema = new ZVecSchema('bug_0058');
    $schema->addInt64('id', nullable: false)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $collection = ZVec::create($path, $schema);
    $collection->insert(
        (new ZVecDoc('doc1'))->setInt64('id', 1)->setVectorFp32('v', [1.0, 0.0, 0.0, 0.0])
    );
    $collection->close();

    $readOnly = ZVec::open($path, readOnly: true);
    try {
        $readOnly->destroy();
        echo "destroy: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo 'destroy rejected: ' . $e->getErrorCodeString() . "\n";
    }

    // This is the line that used to operate on a freed Collection.
    echo 'fetch after failed destroy: ' . count($readOnly->fetch('doc1')) . "\n";

    $readOnly->close();
    echo "close ok\n";

    echo is_dir($path) ? "data kept\n" : "data lost\n";
} finally {
    exec('rm -rf ' . escapeshellarg($path));
}
?>
--EXPECT--
destroy rejected: INVALID_ARGUMENT
fetch after failed destroy: 1
close ok
data kept
