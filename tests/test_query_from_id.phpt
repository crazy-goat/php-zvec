--TEST--
ZVecVectorQuery::fromId(): works in query(), queryVector(), queryWithReranker() and groupByQuery()
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/query_from_id_' . uniqid();

try {
    $schema = new ZVecSchema('fromid_test');
    $schema->addString('category', nullable: true, withInvertIndex: true)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    foreach ([
        ['doc1', 'tech', [1.0, 0.0, 0.0, 0.0]],
        ['doc2', 'tech', [0.9, 0.1, 0.0, 0.0]],
        ['doc3', 'fin', [0.5, 0.5, 0.0, 0.0]],
        ['doc4', 'fin', [0.0, 1.0, 0.0, 0.0]],
    ] as [$pk, $category, $vec]) {
        $c->insert(
            (new ZVecDoc($pk))->setString('category', $category)->setVectorFp32('embedding', $vec)
        );
    }
    $c->optimize();

    // queryWithReranker() returns ZVecRerankedDoc, so the getter is read
    // untyped rather than through a ZVecDoc-typed closure.
    $pks = static fn(array $docs): string => implode(',', array_map(
        static fn(object $d): string => $d->getPk(),
        $docs
    ));

    // 1. The documented use case: find documents similar to a stored one.
    echo '1. query fromId: ' . $pks($c->query(ZVecVectorQuery::fromId('embedding', 'doc1')->setTopk(3))) . "\n";

    // 2. A filter still applies on top of the resolved vector.
    echo '2. filter: ' . $pks($c->query(
        ZVecVectorQuery::fromId('embedding', 'doc1')->setTopk(3)->setFilter("category = 'fin'")
    )) . "\n";

    // 3. queryVector() goes through a different C entry point, so it needs the
    //    vector written onto the native handle.
    $reused = ZVecVectorQuery::fromId('embedding', 'doc1')->setTopk(3);
    echo '3. queryVector fromId: ' . $pks($c->queryVector($reused)) . "\n";
    // 4. Same object twice: the vector is re-resolved on every call, and the
    //    object is not mutated.
    echo '4. reuse: ' . $pks($c->queryVector($reused)) . "\n";
    echo '5. query object unchanged: ' . ($reused->vector === [] ? 'yes' : 'NO') . "\n";

    // 6. queryMulti() routes through query(), so it works too.
    echo '6. reranker: ' . $pks($c->queryWithReranker(
        ZVecVectorQuery::fromId('embedding', 'doc1')->setTopk(3),
        reranker: new ZVecRrfReRanker(topn: 3, rankConstant: 60)
    )) . "\n";

    // 7. groupByQuery() builds only a float[] buffer, so it accepts fp32 and
    //    int8 but not fp64.
    $groups = $c->groupByQuery(
        ZVecVectorQuery::fromId('embedding', 'doc1'),
        [],
        'category',
        groupCount: 2,
        groupTopk: 2
    );
    usort($groups, static fn(array $a, array $b): int => strcmp($a['group_value'], $b['group_value']));
    foreach ($groups as $group) {
        echo '7. group ' . $group['group_value'] . ': ' . $pks($group['docs']) . "\n";
    }

    $expect = static function (string $label, callable $fn) use ($c): void {
        try {
            $fn();
            echo "$label: NOT REJECTED\n";
        } catch (ZVecException $e) {
            echo $label . ': ' . $e->getMessage() . "\n";
        }
    };

    $expect('8. missing document', static fn() => $c->query(ZVecVectorQuery::fromId('embedding', 'missing')));
    $expect('9. empty docId', static fn() => ZVecVectorQuery::fromId('embedding', ''));

    $both = ZVecVectorQuery::fromId('embedding', 'doc1');
    $both->vector = [1.0, 0.0, 0.0, 0.0];
    $expect('10. docId and vector', static fn() => $c->query($both));

    $expect('11. non-vector field', static fn() => $c->query(ZVecVectorQuery::fromId('category', 'doc1')));
} finally {
    exec('rm -rf ' . escapeshellarg($path));
}
?>
--EXPECT--
1. query fromId: doc1,doc2,doc3
2. filter: doc3,doc4
3. queryVector fromId: doc1,doc2,doc3
4. reuse: doc1,doc2,doc3
5. query object unchanged: yes
6. reranker: doc1,doc2,doc3
7. group fin: doc3,doc4
7. group tech: doc1,doc2
8. missing document: Document not found: missing
9. empty docId: Document ID must not be empty
10. docId and vector: Cannot provide both docId and vector
11. non-vector field: Vector field 'category' not found in document: doc1
