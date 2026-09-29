--TEST--
Hybrid search: multiQuery() fuses FTS and dense sub-queries natively
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_FATAL);

$path = __DIR__ . '/../test_dbs/multi_query_' . uniqid();

try {
    $schema = new ZVecSchema('multi_query_test');
    $schema->addString('tag', nullable: true)
        ->addString('body')
        ->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createIndex('body', ZVecIndexParams::forFts());
    foreach ([
        ['d1', 'the quick brown fox', [1.0, 0.0, 0.0, 0.0]],
        ['d2', 'a fast brown rabbit', [0.0, 1.0, 0.0, 0.0]],
        ['d3', 'completely unrelated text', [0.0, 0.0, 1.0, 0.0]],
    ] as [$pk, $body, $vec]) {
        $c->insert(
            (new ZVecDoc($pk))->setString('tag', 't' . $pk)->setString('body', $body)->setVectorFp32('vec', $vec)
        );
    }
    $c->flush();

    $pks = static fn(array $docs): string => implode(',', array_map(static fn(ZVecDoc $d): string => $d->getPk(), $docs));

    $fts = static fn(): ZVecVectorQuery => (new ZVecVectorQuery('body', []))->setFts('body', 'brown');
    $dense = static fn(array $v = [1.0, 0.0, 0.0, 0.0]): ZVecVectorQuery => new ZVecVectorQuery('vec', $v);

    // The point of the feature: an FTS sub-query and a dense one, fused by
    // upstream. The PHP-side queryMulti() could not run this at all.
    $docs = $c->multiQuery([$fts(), $dense()], new ZVecRrfReRanker(), topk: 3);
    echo 'hybrid: ' . $pks($docs) . "\n";

    // RRF is sum(1/(k + rank + 1)) over the per-field lists, 0-based ranks.
    // The FTS list for "brown" ranks d2, d1 (d3 does not match); the dense list
    // for [1,0,0,0] ranks d1, d2 (d3 is further away). So:
    //   d2 = 1/61 (fts rank 0) + 1/62 (dense rank 1) = 0.032522
    //   d1 = 1/62 (fts rank 1) + 1/61 (dense rank 0) = the same, tie broken by order
    //   d3 = 1/63 (rank 2 in the FTS list)              = 0.015873
    // Exact fractions, not a PHP-side approximation.
    $scores = array_map(static fn(ZVecDoc $d): float => $d->getScore(), $docs);
    $expected = [1 / 61 + 1 / 62, 1 / 62 + 1 / 61, 1 / 63];
    $rrfOk = true;
    foreach ($expected as $i => $want) {
        if (abs($scores[$i] - $want) > 1e-6) {
            $rrfOk = false;
        }
    }
    echo 'scores are RRF: ' . ($rrfOk ? 'yes' : 'no') . "\n";

    echo 'filter: ' . $pks($c->multiQuery([$fts(), $dense()], new ZVecRrfReRanker(), topk: 3, filter: "tag = 'td1'")) . "\n";
    echo 'candidates: ' . $pks($c->multiQuery([$fts(), $dense()], new ZVecRrfReRanker(), topk: 3, numCandidates: 1)) . "\n";

    // Upstream allows two sub-queries on the same field; the PHP queryMulti()
    // silently dropped one because it keyed results by field name.
    $sameField = $c->multiQuery([$fts(), $fts()], new ZVecRrfReRanker(), topk: 3);
    echo 'same-field: ' . count($sameField) . ' results, last=' . $sameField[count($sameField) - 1]->getPk() . "\n";

    // Weighted fusion is positional and normalised by upstream, so the dense
    // sub-query alone decides the order.
    $weighted = $c->multiQuery([$fts(), $dense()], new ZVecWeightedReRanker([0.0, 1.0]), topk: 3);
    echo 'weighted first: ' . $weighted[0]->getPk() . "\n";

    $of = $c->multiQuery([$fts(), $dense()], new ZVecRrfReRanker(), topk: 3, outputFields: ['tag']);
    echo 'outputFields: tag=' . $of[0]->getString('tag') . ' body=' . var_export($of[0]->getString('body'), true) . "\n";

    $expect = static function (string $label, callable $fn) use ($c): void {
        try {
            $fn();
            echo "$label: NOT REJECTED\n";
        } catch (ZVecException $e) {
            echo "$label: " . $e->getMessage() . "\n";
        }
    };

    $expect('fewer than 2 sub-queries', static fn() => $c->multiQuery([$dense()], new ZVecRrfReRanker()));
    $expect('topk 0', static fn() => $c->multiQuery([$fts(), $dense()], new ZVecRrfReRanker(), topk: 0));
    $expect('field-keyed weights', static fn() => $c->multiQuery(
        [$fts(), $dense()],
        new ZVecWeightedReRanker(['body' => 0.0, 'vec' => 1.0])
    ));
    $expect('wrong weight count', static fn() => $c->multiQuery(
        [$fts(), $dense()],
        new ZVecWeightedReRanker([1.0, 0.5, 0.25])
    ));
    $expect('fromId sub-query', static fn() => $c->multiQuery(
        [$fts(), ZVecVectorQuery::fromId('vec', 'd1')],
        new ZVecRrfReRanker()
    ));
    $expect('unknown field', static fn() => $c->multiQuery(
        [(new ZVecVectorQuery('nope', []))->setFts('nope', 'x'), $dense()],
        new ZVecRrfReRanker()
    ));

    echo "PASS\n";
} finally {
    exec('rm -rf ' . escapeshellarg($path));
}
?>
--EXPECT--
hybrid: d2,d1,d3
scores are RRF: yes
filter: d1
candidates: d1
same-field: 2 results, last=d1
weighted first: d1
outputFields: tag=td2 body=NULL
fewer than 2 sub-queries: multiQuery() needs at least 2 sub-queries
topk 0: topk must be a positive integer, got: 0
field-keyed weights: multiQuery() needs positional weights (a list) because fusion is positional, not a field-keyed map
wrong weight count: Weighted reranker needs exactly one weight per sub-query
fromId sub-query: multiQuery() does not support fromId() sub-queries yet; fetch the vector first
unknown field: Invalid query: field nope not found
PASS
