--TEST--
Bug 0001: Column DDL after delete causes "recovery delete store failed" on reopen (FIXED)
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

$path = __DIR__ . '/../test_dbs/bug_0001_' . uniqid();

try {
    $schema = new ZVecSchema('bug0001');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addFloat('weight', nullable: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    for ($i = 1; $i <= 3; $i++) {
        $doc = new ZVecDoc("d$i");
        $doc->setInt64('id', $i)
            ->setFloat('weight', $i * 1.0)
            ->setVectorFp32('v', [0.1 * $i, 0.2 * $i, 0.3 * $i, 0.4 * $i]);
        $c->insert($doc);
    }
    $c->optimize();

    $c->delete('d3');
    $c->addColumnInt64('extra', nullable: true, defaultExpr: '0');
    $c->optimize();
    $c->flush();
    $c->close();

    // Reopen should work if bug is fixed
    $c = ZVec::open($path, readOnly: true);
    $fetched = $c->fetch('d1');
    
    if (count($fetched) === 1) {
        echo "PASS: bug_0001 - column DDL after delete, reopen works\n";
    } else {
        echo "FAIL: bug_0001 - Expected 1 doc after reopen, got " . count($fetched) . "\n";
        exit(1);
    }
    
    $c->close();
} catch (ZVecException $e) {
    echo "FAIL: bug_0001 - " . $e->getMessage() . "\n";
    exit(1);
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
PASS: bug_0001 - column DDL after delete, reopen works
