--TEST--
Schema: declare an FTS or invert index on a STRING field at create time
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

/** @return string[] sorted PKs, since result order is not part of the contract */
function ftsPks(ZVec $collection, string $term): array
{
    $query = (new ZVecVectorQuery('body', []))->setTopk(10)->setFts('body', $term);
    $pks = array_map(static fn(ZVecDoc $d): string => $d->getPk(), $collection->queryVector($query));
    sort($pks);
    return $pks;
}

$path = __DIR__ . '/../test_dbs/schema_fts_' . uniqid();
$invertPath = __DIR__ . '/../test_dbs/schema_invert_' . uniqid();
$hnswPath = __DIR__ . '/../test_dbs/schema_hnsw_' . uniqid();

try {
    $schema = new ZVecSchema('schema_fts_test');
    $schema->addString('body', indexParams: ZVecIndexParams::forFts())
        ->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $collection = ZVec::create($path, $schema);

    foreach ([
        ['d1', 'the quick brown fox jumps'],
        ['d2', 'a fast brown rabbit'],
        ['d3', 'completely unrelated text about databases'],
    ] as [$pk, $body]) {
        $collection->insert(
            (new ZVecDoc($pk))->setString('body', $body)->setVectorFp32('vec', [1.0, 0.0, 0.0, 0.0])
        );
    }
    $collection->flush();

    // The index exists from the first insert: no createIndex() call anywhere.
    echo 'fts in schema: ' . implode(',', ftsPks($collection, 'fox')) . "\n";
    echo 'index type: ' . $collection->getFieldSchema('body')->getIndexType() . "\n";

    $collection->close();
    $collection = ZVec::open($path);
    echo 'after reopen: ' . implode(',', ftsPks($collection, 'fox')) . "\n";
    echo 'index type after reopen: ' . $collection->getFieldSchema('body')->getIndexType() . "\n";

    // Invert index declared the same way.
    $invertSchema = new ZVecSchema('schema_invert_test');
    $invertSchema->addString('tag', indexParams: ZVecIndexParams::forInvert())
        ->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $invertCollection = ZVec::create($invertPath, $invertSchema);
    echo 'invert type: ' . $invertCollection->getFieldSchema('tag')->getIndexType() . "\n";

    // Requesting both an invert flag and explicit params is ambiguous.
    try {
        (new ZVecSchema('schema_conflict'))->addString(
            'a',
            withInvertIndex: true,
            indexParams: ZVecIndexParams::forFts(),
        );
        echo "both flags: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo 'both flags: ' . $e->getMessage() . "\n";
    }

    // A duplicate field is reported here, unlike the other add*() methods which
    // drop the Status from add_field().
    try {
        $duplicate = new ZVecSchema('schema_duplicate');
        $duplicate->addString('body');
        $duplicate->addString('body', indexParams: ZVecIndexParams::forFts());
        echo "duplicate: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo 'duplicate: ' . (str_contains($e->getMessage(), 'already exists') ? 'rejected' : $e->getMessage()) . "\n";
    }

    // Vector index params on a scalar field are caught by upstream at create
    // time, which names the field and the type.
    $hnswSchema = new ZVecSchema('schema_hnsw_string');
    $hnswSchema->addString('body', indexParams: ZVecIndexParams::forHnsw(ZVecSchema::METRIC_IP))
        ->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    try {
        ZVec::create($hnswPath, $hnswSchema);
        echo "hnsw on string: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo 'hnsw on string: ' . (str_contains($e->getMessage(), 'does not support vector index params')
            ? 'rejected' : $e->getMessage()) . "\n";
    }

    // Backward compatibility of the pre-existing argument combinations.
    $bcSchema = new ZVecSchema('schema_bc');
    $bcSchema->addString('x')
        ->addString('x2', withInvertIndex: true)
        ->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $bcPath = __DIR__ . '/../test_dbs/schema_bc_' . uniqid();
    $bcCollection = ZVec::create($bcPath, $bcSchema);
    echo 'bc: ' . ($bcCollection->getFieldSchema('x2')->hasInvertIndex() ? 'ok' : 'FAILED') . "\n";
    $bcCollection->close();
    exec('rm -rf ' . escapeshellarg($bcPath));

    echo "PASS\n";
} finally {
    exec('rm -rf ' . escapeshellarg($path));
    exec('rm -rf ' . escapeshellarg($invertPath));
    exec('rm -rf ' . escapeshellarg($hnswPath));
}
?>
--EXPECT--
fts in schema: d1
index type: 11
after reopen: d1
index type after reopen: 11
invert type: 10
both flags: Use either $withInvertIndex or $indexParams, not both
duplicate: rejected
hnsw on string: rejected
bc: ok
PASS
