--TEST--
DocIterator lifecycle: guards on close/destroy/DDL, collection lifetime, destructor safety
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/iter_life_' . uniqid();

$schema = new ZVecSchema('iter_life');
$schema->addInt64('id', nullable: false)
    ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

function makeLifeDocs(int $n, string $prefix = ''): array
{
    $docs = [];
    for ($i = 0; $i < $n; $i++) {
        $docs[] = (new ZVecDoc($prefix . $i))
            ->setInt64('id', $i)
            ->setVectorFp32('v', [(float)$i, 0.0, 0.0, 0.0]);
    }
    return $docs;
}

try {
    $c = ZVec::create($path, $schema);
    $c->insert(...makeLifeDocs(10));
    $c->flush();
    $c->close();

    $c = ZVec::open($path);
    $it = $c->iterDocs();
    $it->rewind();

    // A rejected destroy() must leave the collection usable -- this is the
    // guard #222 had to provide, since erasing the handle on failure would
    // leave PHP with a dangling pointer.
    try {
        $c->destroy();
        echo "destroy: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo 'destroy: ' . $e->getErrorCodeString() . "\n";
    }
    echo 'usable after destroy: ' . count($c->fetch('0')) . "\n";

    try {
        $c->close();
        echo "close: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo 'close: ' . $e->getErrorCodeString() . "\n";
    }
    $c->flush();
    echo "usable after close: yes\n";

    try {
        $c->addColumnInt64('extra');
        echo "ddl: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo 'ddl: ' . $e->getErrorCodeString() . "\n";
    }

    try {
        $c->optimize();
        echo "optimize: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo 'optimize: ' . $e->getErrorCodeString() . "\n";
    }

    // Writes and queries are explicitly not affected.
    $c->insert(...makeLifeDocs(1, 'w_'));
    echo 'write while open: ' . count($c->fetch('w_0')) . "\n";

    // 10 rather than 9: iterator_count() calls rewind() first, which is a no-op
    // at position 0, so the already-current document is counted too. The
    // document written above is not visible -- the snapshot predates it.
    echo 'drained: ' . iterator_count($it) . "\n";

    // Exhaustion returned the slot, so this close is accepted.
    $c->close();
    echo "close after drain: ok\n";

    // Dropping the collection variable must not stop iteration: the iterator
    // holds a PHP reference to it. 11 because w_0 was written after the earlier
    // iterators existed and is visible to a fresh snapshot.
    $holder = ZVec::open($path);
    $it2 = $holder->iterDocs();
    $it2->rewind();
    unset($holder);
    echo 'iter after unset: ' . iterator_count($it2) . "\n";
    unset($it2);

    // Destructor with an open iterator must not throw, and the iterator must
    // keep working. This is where a member-order mistake in the adapter would
    // deadlock: ~CollectionImpl blocks until every iterator is closed.
    $doomed = ZVec::open($path);
    $it3 = $doomed->iterDocs();
    $it3->rewind();
    $doomed->__destruct();
    echo "destruct with open iterator: ok\n";
    try {
        $doomed->fetch('0');
        echo "closed: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo 'closed: ' . $e->getMessage() . "\n";
    }
    echo 'iter after destruct: ' . iterator_count($it3) . "\n";
    // 11 as above: this snapshot also includes w_0.
    // Required: the finished iterator still holds the old ZVec object, and that
    // object still holds the collection lock.
    unset($it3, $doomed);

    $reopened = ZVec::open($path);
    echo "reopen: ok\n";
    $reopened->destroy();
    echo "destroy after release: ok\n";
} finally {
    exec('rm -rf ' . escapeshellarg($path));
}
?>
--EXPECT--
destroy: FAILED_PRECONDITION
usable after destroy: 1
close: FAILED_PRECONDITION
usable after close: yes
ddl: FAILED_PRECONDITION
optimize: FAILED_PRECONDITION
write while open: 1
drained: 10
close after drain: ok
iter after unset: 11
destruct with open iterator: ok
closed: Collection is closed. Open with ZVec::open() to continue.
iter after destruct: 11
reopen: ok
destroy after release: ok
