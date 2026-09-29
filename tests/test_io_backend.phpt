--TEST--
DiskANN I/O backend: getIoBackendType, getIoBackendTypeName, getIoBackendDescription
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';

// Deliberately no ZVec::init() before the first call: the backend is resolved
// lazily on first use, and the values are process-wide rather than config.

echo 'constants: ' . implode(',', [ZVec::IO_BACKEND_PREAD, ZVec::IO_BACKEND_LIBAIO, ZVec::IO_BACKEND_IO_URING]) . "\n";

echo 'names: ' . implode(',', [
    ZVec::getIoBackendTypeName(ZVec::IO_BACKEND_PREAD),
    ZVec::getIoBackendTypeName(ZVec::IO_BACKEND_LIBAIO),
    ZVec::getIoBackendTypeName(ZVec::IO_BACKEND_IO_URING),
    ZVec::getIoBackendTypeName(999),
]) . "\n";

$type = ZVec::getIoBackendType();
echo 'type valid: ' . (in_array($type, [ZVec::IO_BACKEND_PREAD, ZVec::IO_BACKEND_LIBAIO, ZVec::IO_BACKEND_IO_URING], true) ? 'yes' : 'no') . "\n";

$description = ZVec::getIoBackendDescription();
echo 'description non-empty: ' . ($description !== '' ? 'yes' : 'no') . "\n";
echo 'description matches name: ' . (str_contains(strtolower($description), ZVec::getIoBackendTypeName($type)) ? 'yes' : 'no') . "\n";

// macOS always uses synchronous pread(); on Linux any backend is acceptable.
$platformOk = PHP_OS_FAMILY !== 'Darwin' || $type === ZVec::IO_BACKEND_PREAD;
echo 'platform check: ' . ($platformOk ? 'ok' : 'FAIL') . "\n";

// The choice is cached after the first probe, so repeated calls agree.
echo 'stable: ' . (ZVec::getIoBackendType() === $type ? 'yes' : 'no') . "\n";

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);
echo 'after init: ' . (ZVec::getIoBackendType() === $type ? 'yes' : 'no') . "\n";

echo 'PASS';
?>
--EXPECT--
constants: 0,1,2
names: pread,libaio,io_uring,unknown
type valid: yes
description non-empty: yes
description matches name: yes
platform check: ok
stable: yes
after init: yes
PASS
