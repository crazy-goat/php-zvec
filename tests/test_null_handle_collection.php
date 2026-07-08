<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$ffi = ZVec::getFFI();

echo "PASS: Starting null handle tests for collection\n";

// 1. zvec_collection_free(null) — should be no-op, not segfault
$ffi->zvec_collection_free(null);
echo "PASS: zvec_collection_free(null) is no-op\n";

// 2. zvec_collection_flush(null) — should return error, not segfault
$status = $ffi->zvec_collection_flush(null);
echo "PASS: zvec_collection_flush(null) returned code={$status->code}\n";

// 3. zvec_collection_optimize(null, 0) — should return error
$status = $ffi->zvec_collection_optimize(null, 0);
echo "PASS: zvec_collection_optimize(null, 0) returned code={$status->code}\n";

// 4. zvec_collection_destroy(null) — should return error
$status = $ffi->zvec_collection_destroy(null);
echo "PASS: zvec_collection_destroy(null) returned code={$status->code}\n";

// 5. zvec_collection_schema(null, ..., ...) — should return error
$buf = $ffi->new("char[256]");
$status = $ffi->zvec_collection_schema(null, $buf, 256);
echo "PASS: zvec_collection_schema(null) returned code={$status->code}\n";

// 6. zvec_collection_path(null, ..., ...) — should return error
$status = $ffi->zvec_collection_path(null, $buf, 256);
echo "PASS: zvec_collection_path(null) returned code={$status->code}\n";

// 7. zvec_collection_stats(null, ..., ...) — should return error
$status = $ffi->zvec_collection_stats(null, $buf, 256);
echo "PASS: zvec_collection_stats(null) returned code={$status->code}\n";

// 8. zvec_collection_query_vector(null, null, null) — should return error
$result = $ffi->new('zvec_query_result_t');
$ffi->zvec_query_result_free(\FFI::addr($result)); // ensure clean
$status = $ffi->zvec_collection_query_vector(null, null, \FFI::addr($result));
echo "PASS: zvec_collection_query_vector(null) returned code={$status->code}\n";

echo "PASS\n";
?>
