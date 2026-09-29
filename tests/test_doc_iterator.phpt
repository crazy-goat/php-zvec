--TEST--
DocIterator: iterDocs() full scan, options, snapshot, deletes, validation
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/iter_' . uniqid();

$schema = new ZVecSchema('iter_test');
$schema->addInt64('id', nullable: false, withInvertIndex: true)
    ->addString('name', nullable: false)
    ->addFloat('weight', nullable: true)
    ->addVectorFp32('dense', dimension: 4, metricType: ZVecSchema::METRIC_IP);

/** @return ZVecDoc[] */
function makeDocs(int $n, string $prefix = ''): array
{
    $docs = [];
    for ($i = 0; $i < $n; $i++) {
        $d = new ZVecDoc($prefix . $i);
        $d->setInt64('id', $i)
            ->setString('name', "name_$i")
            ->setVectorFp32('dense', [(float)$i, 0.0, 0.0, 1.0]);
        if ($i % 2 === 0) {
            $d->setFloat('weight', (float)$i);
        }
        $docs[] = $d;
    }
    return $docs;
}

try {
    $c = ZVec::create($path, $schema);
    echo 'empty: ' . count(iterator_to_array($c->iterDocs())) . "\n";

    $c->insert(...makeDocs(50));
    $c->flush();

    // Document order within a segment is not part of the contract, so sort.
    $pks = [];
    $fieldsOk = true;
    foreach ($c->iterDocs() as $pk => $doc) {
        $pks[] = $pk;
        if ($pk !== $doc->getPk() || $doc->getInt64('id') !== (int)$pk || $doc->getString('name') !== "name_$pk") {
            $fieldsOk = false;
        }
    }
    sort($pks);
    echo 'basic: count=' . count($pks) . ' unique=' . count(array_unique($pks))
        . ' fields_ok=' . ($fieldsOk ? 'yes' : 'no') . "\n";

    $allVectors = true;
    foreach ($c->iterDocs() as $doc) {
        if (count($doc->getVectorFp32('dense')) !== 4) {
            $allVectors = false;
        }
    }
    echo 'vectors: ' . ($allVectors ? 'yes' : 'no') . "\n";

    $noVectors = true;
    foreach ($c->iterDocs(includeVector: false) as $doc) {
        if ($doc->hasVector('dense') || $doc->getInt64('id') === null) {
            $noVectors = false;
        }
    }
    echo 'no vectors: ' . ($noVectors ? 'yes' : 'no') . "\n";

    $outputOk = true;
    foreach ($c->iterDocs(outputFields: ['id'], includeVector: false) as $doc) {
        if (!$doc->hasField('id') || $doc->hasField('name') || $doc->hasField('weight')) {
            $outputOk = false;
        }
    }
    echo 'output fields: ' . ($outputOk ? 'yes' : 'no') . "\n";

    // An empty list means "primary key only" upstream.
    $pkOnly = true;
    $pkOnlyCount = 0;
    foreach ($c->iterDocs(outputFields: [], includeVector: false) as $doc) {
        $pkOnlyCount++;
        if ($doc->fieldNames() !== []) {
            $pkOnly = false;
        }
    }
    echo 'pk only: ' . $pkOnlyCount . ($pkOnly ? '' : ' FAILED') . "\n";

    // Since #192 fetch() reports an absent nullable field as present-and-null;
    // iteration must agree, but only for the fields actually requested.
    $nullableOk = true;
    foreach ($c->iterDocs() as $pk => $doc) {
        if ($doc->getPk() === '1' && (!($doc->isFieldNull('weight')) || $doc->getFloat('weight') !== null)) {
            $nullableOk = false;
        }
        if ($doc->getPk() === '2' && $doc->getFloat('weight') !== 2.0) {
            $nullableOk = false;
        }
    }
    foreach ($c->iterDocs(outputFields: ['id'], includeVector: false) as $doc) {
        if ($doc->hasField('weight')) {
            $nullableOk = false;
        }
    }
    echo 'nullable: ' . ($nullableOk ? 'yes' : 'no') . "\n";

    $c->delete(...array_map('strval', range(0, 48, 2)));
    $c->flush();
    $afterDelete = [];
    foreach ($c->iterDocs() as $pk => $doc) {
        $afterDelete[] = (int)$pk;
    }
    echo 'after delete: ' . count($afterDelete)
        . (count(array_filter($afterDelete, static fn(int $v): bool => $v % 2 === 0)) === 0 ? '' : ' FAILED') . "\n";

    // The snapshot is taken when iterDocs() is called, so later writes are invisible.
    $snapshot = $c->iterDocs();
    $c->insert(...makeDocs(3, 'late_'));
    echo 'snapshot: ' . iterator_count($snapshot) . "\n";
    echo 'fresh: ' . count(iterator_to_array($c->iterDocs())) . "\n";

    // Exhaustion releases the native handle, so a completed foreach needs no close().
    echo 'auto closed: ' . ($snapshot->isClosed() ? 'yes' : 'no') . "\n";

    $early = $c->iterDocs();
    $early->rewind();
    $early->close();
    $early->close();
    echo 'early close: ' . (!$early->valid() && $early->isClosed() ? 'yes' : 'no') . "\n";

    $forward = $c->iterDocs();
    $forward->rewind();
    $forward->next();
    try {
        $forward->rewind();
        echo "rewind: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo 'rewind: ' . $e->getMessage() . "\n";
    }
    $forward->close();

    try {
        $c->iterDocs(outputFields: ['nope']);
        echo "unknown field: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo 'unknown field: ' . $e->getErrorCodeString() . "\n";
    }

    try {
        $c->iterDocs(outputFields: ['dense']);
        echo "vector field: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo 'vector field: ' . $e->getErrorCodeString() . "\n";
    }

    try {
        $c->iterDocs(outputFields: ['']);
        echo "PHP validation: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo 'PHP validation: ' . $e->getMessage() . "\n";
    }

    $c->close();
    try {
        $c->iterDocs();
        echo "closed collection: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo $e->getMessage() . "\n";
    }
} finally {
    exec('rm -rf ' . escapeshellarg($path));
}
?>
--EXPECT--
empty: 0
basic: count=50 unique=50 fields_ok=yes
vectors: yes
no vectors: yes
output fields: yes
pk only: 50
nullable: yes
after delete: 25
snapshot: 25
fresh: 28
auto closed: yes
early close: yes
rewind: ZVecDocIterator is forward-only and cannot be rewound; call ZVec::iterDocs() again
unknown field: INVALID_ARGUMENT
vector field: INVALID_ARGUMENT
PHP validation: outputFields must contain only non-empty strings
Collection is closed. Open with ZVec::open() to continue.
