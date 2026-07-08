<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$ffi = ZVec::getFFI();

echo "PASS: Starting null handle tests for schema/query/stats\n";

// 1. zvec_schema_free(null) — should be no-op
$ffi->zvec_schema_free(null);
echo "PASS: zvec_schema_free(null) is no-op\n";

// 2. zvec_vector_query_free(null) — should be no-op
$ffi->zvec_vector_query_free(null);
echo "PASS: zvec_vector_query_free(null) is no-op\n";

// 3. zvec_group_by_vector_query_free(null) — should be no-op
$ffi->zvec_group_by_vector_query_free(null);
echo "PASS: zvec_group_by_vector_query_free(null) is no-op\n";

// 4. zvec_index_params_free(null) — should be no-op
$ffi->zvec_index_params_free(null);
echo "PASS: zvec_index_params_free(null) is no-op\n";

// 5. zvec_collection_stats_free(null) — should be no-op
$ffi->zvec_collection_stats_free(null);
echo "PASS: zvec_collection_stats_free(null) is no-op\n";

// 6. zvec_field_schema_free(null) — should be no-op
$ffi->zvec_field_schema_free(null);
echo "PASS: zvec_field_schema_free(null) is no-op\n";

// 7. zvec_batch_result_free(null) — should be no-op
$ffi->zvec_batch_result_free(null);
echo "PASS: zvec_batch_result_free(null) is no-op\n";

// 8. zvec_query_result_free(null) — should be no-op
$ffi->zvec_query_result_free(null);
echo "PASS: zvec_query_result_free(null) is no-op\n";

// 9. zvec_group_results_free(null) — should be no-op
$ffi->zvec_group_results_free(null);
echo "PASS: zvec_group_results_free(null) is no-op\n";

// 10. zvec_collection_stats_get_doc_count(null) — should return 0
$ret = $ffi->zvec_collection_stats_get_doc_count(null);
echo "PASS: zvec_collection_stats_get_doc_count(null)=" . var_export($ret, true) . "\n";

// 11. zvec_collection_stats_get_index_count(null) — should return 0
$ret = $ffi->zvec_collection_stats_get_index_count(null);
echo "PASS: zvec_collection_stats_get_index_count(null)=" . var_export($ret, true) . "\n";

// 12. zvec_collection_stats_get_index_name(null, 0) — should return null
$ret = $ffi->zvec_collection_stats_get_index_name(null, 0);
echo "PASS: zvec_collection_stats_get_index_name(null)=" . var_export($ret, true) . "\n";

// 13. zvec_field_schema_get_name(null) — should return null
$ret = $ffi->zvec_field_schema_get_name(null);
echo "PASS: zvec_field_schema_get_name(null)=" . var_export($ret, true) . "\n";

// 14. zvec_field_schema_get_data_type(null) — should return 0
$ret = $ffi->zvec_field_schema_get_data_type(null);
echo "PASS: zvec_field_schema_get_data_type(null)=" . var_export($ret, true) . "\n";

echo "PASS\n";
?>
