#ifndef ZVEC_FFI_PHP_H
#define ZVEC_FFI_PHP_H

/*
 * PHP FFI header for zvec.
 *
 * This file MUST stay in sync with ffi/zvec_ffi.h.
 * It contains the same C declarations used by PHP FFI at runtime.
 *
 * To regenerate after C++ header changes:
 *   cp ffi/zvec_ffi.h ffi/zvec_ffi_php.h
 *   # Then remove #ifdef __cplusplus and comments
 */

/* FFI_LIB is set dynamically at runtime via FFI::cdef() second argument */

#include <stdint.h>
#include <stddef.h>

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

typedef struct {
    zvec_doc_t* docs;
    int count;
} zvec_query_result_t;

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

zvec_status_t zvec_collection_stats(zvec_collection_t coll, char* buf, size_t buf_size);
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

int zvec_doc_has_field(zvec_doc_t doc, const char* field);
int zvec_doc_has_vector(zvec_doc_t doc, const char* field);
int zvec_doc_field_names(zvec_doc_t doc, char* buf, size_t buf_size);
int zvec_doc_vector_names(zvec_doc_t doc, char* buf, size_t buf_size);

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

typedef void* zvec_collection_stats_t;

zvec_status_t zvec_collection_get_stats_struct(zvec_collection_t coll, zvec_collection_stats_t* out);
void zvec_collection_stats_free(zvec_collection_stats_t stats);

uint64_t zvec_collection_stats_get_doc_count(zvec_collection_stats_t stats);
uint32_t zvec_collection_stats_get_index_count(zvec_collection_stats_t stats);
const char* zvec_collection_stats_get_index_name(zvec_collection_stats_t stats, uint32_t index);
float zvec_collection_stats_get_index_completeness(zvec_collection_stats_t stats, uint32_t index);

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

#endif
