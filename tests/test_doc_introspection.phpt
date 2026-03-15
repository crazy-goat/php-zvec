--TEST--
Doc introspection: hasField, hasVector, fieldNames, vectorNames
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

$path = __DIR__ . '/../test_dbs/doc_introspection_' . uniqid();

$schema = new ZVecSchema('test');
$schema->setMaxDocCountPerSegment(1000)
    ->addInt64('id', nullable: false, withInvertIndex: true)
    ->addString('name', nullable: false)
    ->addFloat('weight')
    ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

$c = ZVec::create($path, $schema);

// Create a doc with various fields
$doc = new ZVecDoc('doc_1');
$doc->setInt64('id', 42)
    ->setString('name', 'Test Document')
    ->setFloat('weight', 1.5)
    ->setVectorFp32('embedding', [0.1, 0.2, 0.3, 0.4]);

echo "=== Testing Doc Introspection ===\n\n";

// Test hasField
echo "hasField tests:\n";
echo "  hasField('id'): " . ($doc->hasField('id') ? 'true' : 'false') . " (expected: true)\n";
echo "  hasField('name'): " . ($doc->hasField('name') ? 'true' : 'false') . " (expected: true)\n";
echo "  hasField('weight'): " . ($doc->hasField('weight') ? 'true' : 'false') . " (expected: true)\n";
echo "  hasField('embedding'): " . ($doc->hasField('embedding') ? 'true' : 'false') . " (expected: true)\n";
echo "  hasField('nonexistent'): " . ($doc->hasField('nonexistent') ? 'true' : 'false') . " (expected: false)\n";

// Test hasVector
echo "\nhasVector tests:\n";
echo "  hasVector('embedding'): " . ($doc->hasVector('embedding') ? 'true' : 'false') . " (expected: true)\n";
echo "  hasVector('id'): " . ($doc->hasVector('id') ? 'true' : 'false') . " (expected: false)\n";
echo "  hasVector('name'): " . ($doc->hasVector('name') ? 'true' : 'false') . " (expected: false)\n";
echo "  hasVector('nonexistent'): " . ($doc->hasVector('nonexistent') ? 'true' : 'false') . " (expected: false)\n";

// Test fieldNames
echo "\nfieldNames():\n";
$fieldNames = $doc->fieldNames();
sort($fieldNames);
echo "  Count: " . count($fieldNames) . " (expected: 3 - id, name, weight)\n";
echo "  Fields: " . implode(', ', $fieldNames) . "\n";

// Test vectorNames
echo "\nvectorNames():\n";
$vectorNames = $doc->vectorNames();
echo "  Count: " . count($vectorNames) . " (expected: 1 - embedding)\n";
echo "  Vectors: " . implode(', ', $vectorNames) . "\n";

// Insert and fetch to test on retrieved doc
$c->insert($doc);
$c->optimize();

$retrieved = $c->fetch('doc_1')[0] ?? null;
if ($retrieved) {
    echo "\n=== Testing on retrieved doc ===\n";
    echo "hasField('name'): " . ($retrieved->hasField('name') ? 'true' : 'false') . "\n";
    echo "hasVector('embedding'): " . ($retrieved->hasVector('embedding') ? 'true' : 'false') . "\n";
    $rFieldNames = $retrieved->fieldNames();
    sort($rFieldNames);
    echo "fieldNames(): " . implode(', ', $rFieldNames) . "\n";
    echo "vectorNames(): " . implode(', ', $retrieved->vectorNames()) . "\n";
}

// Cleanup
$c->close();
exec("rm -rf " . escapeshellarg($path));

// Verify
$ok = true;
if (!$doc->hasField('id')) { echo "\nFAIL: hasField('id') should be true\n"; $ok = false; }
if (!$doc->hasField('name')) { echo "FAIL: hasField('name') should be true\n"; $ok = false; }
if ($doc->hasField('nonexistent')) { echo "FAIL: hasField('nonexistent') should be false\n"; $ok = false; }
if (!$doc->hasVector('embedding')) { echo "FAIL: hasVector('embedding') should be true\n"; $ok = false; }
if ($doc->hasVector('id')) { echo "FAIL: hasVector('id') should be false\n"; $ok = false; }
if (count($fieldNames) !== 3) { echo "FAIL: fieldNames() should return 3 fields\n"; $ok = false; }
if (count($vectorNames) !== 1) { echo "FAIL: vectorNames() should return 1 vector\n"; $ok = false; }

if ($ok) {
    echo "\nPASS: All introspection methods work correctly\n";
} else {
    echo "\nFAIL: Some tests failed\n";
    exit(1);
}
?>
--EXPECT--
=== Testing Doc Introspection ===

hasField tests:
  hasField('id'): true (expected: true)
  hasField('name'): true (expected: true)
  hasField('weight'): true (expected: true)
  hasField('embedding'): true (expected: true)
  hasField('nonexistent'): false (expected: false)

hasVector tests:
  hasVector('embedding'): true (expected: true)
  hasVector('id'): false (expected: false)
  hasVector('name'): false (expected: false)
  hasVector('nonexistent'): false (expected: false)

fieldNames():
  Count: 3 (expected: 3 - id, name, weight)
  Fields: id, name, weight

vectorNames():
  Count: 1 (expected: 1 - embedding)
  Vectors: embedding

=== Testing on retrieved doc ===
hasField('name'): true
hasVector('embedding'): true
fieldNames(): id, name, weight
vectorNames(): embedding

PASS: All introspection methods work correctly
