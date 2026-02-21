<?php
/**
 * Bug 0001: Column DDL after delete causes "recovery delete store failed" on reopen.
 *
 * When column DDL (addColumn/renameColumn/dropColumn) runs after delete operations
 * without a flush() in between, zvec's internal delete store numbering gets out of sync.
 * The del.N file referenced by the manifest doesn't exist on disk, causing reopen to fail.
 *
 * Minimal reproduction: delete() -> addColumn() -> close() -> open() = FAIL
 * Fix: Flush() before every column DDL operation (built into C wrapper).
 */

require_once __DIR__ . '/../php/ZVec.php';

$path = __DIR__ . '/../test_bug_0001';
if (is_dir($path)) exec("rm -rf " . escapeshellarg($path));

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

try {
    $c = ZVec::open($path, readOnly: true);
    $fetched = $c->fetch('d1');
    assert(count($fetched) === 1, 'Expected 1 doc after reopen');
    $c->close();
    echo "PASS: bug_0001 - column DDL after delete, reopen works\n";
} catch (ZVecException $e) {
    echo "FAIL: bug_0001 - " . $e->getMessage() . "\n";
    exit(1);
}

exec("rm -rf " . escapeshellarg($path));
