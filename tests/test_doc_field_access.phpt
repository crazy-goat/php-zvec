--TEST--
Doc field access: set/get Int64, Float, Double, String, Vector Fp32
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

$path = __DIR__ . '/../test_dbs/doc_field_access_' . uniqid();

$schema = new ZVecSchema('test');
$schema->setMaxDocCountPerSegment(1000)
    ->addInt64('id', nullable: false)
    ->addFloat('weight')
    ->addDouble('precision')
    ->addString('name', nullable: false)
    ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

$c = ZVec::create($path, $schema);

// Create a doc with various fields
$doc = new ZVecDoc('doc_1');
$doc->setInt64('id', 42)
    ->setFloat('weight', 1.5)
    ->setDouble('precision', 3.14159265359)
    ->setString('name', 'Test Document')
    ->setVectorFp32('embedding', [0.1, 0.2, 0.3, 0.4]);

echo "=== Testing Field Access on Created Doc ===\n\n";

// Test getInt64
echo "getInt64 tests:\n";
$id = $doc->getInt64('id');
echo "  getInt64('id'): " . ($id === null ? 'null' : $id) . " (expected: 42)\n";
$missing = $doc->getInt64('nonexistent');
echo "  getInt64('nonexistent'): " . ($missing === null ? 'null' : $missing) . " (expected: null)\n";

// Test getFloat
echo "\ngetFloat tests:\n";
$weight = $doc->getFloat('weight');
echo "  getFloat('weight'): " . ($weight === null ? 'null' : round($weight, 2)) . " (expected: 1.5)\n";
$missing = $doc->getFloat('nonexistent');
echo "  getFloat('nonexistent'): " . ($missing === null ? 'null' : $missing) . " (expected: null)\n";

// Test getDouble
echo "\ngetDouble tests:\n";
$precision = $doc->getDouble('precision');
echo "  getDouble('precision'): " . ($precision === null ? 'null' : round($precision, 11)) . " (expected: 3.14159265359)\n";
$missing = $doc->getDouble('nonexistent');
echo "  getDouble('nonexistent'): " . ($missing === null ? 'null' : $missing) . " (expected: null)\n";

// Test getString
echo "\ngetString tests:\n";
$name = $doc->getString('name');
echo "  getString('name'): " . ($name === null ? 'null' : $name) . " (expected: Test Document)\n";
$missing = $doc->getString('nonexistent');
echo "  getString('nonexistent'): " . ($missing === null ? 'null' : $missing) . " (expected: null)\n";

// Test getVectorFp32
echo "\ngetVectorFp32 tests:\n";
$vec = $doc->getVectorFp32('embedding');
if ($vec === null) {
    echo "  getVectorFp32('embedding'): null (expected: [0.1, 0.2, 0.3, 0.4])\n";
} else {
    echo "  getVectorFp32('embedding'): [" . implode(', ', array_map(fn($v) => round($v, 1), $vec)) . "] (expected: [0.1, 0.2, 0.3, 0.4])\n";
}
$missing = $doc->getVectorFp32('nonexistent');
echo "  getVectorFp32('nonexistent'): " . ($missing === null ? 'null' : 'array') . " (expected: null)\n";

// Insert and fetch to test on retrieved doc
$c->insert($doc);
$c->optimize();

$retrieved = $c->fetch('doc_1')[0] ?? null;
if ($retrieved) {
    echo "\n=== Testing Field Access on Retrieved Doc ===\n\n";
    
    $id = $retrieved->getInt64('id');
    echo "getInt64('id'): " . ($id === null ? 'null' : $id) . " (expected: 42)\n";
    
    $weight = $retrieved->getFloat('weight');
    echo "getFloat('weight'): " . ($weight === null ? 'null' : round($weight, 2)) . " (expected: 1.5)\n";
    
    $precision = $retrieved->getDouble('precision');
    echo "getDouble('precision'): " . ($precision === null ? 'null' : round($precision, 11)) . " (expected: 3.14159265359)\n";
    
    $name = $retrieved->getString('name');
    echo "getString('name'): " . ($name === null ? 'null' : $name) . " (expected: Test Document)\n";
    
    $vec = $retrieved->getVectorFp32('embedding');
    if ($vec === null) {
        echo "getVectorFp32('embedding'): null (expected: [0.1, 0.2, 0.3, 0.4])\n";
    } else {
        echo "getVectorFp32('embedding'): [" . implode(', ', array_map(fn($v) => round($v, 1), $vec)) . "] (expected: [0.1, 0.2, 0.3, 0.4])\n";
    }
}

