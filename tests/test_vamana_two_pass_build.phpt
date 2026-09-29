--TEST--
Vamana: two_pass_build index param reaches upstream and stays backward compatible
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

/** @return array{0: ZVec, 1: string} collection and its path */
function makeVamana(string $name, bool $twoPassBuild): array
{
    $path = __DIR__ . '/../test_dbs/' . $name . '_' . uniqid();
    $schema = new ZVecSchema($name);
    $schema->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $c = ZVec::create($path, $schema);
    $c->createIndex('v', ZVecIndexParams::forVamana(
        metricType: ZVecSchema::METRIC_IP,
        maxDegree: 32,
        searchListSize: 50,
        twoPassBuild: $twoPassBuild,
    ));
    foreach ([['doc1', [1.0, 0.0, 0.0, 0.0]], ['doc2', [0.9, 0.1, 0.0, 0.0]], ['doc3', [0.0, 1.0, 0.0, 0.0]]] as [$pk, $vec]) {
        $c->insert((new ZVecDoc($pk))->setVectorFp32('v', $vec));
    }
    $c->optimize();
    return [$c, $path];
}

[$c, $p] = makeVamana('vamana_tpb_test', true);
try {
    // Upstream's CollectionSchema::to_string() embeds each field's
    // index_params_->to_string(), which prints ",two_pass_build:true". That is the
    // only way to read an index param back, so assert on it rather than on
    // behaviour that 3 documents cannot distinguish.
    echo 'schema has two_pass_build:true: '
        . (str_contains($c->schema(), 'two_pass_build:true') ? 'yes' : 'no') . "\n";

    $q = (new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]))->setTopk(3);
    echo 'query: ' . implode(',', array_map(static fn(ZVecDoc $d): string => $d->getPk(), $c->queryVector($q))) . "\n";
} finally {
    exec('rm -rf ' . escapeshellarg($p));
}

[$c, $p] = makeVamana('vamana_tpb_default', false);
try {
    echo 'default schema has two_pass_build:false: '
        . (str_contains($c->schema(), 'two_pass_build:false') ? 'yes' : 'no') . "\n";
} finally {
    exec('rm -rf ' . escapeshellarg($p));
}

// The new parameter is last and optional, so the pre-existing positional form
// must still work.
ZVecIndexParams::forVamana(ZVecSchema::METRIC_IP, 32, 50, 1.2, false, false, false, ZVec::QUANTIZE_UNDEFINED);
echo "positional call ok\n";
echo "PASS\n";
?>
--EXPECT--
schema has two_pass_build:true: yes
query: doc1,doc2,doc3
default schema has two_pass_build:false: yes
positional call ok
PASS
