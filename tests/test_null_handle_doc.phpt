--TEST--
SEC-004: Null pointer checks — doc functions handle null without segfault
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$ffi = ZVec::getFFI();

echo "PASS: Starting null handle tests for doc\n";

// 1. zvec_doc_free(null) — should be no-op
$ffi->zvec_doc_free(null);
echo "PASS: zvec_doc_free(null) is no-op\n";

// 2. zvec_doc_get_pk(null) — should return null
$pk = $ffi->zvec_doc_get_pk(null);
echo "PASS: zvec_doc_get_pk(null)=" . var_export($pk, true) . "\n";

// 3. zvec_doc_get_score(null) — should return 0.0
$score = $ffi->zvec_doc_get_score(null);
echo "PASS: zvec_doc_get_score(null)=" . sprintf("%.1f", $score) . "\n";

// 4. zvec_doc_get_int64(null, "field", null) — should return 0
$out = $ffi->new("int64_t");
$ret = $ffi->zvec_doc_get_int64(null, "field", \FFI::addr($out));
echo "PASS: zvec_doc_get_int64(null) returned $ret\n";

// 5. zvec_doc_get_string(null, "field", null) — should return 0
$strOut = $ffi->new("char*");
$ret = $ffi->zvec_doc_get_string(null, "field", \FFI::addr($strOut));
echo "PASS: zvec_doc_get_string(null) returned $ret\n";

// 6. zvec_doc_is_empty(null) — should return 0 (true=empty)
$ret = $ffi->zvec_doc_is_empty(null);
echo "PASS: zvec_doc_is_empty(null) returned $ret\n";

// 7. zvec_doc_has_field(null, "field") — should return 0
$ret = $ffi->zvec_doc_has_field(null, "field");
echo "PASS: zvec_doc_has_field(null) returned $ret\n";

// 8. zvec_doc_merge(null, null) — should be no-op
$ffi->zvec_doc_merge(null, null);
echo "PASS: zvec_doc_merge(null, null) is no-op\n";

// 9. zvec_doc_clear(null) — should be no-op
$ffi->zvec_doc_clear(null);
echo "PASS: zvec_doc_clear(null) is no-op\n";

// 10. zvec_doc_memory_usage(null) — should return 0
$ret = $ffi->zvec_doc_memory_usage(null);
echo "PASS: zvec_doc_memory_usage(null) returned $ret\n";

// 11. zvec_doc_serialize(null, null, null) — should return error status
$dataPtr = $ffi->new("uint8_t*");
$sizePtr = $ffi->new("size_t");
$status = $ffi->zvec_doc_serialize(null, \FFI::addr($dataPtr), \FFI::addr($sizePtr));
echo "PASS: zvec_doc_serialize(null) returned code={$status->code}\n";

echo "PASS\n";
?>
--EXPECTF--
PASS: Starting null handle tests for doc
PASS: zvec_doc_free(null) is no-op
PASS: zvec_doc_get_pk(null)=NULL
PASS: zvec_doc_get_score(null)=0.0
PASS: zvec_doc_get_int64(null) returned 0
PASS: zvec_doc_get_string(null) returned 0
PASS: zvec_doc_is_empty(null) returned 0
PASS: zvec_doc_has_field(null) returned 0
PASS: zvec_doc_merge(null, null) is no-op
PASS: zvec_doc_clear(null) is no-op
PASS: zvec_doc_memory_usage(null) returned 0
PASS: zvec_doc_serialize(null) returned code=%d
PASS
