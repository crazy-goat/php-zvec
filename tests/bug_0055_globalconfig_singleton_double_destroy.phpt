--TEST--
Bug 0055: GlobalConfig singleton constructed and destroyed twice, SIGABRT at exit
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
/**
 * Bug reproduction: GlobalConfig singleton double destruction on exit.
 *
 * Expected: process exits cleanly (exit 0).
 * Actual:   "free(): chunks in smallbin corrupted" then SIGABRT (exit 134).
 *           valgrind reported an invalid read inside
 *           _Sp_counted_base::_M_release() from ~GlobalConfig() in
 *           libzvec_ffi.so, on an already-freed 32-byte block.
 *
 * Cause:    zvec::ailego::Singleton<T>::Instance() is an inline template with a
 *           function-local static. Calling it directly from the FFI adapter made
 *           the adapter emit its own GNU_UNIQUE "::obj" plus its own guard
 *           variable. The linker bound both modules to the same object, but each
 *           ran the constructor under its own guard, so the singleton -- and
 *           therefore the shared_ptr<GlobalConfig::LogConfig> it owns -- was
 *           constructed twice and destroyed twice at exit.
 *
 * Status: Fixed -- the adapter now resolves the one real Instance() inside
 *         libzvec through global_config_ptr() (dlsym) instead of inlining it.
 *
 * Location: ffi/zvec_ffi.cc, global_config_ptr() and its call sites.
 *
 * The collection is deliberately left open so it is still torn down by the exit
 * handlers: that is the path on which the double destruction fires. A test that
 * only called zvec_ffi_initialize() was not enough to trigger it.
 */

require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_ERROR);

$path = __DIR__ . '/../test_dbs/bug_0055_' . uniqid();

$schema = new ZVecSchema('bug_0055');
$schema->addInt64('id', nullable: false)
    ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

$collection = ZVec::create($path, $schema);
$collection->insert(
    (new ZVecDoc('a'))->setInt64('id', 1)->setVectorFp32('v', [1.0, 0.0, 0.0, 0.0])
);
$collection->flush();
$collection->insert(
    (new ZVecDoc('b'))->setInt64('id', 2)->setVectorFp32('v', [0.0, 1.0, 0.0, 0.0])
);
$collection->optimize();

$query = (new ZVecVectorQuery('v', [1.0, 0.0, 0.0, 0.0]))->setTopk(2);
echo 'hits: ' . count($collection->queryVector($query)) . "\n";

// No close() and no cleanup on purpose: the collection must still be alive when
// the exit handlers run, which is where the double destruction used to abort.
// The temp directory is removed by --CLEAN--.
echo "done\n";
?>
--CLEAN--
<?php
foreach (glob(__DIR__ . '/../test_dbs/bug_0055_*') as $dir) {
    exec('rm -rf ' . escapeshellarg($dir));
}
?>
--EXPECT--
hits: 2
done
