--TEST--
FFI::load() header loads all C function symbols
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$ffi = ZVec::ffi();

$requiredFunctions = [
    'zvec_init',
    'zvec_schema_create',
    'zvec_schema_free',
    'zvec_collection_create',
    'zvec_collection_open',
    'zvec_collection_free',
    'zvec_collection_flush',
    'zvec_collection_optimize',
    'zvec_collection_destroy',
    'zvec_doc_create',
    'zvec_doc_free',
    'zvec_collection_insert',
    'zvec_collection_upsert',
    'zvec_collection_update',
    'zvec_collection_delete',
    'zvec_collection_query',
    'zvec_collection_query_fp64',
    'zvec_collection_query_ex',
    'zvec_collection_query_fp64_ex',
    'zvec_collection_query_filter',
    'zvec_collection_query_filter_ex',
    'zvec_collection_group_by_query',
    'zvec_collection_query_vector',
    'zvec_collection_group_by_query_vector',
    'zvec_collection_stats',
    'zvec_collection_get_stats_struct',
    'zvec_collection_stats_free',
    'zvec_collection_get_field_schema',
    'zvec_field_schema_free',
    'zvec_get_version',
    'zvec_check_version',
    'zvec_get_version_major',
    'zvec_get_version_minor',
    'zvec_get_version_patch',
    'zvec_get_last_error_details',
    'zvec_clear_error',
    'zvec_error_code_to_string',
    'zvec_index_params_create',
    'zvec_index_params_free',
    'zvec_index_params_set_hnsw',
    'zvec_index_params_set_flat',
    'zvec_index_params_set_ivf',
    'zvec_index_params_set_vamana',
    'zvec_index_params_set_invert',
    'zvec_vector_query_create',
    'zvec_vector_query_free',
    'zvec_group_by_vector_query_create',
    'zvec_group_by_vector_query_free',
    'zvec_query_result_free',
    'zvec_query_result_free_array',
    'zvec_batch_result_free',
    'zvec_group_results_free',
    'zvec_ffi_initialize',
    'zvec_ffi_shutdown',
    'zvec_ffi_is_initialized',
    'zvec_config_data_create',
    'zvec_config_data_free',
    'zvec_log_config_create_console',
    'zvec_log_config_create_file',
    'zvec_log_config_free',
];

$missing = [];
foreach ($requiredFunctions as $fn) {
    try {
        $ffi->$fn;
    } catch (Throwable $e) {
        $missing[] = $fn;
    }
}

if (count($missing) > 0) {
    echo "FAIL: Missing FFI symbols: " . implode(', ', $missing) . "\n";
} else {
    echo "All " . count($requiredFunctions) . " FFI symbols resolved successfully\n";
}

// Verify no FFI::cdef() inline string remains in source
$source = file_get_contents(__DIR__ . '/../src/ZVec.php');
if (preg_match('/FFI::cdef\s*\(\s*[\'"]/', $source)) {
    echo "FAIL: FFI::cdef() with inline string still present in src/ZVec.php\n";
} else {
    echo "No FFI::cdef() inline string found in src/ZVec.php\n";
}

// Verify header file is used as source of truth
if (strpos($source, 'zvec_ffi_php.h') !== false) {
    echo "Header file zvec_ffi_php.h is used as source of truth\n";
} else {
    echo "FAIL: zvec_ffi_php.h not referenced in src/ZVec.php\n";
}

// Test basic functionality works with the loaded FFI
$path = __DIR__ . '/../test_dbs/ffi_load_test_' . uniqid();
try {
    $schema = new ZVecSchema('test');
    $schema->addInt64('id');
    $schema->addString('name');
    $schema->addVectorFp32('embedding', dimension: 128, metricType: ZVecSchema::METRIC_IP);

    $coll = ZVec::create($path, $schema);
    
    $doc = new ZVecDoc('doc1');
    $doc->setInt64('id', 1);
    $doc->setString('name', 'test');
    $vector = array_fill(0, 128, 0.5);
    $doc->setVectorFp32('embedding', $vector);
    $coll->insert($doc);
    
    $coll->optimize();
    
    echo "Basic create/insert/optimize works\n";
    
    $coll->close();
} finally {
    exec("rm -rf " . escapeshellarg($path));
}

echo "DONE\n";
?>
--EXPECT--
All 60 FFI symbols resolved successfully
No FFI::cdef() inline string found in src/ZVec.php
Header file zvec_ffi_php.h is used as source of truth
Basic create/insert/optimize works
DONE