// Test type coercion - accessing field with wrong type getter returns null
echo "\n=== Testing Type Coercion ===\n\n";
$wrongType1 = $doc->getFloat('id'); // Int64 field accessed as Float
echo "getFloat('id') on Int64 field: " . ($wrongType1 === null ? 'null' : round($wrongType1, 2)) . " (expected: null - wrong type)\n";

$wrongType2 = $doc->getInt64('name'); // String field accessed as Int64
echo "getInt64('name') on String field: " . ($wrongType2 === null ? 'null' : $wrongType2) . " (expected: null - wrong type)\n";

// Cleanup
$c->close();
exec("rm -rf " . escapeshellarg($path));

// Verify results
$ok = true;

// Check created doc values
if ($doc->getInt64('id') !== 42) { echo "\nFAIL: getInt64('id') should be 42\n"; $ok = false; }
if (abs($doc->getFloat('weight') - 1.5) > 0.001) { echo "\nFAIL: getFloat('weight') should be ~1.5\n"; $ok = false; }
if (abs($doc->getDouble('precision') - 3.14159265359) > 0.0001) { echo "\nFAIL: getDouble('precision') should be ~3.14159265359\n"; $ok = false; }
if ($doc->getString('name') !== 'Test Document') { echo "\nFAIL: getString('name') should be 'Test Document'\n"; $ok = false; }

// Check non-existent fields return null
if ($doc->getInt64('nonexistent') !== null) { echo "\nFAIL: getInt64('nonexistent') should be null\n"; $ok = false; }
if ($doc->getFloat('nonexistent') !== null) { echo "\nFAIL: getFloat('nonexistent') should be null\n"; $ok = false; }
if ($doc->getDouble('nonexistent') !== null) { echo "\nFAIL: getDouble('nonexistent') should be null\n"; $ok = false; }
if ($doc->getString('nonexistent') !== null) { echo "\nFAIL: getString('nonexistent') should be null\n"; $ok = false; }
if ($doc->getVectorFp32('nonexistent') !== null) { echo "\nFAIL: getVectorFp32('nonexistent') should be null\n"; $ok = false; }

// Check wrong type access returns null
if ($doc->getFloat('id') !== null) { echo "\nFAIL: getFloat('id') on Int64 field should return null\n"; $ok = false; }
if ($doc->getInt64('name') !== null) { echo "\nFAIL: getInt64('name') on String field should return null\n"; $ok = false; }

if ($ok) {
    echo "\nPASS: All field access methods work correctly\n";
} else {
    echo "\nFAIL: Some tests failed\n";
    exit(1);
}
?>
--EXPECT--
=== Testing Field Access on Created Doc ===

getInt64 tests:
  getInt64('id'): 42 (expected: 42)
  getInt64('nonexistent'): null (expected: null)

getFloat tests:
  getFloat('weight'): 1.5 (expected: 1.5)
  getFloat('nonexistent'): null (expected: null)

getDouble tests:
  getDouble('precision'): 3.14159265359 (expected: 3.14159265359)
  getDouble('nonexistent'): null (expected: null)

getString tests:
  getString('name'): Test Document (expected: Test Document)
  getString('nonexistent'): null (expected: null)

getVectorFp32 tests:
  getVectorFp32('embedding'): [0.1, 0.2, 0.3, 0.4] (expected: [0.1, 0.2, 0.3, 0.4])
  getVectorFp32('nonexistent'): null (expected: null)

=== Testing Field Access on Retrieved Doc ===

getInt64('id'): 42 (expected: 42)
getFloat('weight'): 1.5 (expected: 1.5)
getDouble('precision'): 3.14159265359 (expected: 3.14159265359)
getString('name'): Test Document (expected: Test Document)
getVectorFp32('embedding'): [0.1, 0.2, 0.3, 0.4] (expected: [0.1, 0.2, 0.3, 0.4])

=== Testing Type Coercion ===

getFloat('id') on Int64 field: null (expected: null - wrong type)
getInt64('name') on String field: null (expected: null - wrong type)

PASS: All field access methods work correctly
