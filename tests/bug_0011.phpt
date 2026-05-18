--TEST--
BUG-011: Clone-Safety Double-Free — private __clone() prevents cloning on handle-holding classes
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/bug_0011_' . uniqid();
$allOk = true;

try {
    // ===== 1. ZVecSchema =====
    try {
        $schema = new ZVecSchema('clone_test');
        $schema->addInt64('id', nullable: false, withInvertIndex: true)
            ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);
        $clone = clone $schema;
        echo "FAIL: ZVecSchema clone should throw\n";
        $allOk = false;
    } catch (\Error $e) {
        echo "OK 1: ZVecSchema clone blocked - " . $e->getMessage() . "\n";
    }

    // ===== 2. ZVecIndexParams =====
    try {
        $params = ZVecIndexParams::forHnsw(ZVecSchema::METRIC_IP, 16, 200);
        $clone = clone $params;
        echo "FAIL: ZVecIndexParams clone should throw\n";
        $allOk = false;
    } catch (\Error $e) {
        echo "OK 2: ZVecIndexParams clone blocked - " . $e->getMessage() . "\n";
    }

    // ===== 3. ZVecVectorQuery =====
    try {
        $query = new ZVecVectorQuery('v', [0.1, 0.2, 0.3, 0.4]);
        $clone = clone $query;
        echo "FAIL: ZVecVectorQuery clone should throw\n";
        $allOk = false;
    } catch (\Error $e) {
        echo "OK 3: ZVecVectorQuery clone blocked - " . $e->getMessage() . "\n";
    }

    // ===== Create a collection for stats/fieldSchema tests =====
    $schema2 = new ZVecSchema('clone_stats');
    $schema2->addInt64('category', nullable: false, withInvertIndex: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $c = ZVec::create($path, $schema2);

    $doc = new ZVecDoc('doc1');
    $doc->setInt64('category', 1)->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc);

    // Flush so stats are available
    $c->flush();

    // ===== 4. ZVecCollectionStats =====
    try {
        $stats = $c->getStatsStruct();
        $clone = clone $stats;
        echo "FAIL: ZVecCollectionStats clone should throw\n";
        $allOk = false;
    } catch (\Error $e) {
        echo "OK 4: ZVecCollectionStats clone blocked - " . $e->getMessage() . "\n";
    }

    // ===== 5. ZVecFieldSchema =====
    try {
        $fieldSchema = $c->getFieldSchema('category');
        $clone = clone $fieldSchema;
        echo "FAIL: ZVecFieldSchema clone should throw\n";
        $allOk = false;
    } catch (\Error $e) {
        echo "OK 5: ZVecFieldSchema clone blocked - " . $e->getMessage() . "\n";
    }

    // ===== 6. ZVecDoc (ownsHandle = true) =====
    try {
        $doc2 = new ZVecDoc('test_pk');
        $doc2->setInt64('category', 99);
        $clone = clone $doc2;
        echo "FAIL: ZVecDoc clone should throw\n";
        $allOk = false;
    } catch (\Error $e) {
        echo "OK 6: ZVecDoc clone blocked - " . $e->getMessage() . "\n";
    }

    // ===== 7. ZVec (collection) =====
    try {
        $clone = clone $c;
        echo "FAIL: ZVec clone should throw\n";
        $allOk = false;
    } catch (\Error $e) {
        echo "OK 7: ZVec clone blocked - " . $e->getMessage() . "\n";
    }

    // ===== Verify normal use (no clone) still works =====
    // ZVecSchema — create and use
    $s = new ZVecSchema('normal_use');
    $s->addInt64('x', nullable: false);
    $s = null;
    echo "OK 8: ZVecSchema normal use works\n";

    // ZVecIndexParams — create and use
    $p = ZVecIndexParams::forHnsw(ZVecSchema::METRIC_IP, 16, 200);
    $p = null;
    echo "OK 9: ZVecIndexParams normal use works\n";

    // ZVecVectorQuery — create and use
    $q = new ZVecVectorQuery('v', [0.1, 0.2, 0.3, 0.4]);
    $q = null;
    echo "OK 10: ZVecVectorQuery normal use works\n";

    // ZVecCollectionStats — create and use
    $st = $c->getStatsStruct();
    $count = $st->getDocCount();
    $st = null;
    echo "OK 11: ZVecCollectionStats normal use works (docCount=$count)\n";

    // ZVecFieldSchema — create and use
    $fs = $c->getFieldSchema('category');
    $name = $fs->getName();
    $fs = null;
    echo "OK 12: ZVecFieldSchema normal use works (name=$name)\n";

    // ZVecDoc — create and use
    $d = new ZVecDoc('normal_doc');
    $d->setInt64('category', 42);
    $d = null;
    echo "OK 13: ZVecDoc normal use works\n";

    $c->close();

    if ($allOk) {
        echo "PASS: All clone-safety tests passed\n";
    }
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
OK 1: ZVecSchema clone blocked - Call to private ZVecSchema::__clone() from global scope
OK 2: ZVecIndexParams clone blocked - Call to private ZVecIndexParams::__clone() from global scope
OK 3: ZVecVectorQuery clone blocked - Call to private ZVecVectorQuery::__clone() from global scope
OK 4: ZVecCollectionStats clone blocked - Call to private ZVecCollectionStats::__clone() from global scope
OK 5: ZVecFieldSchema clone blocked - Call to private ZVecFieldSchema::__clone() from global scope
OK 6: ZVecDoc clone blocked - Call to private ZVecDoc::__clone() from global scope
OK 7: ZVec clone blocked - Call to private ZVec::__clone() from global scope
OK 8: ZVecSchema normal use works
OK 9: ZVecIndexParams normal use works
OK 10: ZVecVectorQuery normal use works
OK 11: ZVecCollectionStats normal use works (docCount=1)
OK 12: ZVecFieldSchema normal use works (name=category)
OK 13: ZVecDoc normal use works
PASS: All clone-safety tests passed
