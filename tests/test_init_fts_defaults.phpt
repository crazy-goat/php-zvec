--TEST--
ZVec::init(): upstream defaults for jiebaDictDir and ftsBruteForceByKeysRatio
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';

// No new options: the getters must report the upstream defaults, and the
// bundled jieba dictionary must still be picked up automatically.
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

echo 'ratio: ' . sprintf('%.2f', ZVec::getFtsBruteForceByKeysRatio()) . "\n";

// The path is derived from the shared library location via dladdr, so it can
// contain a "src/../ffi/build" segment; match only the tail.
echo 'jieba dict: ' . (str_ends_with(ZVec::getJiebaDictDir(), '/zvec_data/jieba_dict') ? 'bundled' : 'other') . "\n";
echo 'is initialized: ' . (ZVec::isInitialized() ? 'yes' : 'no') . "\n";
?>
--EXPECT--
ratio: 0.05
jieba dict: bundled
is initialized: yes
