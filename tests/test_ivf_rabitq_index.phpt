--TEST--
IVF-RaBitQ index: create, query via queryVector() and the legacy query() path
--CAPTURE_STDIO--
STDOUT
--DESCRIPTION--
Building a RaBitQ index makes the bundled rabitqlib print "FhtKacRotator is
selected" on stderr from a C++ static initialiser. CAPTURE_STDIO STDOUT keeps
that chatter out of the compared output; it cannot be silenced from PHP.
--SKIPIF--
<?php
if (!extension_loaded('ffi')) die('skip FFI extension not available');
// Upstream supports RaBitQ on Linux x86_64 with AVX2/FMA or AVX-512 only; the
// dimension 64-4095 and metric rules are platform-independent, so this test
// only makes sense where the index can actually be created.
if (PHP_OS_FAMILY !== 'Linux' || php_uname('m') !== 'x86_64') {
    die('skip RaBitQ is supported on Linux x86_64 only');
}
?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/ivf_rabitq_' . uniqid();
try {
    $schema = new ZVecSchema('ivf_rabitq_test');
    $schema->addVectorFp32('v', dimension: 128, metricType: ZVecSchema::METRIC_L2);
    $c = ZVec::create($path, $schema);
    $c->createIndex('v', ZVecIndexParams::forIvfRabitq(metricType: ZVecSchema::METRIC_L2, nList: 16));

    $docCount = 64;
    for ($i = 0; $i < $docCount; $i++) {
        $c->insert((new ZVecDoc((string) $i))->setVectorFp32('v', array_fill(0, 128, (float) $i)));
    }
    $c->optimize();

    echo 'index type: ' . $c->getFieldSchema('v')->getIndexType() . "\n";

    // RaBitQ is a lossy quantizer and IVF is approximate, so the exact top hit is
    // not guaranteed: measured 9/10 for the exact PK and a 1-off otherwise.
    // Assert the winner is in a small neighbourhood of the target instead, which
    // is what the index contract actually promises.
    $near = static fn(string $pk): bool => abs((int)$pk - 42) <= 1;

    $target = array_fill(0, 128, 42.0);
    $query = (new ZVecVectorQuery('v', $target))->setTopk(10)->setIvfRabitqParams(nprobe: 16);
    $hits = array_map(static fn(ZVecDoc $d): string => $d->getPk(), $c->queryVector($query));
    echo 'queryVector top: ' . ($near($hits[0]) ? 'near target' : 'WRONG: ' . $hits[0]) . "\n";

    // The legacy query() path goes through zvec_collection_query_ex, which needs
    // its own IVF_RABITQ branch in validate_query_param_type() and
    // apply_query_params() to work at all.
    $legacy = (new ZVecVectorQuery('v', $target))->setTopk(10)->setIvfRabitqParams(nprobe: 16);
    $legacyHits = array_map(static fn(ZVecDoc $d): string => $d->getPk(), $c->query($legacy));
    echo 'query top: ' . ($near($legacyHits[0]) ? 'near target' : 'WRONG: ' . $legacyHits[0]) . "\n";

    // Radius only, with no setIvfRabitqParams(): this goes through
    // ensure_query_params_for_field(), which without an IVF_RABITQ case would
    // fall back to HNSW params and be rejected as a type mismatch.
    $radiusOnly = (new ZVecVectorQuery('v', $target))->setTopk(10)->setRadius(1.0e9);
    $c->queryVector($radiusOnly);
    echo "radius-only query ok\n";

    // HNSW params on an IVF_RABITQ field must be rejected by the type check.
    try {
        $wrong = (new ZVecVectorQuery('v', $target))->setTopk(10)->setHnswParams(64);
        $c->queryVector($wrong);
        echo "hnsw params: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo 'hnsw params rejected (code ' . $e->getCode() . ")\n";
    }

    echo "PASS: IVF-RaBitQ index works\n";
} finally {
    exec('rm -rf ' . escapeshellarg($path));
}

// Upstream requires dimension >= 64 (kMinRabitqDimSize). Checked on a separate
// collection because the index above is already built on this one.
$shortPath = __DIR__ . '/../test_dbs/ivf_rabitq_dim_' . uniqid();
try {
    $shortSchema = new ZVecSchema('ivf_rabitq_dim_test');
    $shortSchema->addVectorFp32('v', dimension: 32, metricType: ZVecSchema::METRIC_L2);
    $shortCollection = ZVec::create($shortPath, $shortSchema);
    try {
        $shortCollection->createIndex('v', ZVecIndexParams::forIvfRabitq(
            metricType: ZVecSchema::METRIC_L2,
            nList: 16,
        ));
        echo "dim 32: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo 'dim 32 rejected (code ' . $e->getCode() . ")\n";
    }
} finally {
    exec('rm -rf ' . escapeshellarg($shortPath));
}
?>
--EXPECT--
index type: 7
queryVector top: near target
query top: near target
radius-only query ok
hnsw params rejected (code 3)
PASS: IVF-RaBitQ index works
dim 32 rejected (code 3)
