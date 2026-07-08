<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';

$path = __DIR__ . '/../test_dbs/docenh_' . uniqid();

$schema = new ZVecSchema('test');
$schema->setMaxDocCountPerSegment(1000)
    ->addInt64('id', nullable: false)
    ->addString('name', nullable: true)
    ->addFloat('score')
    ->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);

$c = ZVec::create($path, $schema);
$c->createHnswIndex('vec');

echo "=== setFieldNull / isFieldNull ===\n";
$doc = new ZVecDoc('d1');
$doc->setInt64('id', 1);
$doc->setString('name', 'hello');
$doc->setFloat('score', 9.5);
$doc->setVectorFp32('vec', [0.1, 0.2, 0.3, 0.4]);

echo "before null: isFieldNull('name')=" . ($doc->isFieldNull('name') ? '1' : '0') . "\n";
$doc->setFieldNull('name');
echo "after null: isFieldNull('name')=" . ($doc->isFieldNull('name') ? '1' : '0') . "\n";
echo "hasField('name')=" . ($doc->hasField('name') ? '1' : '0') . "\n";

$c->insert($doc);
$c->optimize();
$fetched = $c->fetch('d1')[0];
echo "retrieved: hasField('name')=" . ($fetched->hasField('name') ? '1' : '0') . "\n";

echo "\n=== removeField ===\n";
$doc2 = new ZVecDoc('d2');
$doc2->setInt64('id', 2);
$doc2->setString('name', 'remove_me');
$doc2->setFloat('score', 8.0);
echo "before remove: hasField('name')=" . ($doc2->hasField('name') ? '1' : '0') . "\n";
$doc2->removeField('name');
echo "after remove: hasField('name')=" . ($doc2->hasField('name') ? '1' : '0') . "\n";

echo "\n=== merge ===\n";
$doc3a = new ZVecDoc('d3');
$doc3a->setInt64('id', 3);
$doc3a->setFloat('score', 7.5);

$doc3b = new ZVecDoc('d3');
$doc3b->setString('name', 'merged_doc');
$doc3b->setVectorFp32('vec', [0.5, 0.6, 0.7, 0.8]);

$doc3a->merge($doc3b);
echo "after merge: getInt64('id')=" . ($doc3a->getInt64('id') ?? 'null') . "\n";
echo "after merge: getString('name')=" . ($doc3a->getString('name') ?? 'null') . "\n";
echo "after merge: getFloat('score')=" . ($doc3a->getFloat('score') ?? 'null') . "\n";

$c->insert($doc3a);
$c->optimize();
$fetched3 = $c->fetch('d3')[0];
echo "retrieved merge: getInt64('id')=" . ($fetched3->getInt64('id') ?? 'null') . "\n";
echo "retrieved merge: getString('name')=" . ($fetched3->getString('name') ?? 'null') . "\n";

echo "\n=== serialize / deserialize ===\n";
$bin = $doc->serialize();
echo "serialize len: " . strlen($bin) . "\n";
echo "serialize not empty: " . (strlen($bin) > 0 ? '1' : '0') . "\n";

$restored = ZVecDoc::deserialize($bin);
echo "deserialize pk: " . $restored->getPk() . "\n";
echo "deserialize getInt64('id'): " . ($restored->getInt64('id') ?? 'null') . "\n";
echo "deserialize getString('name'): " . ($restored->getString('name') ?? 'null') . "\n";
echo "deserialize getFloat('score'): " . ($restored->getFloat('score') ?? 'null') . "\n";
$restoredVec = $restored->getVectorFp32('vec');
echo "deserialize getVectorFp32: [" . implode(', ', array_map(fn($v) => round($v, 1), $restoredVec ?? [])) . "]\n";

echo "\n=== isEmpty / clear ===\n";
$empty = new ZVecDoc('empty_test');
echo "new empty: isEmpty=" . ($empty->isEmpty() ? '1' : '0') . "\n";

$doc->clear();
echo "after clear: isEmpty=" . ($doc->isEmpty() ? '1' : '0') . "\n";
echo "after clear: hasField('id')=" . ($doc->hasField('id') ? '1' : '0') . "\n";

echo "\n=== memoryUsage ===\n";
$memDoc = new ZVecDoc('mem_test');
$memDoc->setInt64('id', 100);
$memDoc->setString('name', 'memory_test_doc');
echo "memoryUsage > 0: " . ($memDoc->getMemoryUsage() > 0 ? '1' : '0') . "\n";

echo "\n=== docOperator ===\n";
$opDoc = new ZVecDoc('op_test');
$opDoc->setInt64('id', 99);
echo "default operator: " . $opDoc->getOperator() . " (expected: 0=INSERT)\n";
$opDoc->setOperator(ZVecDoc::OP_UPDATE);
echo "after set UPDATE: " . $opDoc->getOperator() . " (expected: 1=UPDATE)\n";
$opDoc->setOperator(ZVecDoc::OP_UPSERT);
echo "after set UPSERT: " . $opDoc->getOperator() . " (expected: 2=UPSERT)\n";
$opDoc->setOperator(ZVecDoc::OP_DELETE);
echo "after set DELETE: " . $opDoc->getOperator() . " (expected: 3=DELETE)\n";

echo "\n=== Constants ===\n";
echo "OP_INSERT=" . ZVecDoc::OP_INSERT . "\n";
echo "OP_UPDATE=" . ZVecDoc::OP_UPDATE . "\n";
echo "OP_UPSERT=" . ZVecDoc::OP_UPSERT . "\n";
echo "OP_DELETE=" . ZVecDoc::OP_DELETE . "\n";

// Cleanup
$c->destroy();

exec("rm -rf " . escapeshellarg($path));

echo "\nPASS: All enhanced doc API tests passed\n";
?>
