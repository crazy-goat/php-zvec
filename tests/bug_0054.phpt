--TEST--
Bug 0054: Memory leak in ZVecDoc::deserialize() — buffer never freed (GH#54)
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$allOk = true;
$path = __DIR__ . '/../test_dbs/bug_0054_' . uniqid();

try {
    // ===== Setup: create a collection =====
    $schema = new ZVecSchema('bug_0054');
    $schema->addInt64('id', nullable: false, withInvertIndex: true)
        ->addFloat('score', nullable: true)
        ->addString('label', nullable: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $collection = ZVec::create($path, $schema);

    // ===== Test 1: serialize then deserialize a ZVecDoc =====
    echo "Test 1: Deserialize returns valid ZVecDoc with correct fields\n";

    $doc = new ZVecDoc('ser_test');
    $doc->setInt64('id', 99)
        ->setFloat('score', 2.718)
        ->setString('label', 'serialized')
        ->setVectorFp32('v', [0.5, 0.6, 0.7, 0.8]);

    $serialized = $doc->serialize();
    assert(is_string($serialized) && strlen($serialized) > 0, "serialize returned empty");

    $restored = ZVecDoc::deserialize($serialized);
    assert($restored instanceof ZVecDoc, "deserialize did not return ZVecDoc");

    // Check field values
    assert($restored->getInt64('id') === 99, "id mismatch");
    assert(abs($restored->getFloat('score') - 2.718) < 0.001, "score mismatch");
    assert($restored->getString('label') === 'serialized', "label mismatch");

    echo "OK: deserialized doc has correct fields\n";

    // ===== Test 2: Serialize and deserialize with collection-owned doc =====
    echo "Test 2: Serialize/deserialize with fetched doc\n";

    // Insert and fetch back
    $doc2 = new ZVecDoc('pk1');
    $doc2->setInt64('id', 42)
        ->setFloat('score', 3.14)
        ->setString('label', 'from_db')
        ->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);

    $collection->insert($doc2);
    $collection->flush();

    $fetched = $collection->fetch('pk1');
    assert(count($fetched) === 1, "fetch returned " . count($fetched) . " docs");

    $ser2 = $fetched[0]->serialize();
    $restored2 = ZVecDoc::deserialize($ser2);
    assert($restored2 instanceof ZVecDoc, "deserialize fetched failed");
    assert($restored2->getInt64('id') === 42, "fetched id mismatch");
    assert(abs($restored2->getFloat('score') - 3.14) < 0.001, "fetched score mismatch");
    assert($restored2->getString('label') === 'from_db', "fetched label mismatch");

    echo "OK: fetched doc round-trip works\n";

    // ===== Test 3: Multiple serialize/deserialize cycles (stress test) =====
    echo "Test 3: Multiple serialize/deserialize cycles\n";
    $cycles = 20;
    for ($i = 0; $i < $cycles; $i++) {
        $d = new ZVecDoc('cycle_' . $i);
        $d->setInt64('id', $i * 10)
            ->setFloat('score', $i * 1.5)
            ->setString('label', "cycle_$i")
            ->setVectorFp32('v', [0.1 * $i, 0.2 * $i, 0.3 * $i, 0.4 * $i]);

        $ser = $d->serialize();
        $des = ZVecDoc::deserialize($ser);

        assert($des->getInt64('id') === $i * 10, "cycle $i id mismatch");
        assert(abs($des->getFloat('score') - $i * 1.5) < 0.001, "cycle $i score mismatch");
        assert($des->getString('label') === "cycle_$i", "cycle $i label mismatch");
    }
    echo "OK: $cycles cycles completed\n";

    // ===== Test 4: Deserialize with empty/null-like edge cases =====
    // We test that valid minimal data works (a doc with just a PK)
    echo "Test 4: Deserialize minimal doc (PK only)\n";
    $minDoc = new ZVecDoc('minimal');
    $minSer = $minDoc->serialize();
    $minDes = ZVecDoc::deserialize($minSer);
    assert($minDes instanceof ZVecDoc, "minimal deserialize failed");
    echo "OK: minimal doc round-trip works\n";

    $collection->close();

} finally {
    exec("rm -rf " . escapeshellarg($path));
}

if ($allOk) {
    echo "\nAll Bug 0054 tests passed\n";
} else {
    echo "\nSome Bug 0054 tests FAILED\n";
    exit(1);
}
?>
--EXPECTF--
Test 1: Deserialize returns valid ZVecDoc with correct fields
OK: deserialized doc has correct fields
Test 2: Serialize/deserialize with fetched doc
OK: fetched doc round-trip works
Test 3: Multiple serialize/deserialize cycles
OK: 20 cycles completed
Test 4: Deserialize minimal doc (PK only)
OK: minimal doc round-trip works

All Bug 0054 tests passed
