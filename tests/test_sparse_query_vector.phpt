--TEST--
Sparse query vectors: setSparseVector() produces real scores and a working radius
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/sparse_query_' . uniqid();

try {
    $schema = new ZVecSchema('sparse_query');
    $schema->addSparseVectorFp32('sv');

    $c = ZVec::create($path, $schema);
    foreach ([
        ['d1', [0, 2], [1.0, 1.0]],
        ['d2', [2, 4], [1.0, 1.0]],
        ['d3', [0, 2, 4], [1.0, 1.0, 1.0]],
    ] as [$pk, $indices, $values]) {
        $c->insert((new ZVecDoc($pk))->setSparseVectorFp32('sv', $indices, $values));
    }
    $c->flush();

    $pks = static fn(array $docs): string => implode(',', array_map(static fn(ZVecDoc $d): string => $d->getPk(), $docs));

    // A negative weight makes d2 score below the others, which is what makes the
    // radius case observable: sparse scores are not normalised and can be
    // negative, so the IP threshold of -radius can separate them.
    $query = static fn(): ZVecVectorQuery => (new ZVecVectorQuery('sv', []))
        ->setTopk(3)
        ->setSparseVector([0, 2], [1.0, -0.5]);

    $docs = $c->queryVector($query());
    $scores = array_map(static fn(ZVecDoc $d): float => $d->getScore(), $docs);

    // Before setSparseVector() existed the handle carried no sparse clause, so
    // upstream saw a dense payload, turned it into a single 0-index entry and
    // scored every document 0.0. Distinct non-zero scores prove the clause
    // reached the engine.
    echo 'hits: ' . $pks($docs) . "\n";
    $rounded = array_map(static fn(float $s): float => round($s, 4), $scores);
    echo 'scores distinct: ' . (count(array_unique($rounded)) > 1 ? 'yes' : 'no') . "\n";
    echo 'scores non-zero: ' . (array_sum(array_map('abs', $scores)) > 0.0 ? 'yes' : 'no') . "\n";

    // The point of the change: radius now filters. With scores 0.5 / 0.5 / -0.5
    // and the IP threshold at -radius, radius 0.25 excludes d2.
    //
    // Everything radius-free runs first, on purpose: upstream caches the radius
    // threshold on a thread_local search context, so the first radius query
    // changes the result set of every later query in the same process (#200).
    // assertNoLeak below pins that down rather than working around it silently.
    // Without a radius everything comes back, including d2.
    $noRadius = $pks($c->queryVector($query()));
    echo 'no radius keeps d2: ' . (str_contains($noRadius, 'd2') ? 'yes' : 'no') . "\n";

    // The legacy scalar path has no dense vector to work with, so it delegates
    // to the vector path rather than failing on an empty float buffer.
    echo 'query(): ' . $pks($c->query($query())) . "\n";

    // Unsorted indices are accepted; upstream sorts them and rejects duplicates.
    $unsorted = (new ZVecVectorQuery('sv', []))->setTopk(3)->setSparseVector([2, 0], [1.0, 1.0]);
    echo 'unsorted: ' . $pks($c->queryVector($unsorted)) . "\n";

    try {
        $c->queryVector((new ZVecVectorQuery('sv', []))->setSparseVector([0, 0], [1.0, 1.0]));
        echo "duplicate indices: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo 'duplicate indices: rejected' . "\n";
    }

    $expect = static function (string $label, callable $fn) use ($c): void {
        try {
            $fn();
            echo "$label: NOT REJECTED\n";
        } catch (ZVecException $e) {
            echo "$label: rejected\n";
        }
    };
    $expect('empty indices', static fn() => (new ZVecVectorQuery('sv', []))->setSparseVector([], []));
    $expect('mismatched lengths', static fn() => (new ZVecVectorQuery('sv', []))->setSparseVector([0, 1], [1.0]));
    $expect('negative index', static fn() => (new ZVecVectorQuery('sv', []))->setSparseVector([-1], [1.0]));
    $expect('non-numeric value', static fn() => (new ZVecVectorQuery('sv', []))->setSparseVector([0], ['x']));

    // resetRadiusThreshold() takes a dense vector, so it cannot build a query for
    // a sparse field at all. Recorded here because it is the natural reflex when
    // the leak below bites.
    try {
        $c->resetRadiusThreshold('sv', []);
        echo "resetRadiusThreshold on sparse: accepted\n";
    } catch (ZVecException $e) {
        echo 'resetRadiusThreshold on sparse: ' . (str_contains($e->getMessage(), 'missing query clause') ? 'unsupported' : 'other error') . "\n";
    }

    // Radius cases last, and the leak they cause is asserted rather than hidden.
    $radiusQuery = static function () use ($query): ZVecVectorQuery {
        $q = $query();
        $q->setRadius(0.25);
        return $q;
    };
    $filtered = $pks($c->queryVector($radiusQuery()));
    echo 'radius 0.25: ' . $filtered . "\n";
    echo 'd2 excluded: ' . (!str_contains($filtered, 'd2') ? 'yes' : 'no') . "\n";
    $viaQuery = $query();
    $viaQuery->setRadius(0.25);
    echo 'query() radius 0.25: ' . $pks($c->query($viaQuery)) . "\n";
    // Threshold stays cached for the rest of the process (#200): the same
    // radius-free query now returns the filtered set.
    echo 'threshold leaks after radius: ' . (str_contains($pks($c->queryVector($query())), 'd2') ? 'no' : 'yes') . "\n";
} finally {
    exec('rm -rf ' . escapeshellarg($path));
}
?>
--EXPECT--
hits: d1,d3,d2
scores distinct: yes
scores non-zero: yes
no radius keeps d2: yes
query(): d1,d3,d2
unsorted: d1,d3,d2
duplicate indices: rejected
empty indices: rejected
mismatched lengths: rejected
negative index: rejected
non-numeric value: rejected
resetRadiusThreshold on sparse: unsupported
radius 0.25: d1,d3
d2 excluded: yes
query() radius 0.25: d1,d3
threshold leaks after radius: yes
