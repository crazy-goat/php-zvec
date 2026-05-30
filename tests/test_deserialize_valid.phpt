--TEST--
ZVecDoc::deserialize() round-trip preserves all fields
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

$doc = new ZVecDoc('pk1');
$doc->setInt64('int_field', 42)
    ->setString('str_field', 'hello world')
    ->setFloat('float_field', 3.14)
    ->setDouble('double_field', 2.718281828)
    ->setBool('bool_field', true)
    ->setVectorFp32('vec_field', [0.1, 0.2, 0.3]);

$serialized = $doc->serialize();
$doc2 = ZVecDoc::deserialize($serialized);

$ok = true;

if ($doc2->getPk() !== 'pk1') {
    echo "FAIL: pk mismatch\n";
    $ok = false;
}
if ($doc2->getInt64('int_field') !== 42) {
    echo "FAIL: int64 mismatch\n";
    $ok = false;
}
if ($doc2->getString('str_field') !== 'hello world') {
    echo "FAIL: string mismatch\n";
    $ok = false;
}
if (abs($doc2->getFloat('float_field') - 3.14) > 0.001) {
    echo "FAIL: float mismatch\n";
    $ok = false;
}
if (abs($doc2->getDouble('double_field') - 2.718281828) > 1e-6) {
    echo "FAIL: double mismatch\n";
    $ok = false;
}
if ($doc2->getBool('bool_field') !== true) {
    echo "FAIL: bool mismatch\n";
    $ok = false;
}
$vec = $doc2->getVectorFp32('vec_field');
if ($vec === null || count($vec) !== 3 || abs($vec[0] - 0.1) > 1e-6) {
    echo "FAIL: vector mismatch\n";
    $ok = false;
}

if ($ok) {
    echo "PASS: round-trip preserves all fields\n";
}
?>
--EXPECT--
PASS: round-trip preserves all fields
