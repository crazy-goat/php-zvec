--TEST--
DocIterator: process shutdown with a collection and an open iterator exits cleanly
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_FATAL);

$schema = new ZVecSchema('iter_shutdown');
$schema->addInt64('id', nullable: false)
    ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

// Recorded so --CLEAN-- can remove the directory in a separate process: the
// collection must still exist when PHP shuts down, so try/finally is not an
// option here.
$path = __DIR__ . '/../test_dbs/iter_shutdown_' . uniqid();
file_put_contents(sys_get_temp_dir() . '/zvec_iter_shutdown.path', $path);

$c = ZVec::create($path, $schema);
for ($i = 0; $i < 5; $i++) {
    $c->insert(
        (new ZVecDoc((string)$i))->setInt64('id', $i)->setVectorFp32('v', [(float)$i, 0.0, 0.0, 0.0])
    );
}

$it = $c->iterDocs();
$it->rewind();

echo "done\n";
// No close(), no cleanup: at shutdown the PHP objects are torn down in an order
// we do not control, with the iterator still open. If the adapter released the
// collection before the iterator, upstream ~CollectionImpl would wait forever
// for an iterator that can never be closed -- an infinite hang rather than a
// crash, which is what makes this case worth its own file.
?>
--CLEAN--
<?php
$f = sys_get_temp_dir() . '/zvec_iter_shutdown.path';
if (is_file($f)) {
    exec('rm -rf ' . escapeshellarg(file_get_contents($f)));
    unlink($f);
}
?>
--EXPECT--
done
