<?php
/**
 * Repro: crash when using official zvec SDK (v0.7.0) via PHP FFI.
 *
 * The SDK is built with static libstdc++, so operator new/delete are local
 * symbols. PHP FFI uses dynamic libstdc++, resulting in two different
 * allocators. Memory allocated in libzvec.so is freed in libzvec_ffi.so,
 * causing "free(): chunks in smallbin corrupted" at exit.
 *
 * Expected: crash (SIGABRT) at exit.
 * See: docs/ffi_static_libstdc_crash.md
 */

require_once __DIR__ . '/../src/ZVec.php';

echo "1. FFI::load\n";
$ffi = ZVec::ffi();

echo "2. initialize(null)\n";
$st = $ffi->zvec_ffi_initialize(null);
echo "   code=" . $st->code . "\n";

echo "3. exit (should crash here)\n";
