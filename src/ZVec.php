<?php

declare(strict_types=1);

if (extension_loaded('zvec')) return;

require_once __DIR__ . '/ZVecException.php';
require_once __DIR__ . '/ZVecCollectionOptions.php';
require_once __DIR__ . '/ZVecCollectionStats.php';
require_once __DIR__ . '/ZVecFieldSchema.php';
require_once __DIR__ . '/ZVecIndexParams.php';
require_once __DIR__ . '/ZVecVectorQuery.php';
require_once __DIR__ . '/ZVecGroupByVectorQuery.php';
require_once __DIR__ . '/ZVecSchema.php';
require_once __DIR__ . '/ZVecDoc.php';

class ZVec
{
    private static ?FFI $ffi = null;
    private FFI\CData $handle;
    private bool $closed = false;
    private bool $destroyed = false;
    private string $path = '';

    public static function getFFI(): ?FFI
    {
        return self::$ffi;
    }

    private static function ffi(): FFI
    {
        if (self::$ffi === null) {
            $ext = PHP_OS_FAMILY === 'Darwin' ? 'dylib' : 'so';
            $candidates = [
                __DIR__ . "/../lib/libzvec_ffi.$ext",
                __DIR__ . "/../ffi/build/libzvec_ffi.$ext",
            ];

            $libPath = null;
            foreach ($candidates as $candidate) {
                if (file_exists($candidate)) {
                    $libPath = $candidate;
                    break;
                }
            }

            if ($libPath === null) {
                throw new ZVecException("Library not found. Run 'composer install' or 'build_zvec.sh'.");
            }

            self::$ffi = FFI::cdef('
                typedef void* zvec_collection_t;
                typedef void* zvec_schema_t;
                typedef void* zvec_doc_t;

                typedef struct {
                    int code;
                    char message[512];
                } zvec_status_t;

                typedef struct {
                    int count;
                    int* codes;
                    char** messages;
                    char** doc_pks;
                } zvec_batch_result_t;

                typedef struct {
                    zvec_doc_t* docs;
                    int count;
                } zvec_query_result_t;

                zvec_status_t zvec_init(int log_type, int log_level,
                                        const char* log_dir, const char* log_basename,
                                        uint32_t log_file_size, uint32_t log_overdue_days,
                                        uint32_t query_threads, uint32_t optimize_threads,
                                        float invert_to_forward_scan_ratio,
                                        float brute_force_by_keys_ratio,
                                        uint64_t memory_limit_mb);

                typedef void* zvec_log_config_t;
                typedef void* zvec_config_data_t;

                zvec_log_config_t zvec_log_config_create_console(int level);
                zvec_log_config_t zvec_log_config_create_file(int level, const char* dir, const char* basename, uint32_t file_size, uint32_t overdue_days);
                void zvec_log_config_free(zvec_log_config_t config);

                zvec_config_data_t zvec_config_data_create(void);
                void zvec_config_data_free(zvec_config_data_t config);
                void zvec_config_data_set_memory_limit(zvec_config_data_t config, uint64_t bytes);
                void zvec_config_data_set_log_config(zvec_config_data_t config, zvec_log_config_t log_config);
                void zvec_config_data_set_query_thread_count(zvec_config_data_t config, uint32_t count);
                void zvec_config_data_set_optimize_thread_count(zvec_config_data_t config, uint32_t count);
                void zvec_config_data_set_invert_to_forward_scan_ratio(zvec_config_data_t config, float ratio);
                void zvec_config_data_set_brute_force_by_keys_ratio(zvec_config_data_t config, float ratio);

                zvec_status_t zvec_ffi_initialize(zvec_config_data_t config);
                zvec_status_t zvec_ffi_shutdown(void);
                int zvec_ffi_is_initialized(void);

                zvec_schema_t zvec_schema_create(const char* name);
                void zvec_schema_free(zvec_schema_t schema);
                void zvec_schema_set_max_doc_count_per_segment(zvec_schema_t schema, uint64_t count);
                void zvec_schema_add_field_int64(zvec_schema_t schema, const char* name, int nullable, int with_invert_index);
                void zvec_schema_add_field_string(zvec_schema_t schema, const char* name, int nullable, int with_invert_index);
                void zvec_schema_add_field_bool(zvec_schema_t schema, const char* name, int nullable, int with_invert_index);
                void zvec_schema_add_field_int32(zvec_schema_t schema, const char* name, int nullable, int with_invert_index);
                void zvec_schema_add_field_uint32(zvec_schema_t schema, const char* name, int nullable, int with_invert_index);
                void zvec_schema_add_field_uint64(zvec_schema_t schema, const char* name, int nullable, int with_invert_index);
                void zvec_schema_add_field_float(zvec_schema_t schema, const char* name, int nullable);
                void zvec_schema_add_field_double(zvec_schema_t schema, const char* name, int nullable);
                void zvec_schema_add_field_vector_fp32(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type);
                void zvec_schema_add_field_vector_fp64(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type);
                void zvec_schema_add_field_vector_int8(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type);
                void zvec_schema_add_field_vector_fp16(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type);
                void zvec_schema_add_field_sparse_vector_fp32(zvec_schema_t schema, const char* name, uint32_t metric_type);
                void zvec_schema_add_field_vector_int4(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type);
                void zvec_schema_add_field_vector_int16(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type);
                void zvec_schema_add_field_vector_binary32(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type);
                void zvec_schema_add_field_vector_binary64(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type);
                void zvec_schema_add_field_sparse_vector_fp16(zvec_schema_t schema, const char* name, uint32_t metric_type);
                void zvec_schema_add_field_binary(zvec_schema_t schema, const char* name, int nullable);
                void zvec_schema_add_field_array_string(zvec_schema_t schema, const char* name, int nullable);
                void zvec_schema_add_field_array_bool(zvec_schema_t schema, const char* name, int nullable);
                void zvec_schema_add_field_array_int32(zvec_schema_t schema, const char* name, int nullable);
                void zvec_schema_add_field_array_int64(zvec_schema_t schema, const char* name, int nullable);
                void zvec_schema_add_field_array_uint32(zvec_schema_t schema, const char* name, int nullable);
                void zvec_schema_add_field_array_uint64(zvec_schema_t schema, const char* name, int nullable);
                void zvec_schema_add_field_array_float(zvec_schema_t schema, const char* name, int nullable);
                void zvec_schema_add_field_array_double(zvec_schema_t schema, const char* name, int nullable);

                zvec_status_t zvec_collection_create(const char* path, zvec_schema_t schema, int read_only, int enable_mmap, uint32_t max_buffer_size, zvec_collection_t* out);
                zvec_status_t zvec_collection_open(const char* path, int read_only, int enable_mmap, uint32_t max_buffer_size, zvec_collection_t* out);
                void zvec_collection_free(zvec_collection_t coll);
                zvec_status_t zvec_collection_flush(zvec_collection_t coll);
                zvec_status_t zvec_collection_optimize(zvec_collection_t coll, uint32_t concurrency);
                zvec_status_t zvec_collection_destroy(zvec_collection_t coll);

                zvec_status_t zvec_collection_schema(zvec_collection_t coll, char* buf, size_t buf_size);
                zvec_status_t zvec_collection_path(zvec_collection_t coll, char* buf, size_t buf_size);
                zvec_status_t zvec_collection_options(zvec_collection_t coll, int* read_only, int* enable_mmap, uint32_t* max_buffer_size);

                zvec_status_t zvec_collection_add_column_int64(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency);
                zvec_status_t zvec_collection_add_column_bool(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency);
                zvec_status_t zvec_collection_add_column_int32(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency);
                zvec_status_t zvec_collection_add_column_uint32(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency);
                zvec_status_t zvec_collection_add_column_uint64(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency);
                zvec_status_t zvec_collection_add_column_float(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency);
                zvec_status_t zvec_collection_add_column_double(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency);
                zvec_status_t zvec_collection_add_column_string(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency);
                zvec_status_t zvec_collection_drop_column(zvec_collection_t coll, const char* name);
                zvec_status_t zvec_collection_rename_column(zvec_collection_t coll, const char* old_name, const char* new_name, uint32_t concurrency);
                zvec_status_t zvec_collection_alter_column(zvec_collection_t coll, const char* column_name, const char* new_name, uint32_t data_type, int nullable, uint32_t concurrency);

                zvec_status_t zvec_collection_create_invert_index(zvec_collection_t coll, const char* field_name, int enable_range, int enable_wildcard);
zvec_status_t zvec_collection_create_hnsw_index(zvec_collection_t coll, const char* field_name, uint32_t metric_type, int m, int ef_construction, uint32_t quantize_type, uint32_t concurrency);
zvec_status_t zvec_collection_create_hnsw_rabitq_index(zvec_collection_t coll, const char* field_name, uint32_t metric_type, int total_bits, int num_clusters, int m, int ef_construction, int sample_count, uint32_t concurrency);
zvec_status_t zvec_collection_create_flat_index(zvec_collection_t coll, const char* field_name, uint32_t metric_type, uint32_t quantize_type, uint32_t concurrency);
zvec_status_t zvec_collection_create_ivf_index(zvec_collection_t coll, const char* field_name, uint32_t metric_type, int n_list, int n_iters, int use_soar, uint32_t quantize_type, uint32_t concurrency);
                zvec_status_t zvec_collection_drop_index(zvec_collection_t coll, const char* field_name);

                typedef void* zvec_index_params_t;

                zvec_index_params_t zvec_index_params_create(int index_type, int metric_type);
                void zvec_index_params_free(zvec_index_params_t params);
                void zvec_index_params_set_hnsw(zvec_index_params_t params, int m, int ef_construction, int quantize_type, int use_contiguous_memory);
                void zvec_index_params_set_hnsw_rabitq(zvec_index_params_t params, int total_bits, int num_clusters, int m, int ef_construction, int sample_count);
                void zvec_index_params_set_flat(zvec_index_params_t params, int quantize_type);
                void zvec_index_params_set_ivf(zvec_index_params_t params, int n_list, int n_iters, int use_soar, int quantize_type);
                void zvec_index_params_set_vamana(zvec_index_params_t params, int max_degree, int search_list_size, float alpha, int saturate_graph, int use_contiguous_memory, int use_id_map, int quantize_type);
                void zvec_index_params_set_invert(zvec_index_params_t params, int enable_range, int enable_wildcard);
                void zvec_index_params_set_quantize_type(zvec_index_params_t params, int quantize_type);
                void zvec_index_params_set_metric_type(zvec_index_params_t params, int metric_type);
                zvec_status_t zvec_collection_create_index(zvec_collection_t coll, const char* field_name, zvec_index_params_t params, uint32_t concurrency);

                zvec_doc_t zvec_doc_create(const char* pk);
                void zvec_doc_free(zvec_doc_t doc);
                void zvec_doc_set_int64(zvec_doc_t doc, const char* field, int64_t value);
                void zvec_doc_set_bool(zvec_doc_t doc, const char* field, int value);
                void zvec_doc_set_int32(zvec_doc_t doc, const char* field, int32_t value);
                void zvec_doc_set_uint32(zvec_doc_t doc, const char* field, uint32_t value);
                void zvec_doc_set_uint64(zvec_doc_t doc, const char* field, uint64_t value);
                void zvec_doc_set_string(zvec_doc_t doc, const char* field, const char* value);
                void zvec_doc_set_float(zvec_doc_t doc, const char* field, float value);
                void zvec_doc_set_double(zvec_doc_t doc, const char* field, double value);
                void zvec_doc_set_vector_fp32(zvec_doc_t doc, const char* field, const float* data, uint32_t dim);
                void zvec_doc_set_vector_fp64(zvec_doc_t doc, const char* field, const double* data, uint32_t dim);
                void zvec_doc_set_vector_int8(zvec_doc_t doc, const char* field, const int8_t* data, uint32_t dim);
                void zvec_doc_set_vector_fp16(zvec_doc_t doc, const char* field, const uint16_t* data, uint32_t dim);
                void zvec_doc_set_sparse_vector_fp32(zvec_doc_t doc, const char* field, const uint32_t* indices, const float* values, uint32_t count);
                void zvec_doc_set_vector_int4(zvec_doc_t doc, const char* field, const int8_t* data, uint32_t dim);
                void zvec_doc_set_vector_int16(zvec_doc_t doc, const char* field, const int16_t* data, uint32_t dim);
                void zvec_doc_set_vector_binary32(zvec_doc_t doc, const char* field, const uint32_t* data, uint32_t dim);
                void zvec_doc_set_vector_binary64(zvec_doc_t doc, const char* field, const uint64_t* data, uint32_t dim);
                void zvec_doc_set_sparse_vector_fp16(zvec_doc_t doc, const char* field, const uint32_t* indices, const uint16_t* values, uint32_t count);
                void zvec_doc_set_binary(zvec_doc_t doc, const char* field, const uint8_t* data, uint32_t size);
                void zvec_doc_set_array_int32(zvec_doc_t doc, const char* field, const int32_t* data, uint32_t count);
                void zvec_doc_set_array_int64(zvec_doc_t doc, const char* field, const int64_t* data, uint32_t count);
                void zvec_doc_set_array_uint32(zvec_doc_t doc, const char* field, const uint32_t* data, uint32_t count);
                void zvec_doc_set_array_uint64(zvec_doc_t doc, const char* field, const uint64_t* data, uint32_t count);
                void zvec_doc_set_array_float(zvec_doc_t doc, const char* field, const float* data, uint32_t count);
                void zvec_doc_set_array_double(zvec_doc_t doc, const char* field, const double* data, uint32_t count);
                void zvec_doc_set_array_string(zvec_doc_t doc, const char* field, const char** strings, uint32_t count);
                void zvec_doc_set_array_bool(zvec_doc_t doc, const char* field, const uint8_t* data, uint32_t count);

                const char* zvec_doc_get_pk(zvec_doc_t doc);
                float zvec_doc_get_score(zvec_doc_t doc);
                int zvec_doc_get_int64(zvec_doc_t doc, const char* field, int64_t* out);
                int zvec_doc_get_bool(zvec_doc_t doc, const char* field, int* out);
                int zvec_doc_get_int32(zvec_doc_t doc, const char* field, int32_t* out);
                int zvec_doc_get_uint32(zvec_doc_t doc, const char* field, uint32_t* out);
                int zvec_doc_get_uint64(zvec_doc_t doc, const char* field, uint64_t* out);
                int zvec_doc_get_string(zvec_doc_t doc, const char* field, const char** out);
                int zvec_doc_get_float(zvec_doc_t doc, const char* field, float* out);
                int zvec_doc_get_double(zvec_doc_t doc, const char* field, double* out);
                int zvec_doc_get_vector_fp32(zvec_doc_t doc, const char* field, const float** out, uint32_t* dim);
                int zvec_doc_get_vector_fp64(zvec_doc_t doc, const char* field, const double** out, uint32_t* dim);
                int zvec_doc_get_vector_int8(zvec_doc_t doc, const char* field, const int8_t** out, uint32_t* dim);
                int zvec_doc_get_vector_fp16(zvec_doc_t doc, const char* field, const uint16_t** out, uint32_t* dim);
                int zvec_doc_get_sparse_vector_fp32(zvec_doc_t doc, const char* field, const uint32_t** indices_out, const float** values_out, uint32_t* count_out);
                int zvec_doc_get_vector_int4(zvec_doc_t doc, const char* field, const int8_t** out, uint32_t* dim);
                int zvec_doc_get_vector_int16(zvec_doc_t doc, const char* field, const int16_t** out, uint32_t* dim);
                int zvec_doc_get_vector_binary32(zvec_doc_t doc, const char* field, const uint32_t** out, uint32_t* dim);
                int zvec_doc_get_vector_binary64(zvec_doc_t doc, const char* field, const uint64_t** out, uint32_t* dim);
                int zvec_doc_get_sparse_vector_fp16(zvec_doc_t doc, const char* field, const uint32_t** indices_out, const uint16_t** values_out, uint32_t* count_out);
                int zvec_doc_get_binary(zvec_doc_t doc, const char* field, const uint8_t** out, uint32_t* size);
                int zvec_doc_get_array_int32(zvec_doc_t doc, const char* field, const int32_t** out, uint32_t* count);
                int zvec_doc_get_array_int64(zvec_doc_t doc, const char* field, const int64_t** out, uint32_t* count);
                int zvec_doc_get_array_uint32(zvec_doc_t doc, const char* field, const uint32_t** out, uint32_t* count);
                int zvec_doc_get_array_uint64(zvec_doc_t doc, const char* field, const uint64_t** out, uint32_t* count);
                int zvec_doc_get_array_float(zvec_doc_t doc, const char* field, const float** out, uint32_t* count);
                int zvec_doc_get_array_double(zvec_doc_t doc, const char* field, const double** out, uint32_t* count);
                int zvec_doc_get_array_string(zvec_doc_t doc, const char* field, char* buf, size_t buf_size, uint32_t* count);
                int zvec_doc_get_array_bool(zvec_doc_t doc, const char* field, uint8_t* buf, size_t buf_size);

                // Doc introspection
                int zvec_doc_has_field(zvec_doc_t doc, const char* field);
                int zvec_doc_has_vector(zvec_doc_t doc, const char* field);
                int zvec_doc_field_names(zvec_doc_t doc, char* buf, size_t buf_size);
                int zvec_doc_vector_names(zvec_doc_t doc, char* buf, size_t buf_size);

                // Enhanced Doc API
                void zvec_doc_set_field_null(zvec_doc_t doc, const char* field);
                int zvec_doc_is_field_null(zvec_doc_t doc, const char* field);
                void zvec_doc_remove_field(zvec_doc_t doc, const char* field);
                void zvec_doc_merge(zvec_doc_t doc, zvec_doc_t other);
                zvec_status_t zvec_doc_serialize(zvec_doc_t doc, uint8_t** data, size_t* size);
                void zvec_free_serialized(uint8_t* data);
                zvec_status_t zvec_doc_deserialize(const uint8_t* data, size_t size, zvec_doc_t* out);
                int zvec_doc_is_empty(zvec_doc_t doc);
                void zvec_doc_clear(zvec_doc_t doc);
                size_t zvec_doc_memory_usage(zvec_doc_t doc);
                void zvec_doc_set_operator(zvec_doc_t doc, int op);
                int zvec_doc_get_operator(zvec_doc_t doc);

                zvec_status_t zvec_collection_insert(zvec_collection_t coll, zvec_doc_t* docs, int count);
                zvec_status_t zvec_collection_upsert(zvec_collection_t coll, zvec_doc_t* docs, int count);
                zvec_status_t zvec_collection_update(zvec_collection_t coll, zvec_doc_t* docs, int count);
                zvec_status_t zvec_collection_delete(zvec_collection_t coll, const char** pks, int count);
                zvec_status_t zvec_collection_delete_by_filter(zvec_collection_t coll, const char* filter);

                zvec_status_t zvec_collection_insert_batch(zvec_collection_t coll, zvec_doc_t* docs, int count, zvec_batch_result_t* result);
                zvec_status_t zvec_collection_upsert_batch(zvec_collection_t coll, zvec_doc_t* docs, int count, zvec_batch_result_t* result);
                zvec_status_t zvec_collection_update_batch(zvec_collection_t coll, zvec_doc_t* docs, int count, zvec_batch_result_t* result);
                void zvec_batch_result_free(zvec_batch_result_t* result);

                zvec_status_t zvec_collection_fetch(zvec_collection_t coll, const char** pks, int count, zvec_query_result_t* result);

                zvec_status_t zvec_collection_query(zvec_collection_t coll, const char* field_name,
                                     const float* query_vector, uint32_t dim,
                                     int topk, int include_vector,
                                     const char* filter,
                                     zvec_query_result_t* result);
                zvec_status_t zvec_collection_query_fp16(zvec_collection_t coll, const char* field_name,
                                          const uint16_t* query_vector, uint32_t dim,
                                          int topk, int include_vector,
                                          const char* filter,
                                          zvec_query_result_t* result);
                zvec_status_t zvec_collection_query_fp64(zvec_collection_t coll, const char* field_name,
                                          const double* query_vector, uint32_t dim,
                                          int topk, int include_vector,
                                          const char* filter,
                                          zvec_query_result_t* result);
                zvec_status_t zvec_collection_query_ex(zvec_collection_t coll, const char* field_name,
                                                        const float* query_vector, uint32_t dim,
                                                        int topk, int include_vector,
                                                        const char* filter,
                                                        const char** output_fields, int output_fields_count,
                                                        int query_param_type,
                                                        int hnsw_ef,
                                                        int ivf_nprobe,
                                                        float radius,
                                                        int is_linear,
                                                        int is_using_refiner,
                                                        zvec_query_result_t* result);
                zvec_status_t zvec_collection_query_fp64_ex(zvec_collection_t coll, const char* field_name,
                                                        const double* query_vector, uint32_t dim,
                                                        int topk, int include_vector,
                                                        const char* filter,
                                                        const char** output_fields, int output_fields_count,
                                                        int query_param_type,
                                                        int hnsw_ef,
                                                        int ivf_nprobe,
                                                        float radius,
                                                        int is_linear,
                                                        int is_using_refiner,
                                                        zvec_query_result_t* result);
                zvec_status_t zvec_collection_query_filter(zvec_collection_t coll, const char* filter,
                                                            int topk, zvec_query_result_t* result);
                zvec_status_t zvec_collection_query_filter_ex(zvec_collection_t coll, const char* filter,
                                                               int topk,
                                                               const char** output_fields, int output_fields_count,
                                                               zvec_query_result_t* result);

                typedef struct {
                    zvec_doc_t* docs;
                    int count;
                    const char* group_by_value;
                } zvec_group_result_t;

                typedef struct {
                    zvec_group_result_t* groups;
                    int count;
                } zvec_group_results_t;

                zvec_status_t zvec_collection_group_by_query(zvec_collection_t coll, const char* field_name,
                                                              const float* query_vector, uint32_t dim,
                                                              const char* group_by_field,
                                                              uint32_t group_count, uint32_t group_topk,
                                                              int include_vector,
                                                              const char* filter,
                                                              const char** output_fields, int output_fields_count,
                                                              int query_param_type,
                                                              int hnsw_ef,
                                                              int ivf_nprobe,
                                                              float radius,
                                                              int is_linear,
                                                              int is_using_refiner,
                                                              zvec_group_results_t* result);
                void zvec_group_results_free(zvec_group_results_t* result);

                typedef void* zvec_vector_query_t;
                typedef void* zvec_group_by_vector_query_t;

                zvec_vector_query_t zvec_vector_query_create(void);
                void zvec_vector_query_free(zvec_vector_query_t q);

                void zvec_vector_query_set_field_name(zvec_vector_query_t q, const char* field_name);
                void zvec_vector_query_set_topk(zvec_vector_query_t q, int topk);
                void zvec_vector_query_set_include_vector(zvec_vector_query_t q, int include);
                void zvec_vector_query_set_filter(zvec_vector_query_t q, const char* filter);
                void zvec_vector_query_set_output_fields(zvec_vector_query_t q, const char** fields, int count);
                void zvec_vector_query_set_hnsw_ef(zvec_vector_query_t q, int ef);
                void zvec_vector_query_set_ivf_nprobe(zvec_vector_query_t q, int nprobe);
                void zvec_vector_query_set_flat_mode(zvec_vector_query_t q);
                void zvec_vector_query_set_radius(zvec_vector_query_t q, float radius);
                void zvec_vector_query_set_is_linear(zvec_vector_query_t q, int is_linear);
                void zvec_vector_query_set_using_refiner(zvec_vector_query_t q, int refiner);
                void zvec_vector_query_set_vector_fp32(zvec_vector_query_t q, const float* data, uint32_t dim);
                void zvec_vector_query_set_vector_fp64(zvec_vector_query_t q, const double* data, uint32_t dim);

                zvec_group_by_vector_query_t zvec_group_by_vector_query_create(void);
                void zvec_group_by_vector_query_free(zvec_group_by_vector_query_t q);
                void zvec_group_by_vector_query_set_field_name(zvec_group_by_vector_query_t q, const char* field_name);
                void zvec_group_by_vector_query_set_vector_fp32(zvec_group_by_vector_query_t q, const float* data, uint32_t dim);
                void zvec_group_by_vector_query_set_group_by_field(zvec_group_by_vector_query_t q, const char* field);
                void zvec_group_by_vector_query_set_group_count(zvec_group_by_vector_query_t q, uint32_t count);
                void zvec_group_by_vector_query_set_group_topk(zvec_group_by_vector_query_t q, uint32_t topk);
                void zvec_group_by_vector_query_set_include_vector(zvec_group_by_vector_query_t q, int include);
                void zvec_group_by_vector_query_set_filter(zvec_group_by_vector_query_t q, const char* filter);
                void zvec_group_by_vector_query_set_output_fields(zvec_group_by_vector_query_t q, const char** fields, int count);
                void zvec_group_by_vector_query_set_radius(zvec_group_by_vector_query_t q, float radius);
                void zvec_group_by_vector_query_set_is_linear(zvec_group_by_vector_query_t q, int is_linear);
                void zvec_group_by_vector_query_set_using_refiner(zvec_group_by_vector_query_t q, int refiner);

                zvec_status_t zvec_collection_query_vector(zvec_collection_t coll, const zvec_vector_query_t q, zvec_query_result_t* result);
                zvec_status_t zvec_collection_group_by_query_vector(zvec_collection_t coll, const zvec_group_by_vector_query_t q, zvec_group_results_t* result);

                void zvec_query_result_free(zvec_query_result_t* result);
                void zvec_query_result_free_array(zvec_query_result_t* result);

                zvec_status_t zvec_collection_stats(zvec_collection_t coll, char* buf, size_t buf_size);

                // CollectionStats
                typedef void* zvec_collection_stats_t;

                zvec_status_t zvec_collection_get_stats_struct(zvec_collection_t coll, zvec_collection_stats_t* out);
                void zvec_collection_stats_free(zvec_collection_stats_t stats);
                uint64_t zvec_collection_stats_get_doc_count(zvec_collection_stats_t stats);
                uint32_t zvec_collection_stats_get_index_count(zvec_collection_stats_t stats);
                const char* zvec_collection_stats_get_index_name(zvec_collection_stats_t stats, uint32_t index);
                float zvec_collection_stats_get_index_completeness(zvec_collection_stats_t stats, uint32_t index);

                typedef struct {
                    int code;
                    const char* message;
                    const char* file;
                    int line;
                    const char* function;
                } zvec_error_details_t;

                int zvec_get_last_error_details(zvec_error_details_t* out);
                void zvec_clear_error(void);
                const char* zvec_error_code_to_string(int error_code);

                const char* zvec_get_version(void);
                int zvec_check_version(int major, int minor, int patch);
                int zvec_get_version_major(void);
                int zvec_get_version_minor(void);
                int zvec_get_version_patch(void);

                typedef void* zvec_field_schema_t;

                zvec_status_t zvec_collection_get_field_schema(zvec_collection_t coll, const char* field_name, zvec_field_schema_t* out);
                void zvec_field_schema_free(zvec_field_schema_t schema);
                const char* zvec_field_schema_get_name(zvec_field_schema_t schema);
                int zvec_field_schema_get_data_type(zvec_field_schema_t schema);
                int zvec_field_schema_get_element_data_type(zvec_field_schema_t schema);
                size_t zvec_field_schema_get_element_data_size(zvec_field_schema_t schema);
                uint32_t zvec_field_schema_get_dimension(zvec_field_schema_t schema);
                int zvec_field_schema_is_vector_field(zvec_field_schema_t schema);
                int zvec_field_schema_is_dense_vector(zvec_field_schema_t schema);
                int zvec_field_schema_is_sparse_vector(zvec_field_schema_t schema);
                int zvec_field_schema_is_array_type(zvec_field_schema_t schema);
                int zvec_field_schema_is_nullable(zvec_field_schema_t schema);
                int zvec_field_schema_has_invert_index(zvec_field_schema_t schema);
                int zvec_field_schema_has_index(zvec_field_schema_t schema);
                int zvec_field_schema_get_index_type(zvec_field_schema_t schema);
            ', $libPath);
        }
        return self::$ffi;
    }

    public static function checkStatus(FFI\CData $status): void
    {
        if ($status->code !== 0) {
            $ffi = self::ffi();
            $details = $ffi->new('zvec_error_details_t');
            $ffi->zvec_get_last_error_details(FFI::addr($details));
            $msg = $details->message !== null ? FFI::string($details->message) : FFI::string($status->message);
            $file = $details->file !== null ? FFI::string($details->file) : null;
            $line = $details->line;
            $function = $details->function !== null ? FFI::string($details->function) : null;
            throw new ZVecException($msg, $status->code, null, $file, $line, $function);
        }
    }

    private function checkClosed(): void
    {
        if ($this->destroyed) {
            throw new ZVecException("Collection has been destroyed and cannot be reused");
        }
        if ($this->closed) {
            throw new ZVecException("Collection is closed. Open with ZVec::open() to continue.");
        }
    }

    /**
     * Parse FFI query result into ZVecDoc array and free the result.
     *
     * @return ZVecDoc[]
     */
    private static function parseQueryResult(FFI\CData $result): array
    {
        $ffi = self::ffi();
        $docs = [];
        for ($i = 0; $i < $result->count; $i++) {
            $docs[] = new ZVecDoc($result->docs[$i], true);
        }
        $ffi->zvec_query_result_free_array(FFI::addr($result));
        return $docs;
    }

    /**
     * Parse FFI group query result into indexed array and free the result.
     *
     * @return array<int, array{group_value: string, docs: ZVecDoc[]}>
     */
    private static function parseGroupResult(FFI\CData $result): array
    {
        $ffi = self::ffi();
        $groups = [];
        for ($i = 0; $i < $result->count; $i++) {
            $group = $result->groups[$i];
            $gv = $group->group_by_value;
            $groupValue = is_string($gv) ? $gv : FFI::string($gv);
            $docs = [];
            for ($j = 0; $j < $group->count; $j++) {
                $docs[] = new ZVecDoc($group->docs[$j], true);
            }
            $groups[] = ['group_value' => $groupValue, 'docs' => $docs];
        }
        $ffi->zvec_group_results_free(FFI::addr($result));
        return $groups;
    }

    public static function create(string $path, ZVecSchema $schema, bool $readOnly = false, bool $enableMmap = true, int $maxBufferSize = 67108864): self
    {
        if ($path === '') {
            throw new ZVecException('Path must not be empty');
        }
        $ffi = self::ffi();
        $out = $ffi->new('zvec_collection_t');
        $status = $ffi->zvec_collection_create($path, $schema->getHandle(), $readOnly ? 1 : 0, $enableMmap ? 1 : 0, $maxBufferSize, FFI::addr($out));
        self::checkStatus($status);
        return new self($out, $path);
    }

    public static function open(string $path, bool $readOnly = false, bool $enableMmap = true, int $maxBufferSize = 67108864): self
    {
        if ($path === '') {
            throw new ZVecException('Path must not be empty');
        }
        $ffi = self::ffi();
        $out = $ffi->new('zvec_collection_t');
        $status = $ffi->zvec_collection_open($path, $readOnly ? 1 : 0, $enableMmap ? 1 : 0, $maxBufferSize, FFI::addr($out));
        self::checkStatus($status);
        return new self($out, $path);
    }

    public static function createWith(string $path, ZVecSchema $schema, ZVecCollectionOptions $options): self
    {
        return self::create($path, $schema, $options->readOnly, $options->enableMmap, $options->maxBufferSize);
    }

    public static function openWith(string $path, ZVecCollectionOptions $options): self
    {
        return self::open($path, $options->readOnly, $options->enableMmap, $options->maxBufferSize);
    }

    public function getOptions(): ZVecCollectionOptions
    {
        $arr = $this->options();
        return new ZVecCollectionOptions(
            readOnly: $arr['read_only'],
            enableMmap: $arr['enable_mmap'],
            maxBufferSize: $arr['max_buffer_size']
        );
    }

    private function __construct(FFI\CData $handle, string $path)
    {
        $this->handle = $handle;
        $this->path = $path;
    }

    public function __destruct()
    {
        $this->close();
    }

    private function __clone()
    {
    }

    public function close(): void
    {
        if ($this->closed || $this->destroyed) {
            return;
        }
        self::ffi()->zvec_collection_free($this->handle);
        $this->closed = true;
    }

    public function flush(): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_flush($this->handle));
    }

    public function optimize(int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_optimize($this->handle, $concurrency));
    }

    public function destroy(): void
    {
        if ($this->destroyed) {
            return;
        }

        if ($this->closed) {
            // Re-open via stored path so we can destroy the on-disk data
            $ffi = self::ffi();
            $newHandle = $ffi->new('zvec_collection_t');
            $status = $ffi->zvec_collection_open($this->path, 0, 1, 67108864, FFI::addr($newHandle));
            self::checkStatus($status);
            try {
                self::checkStatus($ffi->zvec_collection_destroy($newHandle));
            } catch (\Throwable $e) {
                // Free the reopened handle to prevent orphaned C++ object in g_collections
                $ffi->zvec_collection_free($newHandle);
                throw $e;
            }
        } else {
            self::checkStatus(self::ffi()->zvec_collection_destroy($this->handle));
            self::ffi()->zvec_collection_free($this->handle);
            $this->closed = true;
        }

        $this->destroyed = true;
    }

    public function schema(): string
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $buf = $ffi->new('char[8192]');
        self::checkStatus($ffi->zvec_collection_schema($this->handle, $buf, 8192));
        return FFI::string($buf);
    }

    public function path(): string
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $buf = $ffi->new('char[4096]');
        self::checkStatus($ffi->zvec_collection_path($this->handle, $buf, 4096));
        return FFI::string($buf);
    }

    /**
     * @return array{read_only: bool, enable_mmap: bool, max_buffer_size: int}
     */
    public function options(): array
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $readOnly = $ffi->new('int');
        $enableMmap = $ffi->new('int');
        $maxBufferSize = $ffi->new('uint32_t');
        self::checkStatus($ffi->zvec_collection_options($this->handle, FFI::addr($readOnly), FFI::addr($enableMmap), FFI::addr($maxBufferSize)));
        return [
            'read_only' => $readOnly->cdata !== 0,
            'enable_mmap' => $enableMmap->cdata !== 0,
            'max_buffer_size' => (int)$maxBufferSize->cdata,
        ];
    }

    public function addColumnInt64(string $name, bool $nullable = true, string $defaultExpr = '0', int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_add_column_int64($this->handle, $name, $nullable ? 1 : 0, $defaultExpr, $concurrency));
    }

    public function addColumnFloat(string $name, bool $nullable = true, string $defaultExpr = '0', int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_add_column_float($this->handle, $name, $nullable ? 1 : 0, $defaultExpr, $concurrency));
    }

    public function addColumnDouble(string $name, bool $nullable = true, string $defaultExpr = '0', int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_add_column_double($this->handle, $name, $nullable ? 1 : 0, $defaultExpr, $concurrency));
    }

    public function addColumnString(string $name, bool $nullable = true, string $defaultExpr = '', int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_add_column_string($this->handle, $name, $nullable ? 1 : 0, $defaultExpr, $concurrency));
    }

    public function addColumnBool(string $name, bool $nullable = true, string $defaultExpr = 'false', int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_add_column_bool($this->handle, $name, $nullable ? 1 : 0, $defaultExpr, $concurrency));
    }

    public function addColumnInt32(string $name, bool $nullable = true, string $defaultExpr = '0', int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_add_column_int32($this->handle, $name, $nullable ? 1 : 0, $defaultExpr, $concurrency));
    }

    public function addColumnUint32(string $name, bool $nullable = true, string $defaultExpr = '0', int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_add_column_uint32($this->handle, $name, $nullable ? 1 : 0, $defaultExpr, $concurrency));
    }

    public function addColumnUint64(string $name, bool $nullable = true, string $defaultExpr = '0', int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_add_column_uint64($this->handle, $name, $nullable ? 1 : 0, $defaultExpr, $concurrency));
    }

    public function dropColumn(string $name): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_drop_column($this->handle, $name));
    }

    public function renameColumn(string $oldName, string $newName, int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_rename_column($this->handle, $oldName, $newName, $concurrency));
    }

    public function alterColumn(string $columnName, ?string $newName = null, ?int $newDataType = null, ?bool $nullable = null, int $concurrency = 0): void
    {
        $this->checkClosed();
        // Data type constants for alter column (scalar numeric only)
        // INT32 = 4, INT64 = 5, UINT32 = 6, UINT64 = 7, FLOAT = 8, DOUBLE = 9
        $dataType = $newDataType ?? 0; // 0 = UNDEFINED (no type change)
        $isNullable = $nullable === null ? 0 : ($nullable ? 1 : 0);
        $rename = $newName ?? '';
        
        self::checkStatus(self::ffi()->zvec_collection_alter_column($this->handle, $columnName, $rename, $dataType, $isNullable, $concurrency));
    }

    public function dropIndex(string $fieldName): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_drop_index($this->handle, $fieldName));
    }

    /**
     * Create an index using the unified IndexParams API.
     */
    public function createIndex(string $fieldName, ZVecIndexParams $params, int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_create_index($this->handle, $fieldName, $params->getHandle(), $concurrency));
    }

    /** @deprecated Use createIndex() with ZVecIndexParams::forInvert() instead */
    public function createInvertIndex(string $fieldName, bool $enableRange = true, bool $enableWildcard = false): void
    {
        $this->createIndex($fieldName, ZVecIndexParams::forInvert($enableRange, $enableWildcard));
    }

    /** @deprecated Use createIndex() with ZVecIndexParams::forHnsw() instead */
    public function createHnswIndex(string $fieldName, int $metricType = ZVecSchema::METRIC_IP, int $m = 50, int $efConstruction = 500, int $quantizeType = 0, int $concurrency = 0, bool $useContiguousMemory = false): void
    {
        $this->createIndex($fieldName, ZVecIndexParams::forHnsw($metricType, $m, $efConstruction, $quantizeType, $useContiguousMemory), $concurrency);
    }

    /** @deprecated Use createIndex() with ZVecIndexParams::forHnswRabitq() instead */
    public function createHnswRabitqIndex(string $fieldName, int $metricType = ZVecSchema::METRIC_IP, int $totalBits = 7, int $numClusters = 16, int $m = 50, int $efConstruction = 500, int $sampleCount = 0, int $concurrency = 0): void
    {
        $this->createIndex($fieldName, ZVecIndexParams::forHnswRabitq($metricType, $totalBits, $numClusters, $m, $efConstruction, $sampleCount), $concurrency);
    }

    /** @deprecated Use createIndex() with ZVecIndexParams::forFlat() instead */
    public function createFlatIndex(string $fieldName, int $metricType = ZVecSchema::METRIC_IP, int $quantizeType = 0, int $concurrency = 0): void
    {
        $this->createIndex($fieldName, ZVecIndexParams::forFlat($metricType, $quantizeType), $concurrency);
    }

    /** @deprecated Use createIndex() with ZVecIndexParams::forIvf() instead */
    public function createIvfIndex(string $fieldName, int $metricType = ZVecSchema::METRIC_IP, int $nList = 1024, int $nIters = 10, bool $useSoar = false, int $quantizeType = 0, int $concurrency = 0): void
    {
        $this->createIndex($fieldName, ZVecIndexParams::forIvf($metricType, $nList, $nIters, $useSoar, $quantizeType), $concurrency);
    }

    private function writeDocs(string $operation, ZVecDoc ...$docs): void
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $count = count($docs);
        $arr = $ffi->new("zvec_doc_t[$count]");
        foreach ($docs as $i => $doc) {
            $arr[$i] = $doc->getHandle();
        }
        self::checkStatus($ffi->{"zvec_collection_{$operation}"}($this->handle, $arr, $count));
    }

    public function insert(ZVecDoc ...$docs): void { $this->writeDocs('insert', ...$docs); }

    public function upsert(ZVecDoc ...$docs): void { $this->writeDocs('upsert', ...$docs); }

    public function update(ZVecDoc ...$docs): void { $this->writeDocs('update', ...$docs); }

    /**
     * @return array<int, array{pk: string, ok: bool, error: string|null}>
     */
    private function writeDocsBatch(string $operation, ZVecDoc ...$docs): array
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $count = count($docs);
        $arr = $ffi->new("zvec_doc_t[$count]");
        foreach ($docs as $i => $doc) {
            $arr[$i] = $doc->getHandle();
        }

        $result = $ffi->new('zvec_batch_result_t');
        self::checkStatus($ffi->{"zvec_collection_{$operation}_batch"}($this->handle, $arr, $count, FFI::addr($result)));

        $results = [];
        for ($i = 0; $i < $result->count; $i++) {
            $results[] = [
                'pk' => FFI::string($result->doc_pks[$i]),
                'ok' => $result->codes[$i] === 0,
                'error' => $result->messages[$i] !== null ? FFI::string($result->messages[$i]) : null,
            ];
        }

        $ffi->zvec_batch_result_free(FFI::addr($result));
        return $results;
    }

    /**
     * @return array<int, array{pk: string, ok: bool, error: string|null}>
     */
    public function insertBatch(ZVecDoc ...$docs): array { return $this->writeDocsBatch('insert', ...$docs); }

    /**
     * @return array<int, array{pk: string, ok: bool, error: string|null}>
     */
    public function upsertBatch(ZVecDoc ...$docs): array { return $this->writeDocsBatch('upsert', ...$docs); }

    /**
     * @return array<int, array{pk: string, ok: bool, error: string|null}>
     */
    public function updateBatch(ZVecDoc ...$docs): array { return $this->writeDocsBatch('update', ...$docs); }

    public function delete(string ...$pks): void
    {
        $this->checkClosed();
        if (empty($pks)) {
            throw new ZVecException('At least one PK is required');
        }
        $ffi = self::ffi();
        $count = count($pks);
        $cStrings = [];
        $arr = $ffi->new("char*[$count]");
        foreach ($pks as $i => $pk) {
            $len = strlen($pk) + 1;
            $cStr = $ffi->new("char[$len]", false);
            FFI::memcpy($cStr, $pk, strlen($pk));
            $cStr[$len - 1] = "\0";
            $cStrings[] = $cStr;
            $arr[$i] = $cStr;
        }
        try {
            self::checkStatus($ffi->zvec_collection_delete($this->handle, $arr, $count));
        } finally {
            foreach ($cStrings as $cStr) {
                FFI::free($cStr);
            }
        }
    }

    public function deleteByFilter(string $filter): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_delete_by_filter($this->handle, $filter));
    }

    /**
     * @return ZVecDoc[]
     */
    public function fetch(string ...$pks): array
    {
        $this->checkClosed();
        if (empty($pks)) {
            throw new ZVecException('At least one PK is required');
        }
        $ffi = self::ffi();
        $count = count($pks);
        $cStrings = [];
        $arr = $ffi->new("char*[$count]");
        foreach ($pks as $i => $pk) {
            $len = strlen($pk) + 1;
            $cStr = $ffi->new("char[$len]", false);
            FFI::memcpy($cStr, $pk, strlen($pk));
            $cStr[$len - 1] = "\0";
            $cStrings[] = $cStr;
            $arr[$i] = $cStr;
        }

        $result = $ffi->new('zvec_query_result_t');
        $status = $ffi->zvec_collection_fetch($this->handle, $arr, $count, FFI::addr($result));
        foreach ($cStrings as $cStr) {
            FFI::free($cStr);
        }
        self::checkStatus($status);

        return self::parseQueryResult($result);
    }

    // Index types for unified IndexParams API
    public const INDEX_TYPE_HNSW = 1;
    public const INDEX_TYPE_IVF = 2;
    public const INDEX_TYPE_FLAT = 3;
    public const INDEX_TYPE_HNSW_RABITQ = 4;
    public const INDEX_TYPE_VAMANA = 5;
    public const INDEX_TYPE_INVERT = 10;

    public const QUERY_PARAM_NONE = 0;
    public const QUERY_PARAM_HNSW = 1;
    public const QUERY_PARAM_IVF = 2;
    public const QUERY_PARAM_FLAT = 3;
    public const QUERY_PARAM_HNSW_RABITQ = 4;
    public const QUERY_PARAM_VAMANA = 5;

    public const LOG_CONSOLE = 0;
    public const LOG_FILE = 1;

    public const LOG_DEBUG = 0;
    public const LOG_INFO = 1;
    public const LOG_WARN = 2;
    public const LOG_ERROR = 3;
    public const LOG_FATAL = 4;

    // Data types for alterColumn (scalar numeric only)
    public const TYPE_INT32 = 4;
    public const TYPE_INT64 = 5;
    public const TYPE_UINT32 = 6;
    public const TYPE_UINT64 = 7;
    public const TYPE_FLOAT = 8;
    public const TYPE_DOUBLE = 9;
    public const TYPE_BOOL = 3;

    // Vector data types for schema
    public const TYPE_VECTOR_FP32 = 23;
    public const TYPE_VECTOR_FP64 = 24;
    public const TYPE_VECTOR_FP16 = 22;
    public const TYPE_VECTOR_INT4 = 25;
    public const TYPE_VECTOR_INT8 = 26;
    public const TYPE_VECTOR_INT16 = 27;
    public const TYPE_VECTOR_BINARY32 = 20;
    public const TYPE_VECTOR_BINARY64 = 21;
    public const TYPE_SPARSE_VECTOR_FP32 = 31;
    public const TYPE_SPARSE_VECTOR_FP16 = 30;
    public const TYPE_BINARY = 1;
    public const TYPE_ARRAY_STRING = 41;
    public const TYPE_ARRAY_BOOL = 42;
    public const TYPE_ARRAY_INT32 = 43;
    public const TYPE_ARRAY_INT64 = 44;
    public const TYPE_ARRAY_UINT32 = 45;
    public const TYPE_ARRAY_UINT64 = 46;
    public const TYPE_ARRAY_FLOAT = 47;
    public const TYPE_ARRAY_DOUBLE = 48;

    // Quantize types for index creation
    public const QUANTIZE_UNDEFINED = 0;
    public const QUANTIZE_FP16 = 1;
    public const QUANTIZE_INT8 = 2;
    public const QUANTIZE_INT4 = 3;
    public const QUANTIZE_RABITQ = 4;

    public static function init(
        int $logType = self::LOG_CONSOLE,
        int $logLevel = self::LOG_WARN,
        ?string $logDir = null,
        ?string $logBasename = null,
        int $logFileSize = 0,
        int $logOverdueDays = 0,
        int $queryThreads = 0,
        int $optimizeThreads = 0,
        float $invertToForwardScanRatio = 0.0,
        float $bruteForceByKeysRatio = 0.0,
        int $memoryLimitMb = 0
    ): void {
        $ffi = self::ffi();

        $logConfig = $logType === self::LOG_FILE
            ? $ffi->zvec_log_config_create_file($logLevel, $logDir, $logBasename, $logFileSize, $logOverdueDays)
            : $ffi->zvec_log_config_create_console($logLevel);

        $configData = $ffi->zvec_config_data_create();
        $ffi->zvec_config_data_set_log_config($configData, $logConfig);

        if ($queryThreads > 0) {
            $ffi->zvec_config_data_set_query_thread_count($configData, $queryThreads);
        }
        if ($optimizeThreads > 0) {
            $ffi->zvec_config_data_set_optimize_thread_count($configData, $optimizeThreads);
        }
        if ($invertToForwardScanRatio > 0.0) {
            $ffi->zvec_config_data_set_invert_to_forward_scan_ratio($configData, $invertToForwardScanRatio);
        }
        if ($bruteForceByKeysRatio > 0.0) {
            $ffi->zvec_config_data_set_brute_force_by_keys_ratio($configData, $bruteForceByKeysRatio);
        }
        if ($memoryLimitMb > 0) {
            $ffi->zvec_config_data_set_memory_limit($configData, $memoryLimitMb * 1048576);
        }

        try {
            self::checkStatus($ffi->zvec_ffi_initialize($configData));
        } finally {
            $ffi->zvec_log_config_free($logConfig);
            $ffi->zvec_config_data_free($configData);
        }
    }

    public static function isInitialized(): bool
    {
        return self::ffi()->zvec_ffi_is_initialized() !== 0;
    }

    public static function shutdown(): void
    {
        self::ffi()->zvec_ffi_shutdown();
    }

    /**
     * @return array{code: int, message: ?string, file: ?string, line: int, function: ?string}
     */
    public static function getLastErrorDetails(): array
    {
        $ffi = self::ffi();
        $details = $ffi->new('zvec_error_details_t');
        $ffi->zvec_get_last_error_details(FFI::addr($details));
        return [
            'code' => $details->code,
            'message' => $details->message !== null ? FFI::string($details->message) : null,
            'file' => $details->file !== null ? FFI::string($details->file) : null,
            'line' => $details->line,
            'function' => $details->function !== null ? FFI::string($details->function) : null,
        ];
    }

    public static function clearError(): void
    {
        self::ffi()->zvec_clear_error();
    }

    public static function getVersion(): string
    {
        return self::ffi()->zvec_get_version();
    }

    public static function checkVersion(int $major, int $minor, int $patch): bool
    {
        return self::ffi()->zvec_check_version($major, $minor, $patch) !== 0;
    }

    public static function getVersionMajor(): int
    {
        return self::ffi()->zvec_get_version_major();
    }

    public static function getVersionMinor(): int
    {
        return self::ffi()->zvec_get_version_minor();
    }

    public static function getVersionPatch(): int
    {
        return self::ffi()->zvec_get_version_patch();
    }

    /**
     * Query using a native ZVecVectorQuery object.
     *
     * @return ZVecDoc[]
     */
    public function queryVector(ZVecVectorQuery $query): array
    {
        $this->checkClosed();

        $ffi = self::ffi();
        $result = $ffi->new('zvec_query_result_t');
        $status = $ffi->zvec_collection_query_vector($this->handle, $query->getHandle(), FFI::addr($result));
        self::checkStatus($status);

        return self::parseQueryResult($result);
    }

    /**
     * GroupBy query using a native ZVecGroupByVectorQuery object.
     *
     * @return array<array{group_value: string, docs: ZVecDoc[]}>
     */
    public function groupByVectorQuery(ZVecGroupByVectorQuery $query): array
    {
        $this->checkClosed();

        $ffi = self::ffi();
        $result = $ffi->new('zvec_group_results_t');
        $status = $ffi->zvec_collection_group_by_query_vector($this->handle, $query->getHandle(), FFI::addr($result));
        self::checkStatus($status);

        return self::parseGroupResult($result);
    }

    /**
     * @param float[] $queryVector
     * @param string[]|null $outputFields
     * @return ZVecDoc[]|ZVecRerankedDoc[]
     */
    public function query(
        string|ZVecVectorQuery $fieldName,
        array $queryVector = [],
        int $topk = 10,
        bool $includeVector = false,
        ?string $filter = null,
        ?array $outputFields = null,
        int $queryParamType = self::QUERY_PARAM_NONE,
        int $hnswEf = 200,
        int $ivfNprobe = 10,
        float $radius = 0.0,
        bool $isLinear = false,
        bool $isUsingRefiner = false,
        ?ZVecReRanker $reranker = null
    ): array {
        $this->checkClosed();

        // Handle ZVecVectorQuery object
        if ($fieldName instanceof ZVecVectorQuery) {
            $vq = $fieldName;
            $fieldName = $vq->fieldName;
            $queryVector = $vq->vector;
            $queryParamType = $vq->queryParamType;
            $hnswEf = $vq->hnswEf;
            $ivfNprobe = $vq->ivfNprobe;
            $radius = $vq->radius;
            $isLinear = $vq->isLinear;
            $isUsingRefiner = $vq->isUsingRefiner;
            $topk = $vq->topk ?? $topk;
            $includeVector = $vq->includeVector ?? $includeVector;
            $filter = $vq->filter ?? $filter;

            if ($vq->docId !== null) {
                throw new ZVecException("query() with docId not yet implemented. Use queryById() or fetch the vector first.");
            }

            // Route to FP64 query path if flagged
            if ($vq->useFp64) {
                return $this->queryFp64(
                    fieldName: $fieldName,
                    queryVector: $queryVector,
                    topk: $topk,
                    includeVector: $includeVector,
                    filter: $filter,
                    outputFields: $outputFields,
                    queryParamType: $queryParamType,
                    hnswEf: $hnswEf,
                    ivfNprobe: $ivfNprobe,
                    radius: $radius,
                    isLinear: $isLinear,
                    isUsingRefiner: $isUsingRefiner,
                    reranker: $reranker
                );
            }
        }

        if ($topk <= 0) {
            throw new ZVecException("topk must be a positive integer, got: {$topk}");
        }
        if (is_string($fieldName) && $fieldName === '') {
            throw new ZVecException('Field name must not be empty');
        }

        // If reranker is provided, fetch more results for two-stage retrieval
        $fetchTopk = $reranker !== null ? max($topk * 2, 100) : $topk;

        $ffi = self::ffi();
        $dim = count($queryVector);
        $vecData = $ffi->new("float[$dim]");
        foreach ($queryVector as $i => $v) {
            $vecData[$i] = $v;
        }

        $result = $ffi->new('zvec_query_result_t');

        $ofCStrings = [];
        try {
            if ($outputFields !== null || $queryParamType !== self::QUERY_PARAM_NONE) {
                $ofArr = null;
                $ofCount = -1;
                if ($outputFields !== null) {
                    $ofCount = count($outputFields);
                    $ofArr = $ffi->new("char*[$ofCount]");
                    foreach ($outputFields as $i => $f) {
                        $len = strlen($f) + 1;
                        $cStr = $ffi->new("char[$len]", false);
                        FFI::memcpy($cStr, $f, strlen($f));
                        $cStr[$len - 1] = "\0";
                        $ofCStrings[] = $cStr;
                        $ofArr[$i] = $cStr;
                    }
                }

                $status = $ffi->zvec_collection_query_ex(
                    $this->handle, $fieldName, $vecData, $dim,
                    $fetchTopk, $includeVector ? 1 : 0, $filter ?? '',
                    $ofArr, $ofCount,
                    $queryParamType, $hnswEf, $ivfNprobe,
                    $radius, $isLinear ? 1 : 0, $isUsingRefiner ? 1 : 0,
                    FFI::addr($result)
                );
            } else {
                $status = $ffi->zvec_collection_query(
                    $this->handle, $fieldName, $vecData, $dim,
                    $fetchTopk, $includeVector ? 1 : 0, $filter ?? '',
                    FFI::addr($result)
                );
            }
            self::checkStatus($status);
        } finally {
            foreach ($ofCStrings as $cStr) {
                FFI::free($cStr);
            }
        }

        $docs = self::parseQueryResult($result);

        // Apply reranker if provided
        if ($reranker !== null) {
            $queryResults = [$fieldName => $docs];
            return $reranker->rerank($queryResults);
        }

        return $docs;
    }

    /**
     * @param int[] $queryVector
     */
    public function queryFp16(
        string $fieldName,
        array $queryVector,
        int $topk = 10,
        bool $includeVector = false,
        ?string $filter = null
    ): array {
        $this->checkClosed();
        if ($topk <= 0) {
            throw new ZVecException("topk must be a positive integer, got: {$topk}");
        }
        if ($fieldName === '') {
            throw new ZVecException('Field name must not be empty');
        }

        $ffi = self::ffi();
        $dim = count($queryVector);
        $vecData = $ffi->new("uint16_t[$dim]");
        foreach ($queryVector as $i => $v) {
            $vecData[$i] = $v;
        }

        $result = $ffi->new('zvec_query_result_t');
        $status = $ffi->zvec_collection_query_fp16(
            $this->handle, $fieldName, $vecData, $dim,
            $topk, $includeVector ? 1 : 0, $filter ?? '',
            FFI::addr($result)
        );
        self::checkStatus($status);

        return self::parseQueryResult($result);
    }

    /**
     * @param float[] $queryVector
     * @param string[]|null $outputFields
     * @return ZVecDoc[]|ZVecRerankedDoc[]
     */
    public function queryFp64(
        string $fieldName,
        array $queryVector,
        int $topk = 10,
        bool $includeVector = false,
        ?string $filter = null,
        ?array $outputFields = null,
        int $queryParamType = self::QUERY_PARAM_NONE,
        int $hnswEf = 200,
        int $ivfNprobe = 10,
        float $radius = 0.0,
        bool $isLinear = false,
        bool $isUsingRefiner = false,
        ?ZVecReRanker $reranker = null
    ): array {
        $this->checkClosed();
        if ($topk <= 0) {
            throw new ZVecException("topk must be a positive integer, got: {$topk}");
        }
        if ($fieldName === '') {
            throw new ZVecException('Field name must not be empty');
        }

        $ffi = self::ffi();
        $dim = count($queryVector);
        $vecData = $ffi->new("double[$dim]");
        foreach ($queryVector as $i => $v) {
            $vecData[$i] = $v;
        }

        // If reranker is provided, fetch more results for two-stage retrieval
        $fetchTopk = $reranker !== null ? max($topk * 2, 100) : $topk;

        $result = $ffi->new('zvec_query_result_t');

        $ofCStrings = [];
        try {
            if ($outputFields !== null || $queryParamType !== self::QUERY_PARAM_NONE) {
                $ofArr = null;
                $ofCount = -1;
                if ($outputFields !== null) {
                    $ofCount = count($outputFields);
                    $ofArr = $ffi->new("char*[$ofCount]");
                    foreach ($outputFields as $i => $f) {
                        $len = strlen($f) + 1;
                        $cStr = $ffi->new("char[$len]", false);
                        FFI::memcpy($cStr, $f, strlen($f));
                        $cStr[$len - 1] = "\0";
                        $ofCStrings[] = $cStr;
                        $ofArr[$i] = $cStr;
                    }
                }

                $status = $ffi->zvec_collection_query_fp64_ex(
                    $this->handle, $fieldName, $vecData, $dim,
                    $fetchTopk, $includeVector ? 1 : 0, $filter ?? '',
                    $ofArr, $ofCount,
                    $queryParamType, $hnswEf, $ivfNprobe,
                    $radius, $isLinear ? 1 : 0, $isUsingRefiner ? 1 : 0,
                    FFI::addr($result)
                );
            } else {
                $status = $ffi->zvec_collection_query_fp64(
                    $this->handle, $fieldName, $vecData, $dim,
                    $fetchTopk, $includeVector ? 1 : 0, $filter ?? '',
                    FFI::addr($result)
                );
            }
            self::checkStatus($status);
        } finally {
            foreach ($ofCStrings as $cStr) {
                FFI::free($cStr);
            }
        }

        $docs = self::parseQueryResult($result);

        // Apply reranker if provided
        if ($reranker !== null) {
            $queryResults = [$fieldName => $docs];
            return $reranker->rerank($queryResults);
        }

        return $docs;
    }

    /**
     * Multi-vector query - search across multiple vector fields simultaneously.
     *
     * Executes queries against multiple vector fields and merges results using
     * a reranker algorithm (RRF or Weighted). This enables hybrid search scenarios
     * like combining dense and sparse embeddings, or fusing multiple embedding types.
     *
     * @param ZVecVectorQuery[] $vectorQueries Array of vector queries, one per field
     * @param ZVecReRanker $reranker Reranker for merging results (required)
     * @param int $topk Number of top results to return after reranking
     * @param string|null $filter Optional filter expression applied to all queries
     * @param string[]|null $outputFields Fields to include in returned documents
     * @return ZVecRerankedDoc[] Reranked results sorted by combined score
     */
    public function queryMulti(
        array $vectorQueries,
        ZVecReRanker $reranker,
        int $topk = 10,
        ?string $filter = null,
        ?array $outputFields = null
    ): array {
        $this->checkClosed();

        if (empty($vectorQueries)) {
            throw new ZVecException("At least one vector query is required");
        }

        // Execute each vector query individually
        $queryResults = [];
        foreach ($vectorQueries as $vq) {
            if (!($vq instanceof ZVecVectorQuery)) {
                throw new ZVecException("All queries must be ZVecVectorQuery instances");
            }

            // For multi-vector, fetch more candidates to give reranker enough data
            $fetchTopk = max($topk * 2, 100);

            $docs = $this->query(
                fieldName: $vq,
                queryVector: [],
                topk: $fetchTopk,
                includeVector: false,
                filter: $filter,
                outputFields: $outputFields,
                queryParamType: $vq->queryParamType,
                hnswEf: $vq->hnswEf,
                ivfNprobe: $vq->ivfNprobe,
                radius: $vq->radius,
                isLinear: $vq->isLinear,
                isUsingRefiner: $vq->isUsingRefiner,
                reranker: null // Don't rerank individual queries
            );

            $queryResults[$vq->fieldName] = $docs;
        }

        // Apply reranker to merge results
        return $reranker->rerank($queryResults);
    }

    /**
     * @param string[]|null $outputFields
     * @return ZVecDoc[]
     */
    public function queryByFilter(string $filter, int $topk = 100, ?array $outputFields = null): array
    {
        $this->checkClosed();
        if ($topk <= 0) {
            throw new ZVecException("topk must be a positive integer, got: {$topk}");
        }
        $ffi = self::ffi();
        $result = $ffi->new('zvec_query_result_t');

        $ofCStrings = [];
        try {
            if ($outputFields !== null) {
                $ofCount = count($outputFields);
                $ofArr = $ffi->new("char*[$ofCount]");
                foreach ($outputFields as $i => $f) {
                    $len = strlen($f) + 1;
                    $cStr = $ffi->new("char[$len]", false);
                    FFI::memcpy($cStr, $f, strlen($f));
                    $cStr[$len - 1] = "\0";
                    $ofCStrings[] = $cStr;
                    $ofArr[$i] = $cStr;
                }

                $status = $ffi->zvec_collection_query_filter_ex(
                    $this->handle, $filter, $topk,
                    $ofArr, $ofCount,
                    FFI::addr($result)
                );
            } else {
                $status = $ffi->zvec_collection_query_filter($this->handle, $filter, $topk, FFI::addr($result));
            }
            self::checkStatus($status);
        } finally {
            foreach ($ofCStrings as $cStr) {
                FFI::free($cStr);
            }
        }

        $docs = self::parseQueryResult($result);

        return $docs;
    }

    /**
     * Query by document ID - find similar documents using an existing document's embedding.
     *
     * @param string $fieldName Vector field name to use for similarity
     * @param string $docId Document ID to use as the query source
     * @param int $topk Number of results to return
     * @param bool $includeVector Whether to include vector data in results
     * @param string|null $filter Optional filter expression
     * @param string[]|null $outputFields Fields to include in results
     * @param int $queryParamType Query parameter type (QUERY_PARAM_NONE, QUERY_PARAM_HNSW, etc.)
     * @param int $hnswEf HNSW ef parameter
     * @param int $ivfNprobe IVF nprobe parameter
     * @param float $radius Search radius for range queries
     * @param bool $isLinear Use linear search
     * @param bool $isUsingRefiner Use refiner
     * @return ZVecDoc[]
     */
    public function queryById(
        string $fieldName,
        string $docId,
        int $topk = 10,
        bool $includeVector = false,
        ?string $filter = null,
        ?array $outputFields = null,
        int $queryParamType = self::QUERY_PARAM_NONE,
        int $hnswEf = 200,
        int $ivfNprobe = 10,
        float $radius = 0.0,
        bool $isLinear = false,
        bool $isUsingRefiner = false
    ): array {
        $this->checkClosed();
        if ($topk <= 0) {
            throw new ZVecException("topk must be a positive integer, got: {$topk}");
        }
        if ($fieldName === '') {
            throw new ZVecException('Field name must not be empty');
        }
        if ($docId === '') {
            throw new ZVecException('Document ID must not be empty');
        }

        $docs = $this->fetch($docId);
        if (empty($docs)) {
            throw new ZVecException("Document not found: $docId");
        }

        $vector = $docs[0]->getVectorFp32($fieldName);
        $isFp64 = false;
        if ($vector === null) {
            $vector = $docs[0]->getVectorFp64($fieldName);
            $isFp64 = $vector !== null;
        }
        if ($vector === null) {
            throw new ZVecException("Vector field '$fieldName' not found in document: $docId");
        }

        if ($isFp64) {
            return $this->queryFp64(
                $fieldName, $vector, $topk, $includeVector, $filter,
                $outputFields, $queryParamType, $hnswEf, $ivfNprobe,
                $radius, $isLinear, $isUsingRefiner
            );
        }

        return $this->query(
            $fieldName,
            $vector,
            $topk,
            $includeVector,
            $filter,
            $outputFields,
            $queryParamType,
            $hnswEf,
            $ivfNprobe,
            $radius,
            $isLinear,
            $isUsingRefiner
        );
    }

    /**
     * @param float[] $queryVector
     * @param string[]|null $outputFields
     * @return array<array{group_value: string, docs: ZVecDoc[]}>
     */
    public function groupByQuery(
        string|ZVecVectorQuery $fieldName,
        array $queryVector,
        string $groupByField,
        int $groupCount = 2,
        int $groupTopk = 3,
        bool $includeVector = false,
        ?string $filter = null,
        ?array $outputFields = null,
        int $queryParamType = self::QUERY_PARAM_NONE,
        int $hnswEf = 200,
        int $ivfNprobe = 10,
        float $radius = 0.0,
        bool $isLinear = false,
        bool $isUsingRefiner = false
    ): array {
        $this->checkClosed();

        // Handle ZVecVectorQuery object
        if ($fieldName instanceof ZVecVectorQuery) {
            $vq = $fieldName;
            $fieldName = $vq->fieldName;
            $queryVector = $vq->vector;
            $queryParamType = $vq->queryParamType;
            $hnswEf = $vq->hnswEf;
            $ivfNprobe = $vq->ivfNprobe;
            $radius = $vq->radius;
            $isLinear = $vq->isLinear;
            $isUsingRefiner = $vq->isUsingRefiner;
            $includeVector = $vq->includeVector ?? $includeVector;
            $filter = $vq->filter ?? $filter;

            if ($vq->docId !== null) {
                throw new ZVecException("groupByQuery() with docId not yet implemented. Use queryById() or fetch the vector first.");
            }
        }

        if ($groupCount <= 0) {
            throw new ZVecException("groupCount must be a positive integer, got: {$groupCount}");
        }
        if ($groupTopk <= 0) {
            throw new ZVecException("groupTopk must be a positive integer, got: {$groupTopk}");
        }
        if (is_string($fieldName) && $fieldName === '') {
            throw new ZVecException('Field name must not be empty');
        }
        if ($groupByField === '') {
            throw new ZVecException('Group by field must not be empty');
        }

        $ffi = self::ffi();
        $dim = count($queryVector);
        $vecData = $ffi->new("float[$dim]");
        foreach ($queryVector as $i => $v) {
            $vecData[$i] = $v;
        }

        $ofArr = null;
        $ofCount = -1;
        $ofCStrings = [];
        $result = $ffi->new('zvec_group_results_t');
        try {
            if ($outputFields !== null) {
                $ofCount = count($outputFields);
                $ofArr = $ffi->new("char*[$ofCount]");
                foreach ($outputFields as $i => $f) {
                    $len = strlen($f) + 1;
                    $cStr = $ffi->new("char[$len]", false);
                    FFI::memcpy($cStr, $f, strlen($f));
                    $cStr[$len - 1] = "\0";
                    $ofCStrings[] = $cStr;
                    $ofArr[$i] = $cStr;
                }
            }
            $status = $ffi->zvec_collection_group_by_query(
                $this->handle, $fieldName, $vecData, $dim,
                $groupByField, $groupCount, $groupTopk,
                $includeVector ? 1 : 0, $filter ?? '',
                $ofArr, $ofCount,
                $queryParamType, $hnswEf, $ivfNprobe,
                $radius, $isLinear ? 1 : 0, $isUsingRefiner ? 1 : 0,
                FFI::addr($result)
            );
            self::checkStatus($status);
        } finally {
            foreach ($ofCStrings as $cStr) {
                FFI::free($cStr);
            }
        }

        $groups = self::parseGroupResult($result);

        return $groups;
    }

    public function stats(): string
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $buf = $ffi->new('char[4096]');
        self::checkStatus($ffi->zvec_collection_stats($this->handle, $buf, 4096));
        return FFI::string($buf);
    }

    /**
     * Get structured collection stats.
     */
    public function getStatsStruct(): ZVecCollectionStats
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $out = $ffi->new('zvec_collection_stats_t');
        self::checkStatus($ffi->zvec_collection_get_stats_struct($this->handle, FFI::addr($out)));
        return new ZVecCollectionStats($out);
    }

    /**
     * Get structured field schema for a specific field.
     */
    public function getFieldSchema(string $fieldName): ZVecFieldSchema
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $out = $ffi->new('zvec_field_schema_t');
        self::checkStatus($ffi->zvec_collection_get_field_schema($this->handle, $fieldName, FFI::addr($out)));
        return new ZVecFieldSchema($out);
    }
}
