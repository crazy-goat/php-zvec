#ifndef ZVEC_FFI_H
#define ZVEC_FFI_H

#include <stdint.h>
#include <stddef.h>

#ifdef __cplusplus
extern "C" {
#endif

typedef void* zvec_collection_t;
typedef void* zvec_schema_t;
typedef void* zvec_doc_t;

typedef struct {
    int code;
    char message[512];
} zvec_status_t;

// Batch operation result - per-document status
typedef struct {
    int count;
    int* codes;           // Array of status codes (0 = success)
    char** messages;      // Array of messages (NULL if success)
    char** doc_pks;       // Array of document primary keys
} zvec_batch_result_t;

// Global init (call once before any other operation)
// log_type: 0=console, 1=file
// log_level: 0=DEBUG, 1=INFO, 2=WARN, 3=ERROR, 4=FATAL
// Pass 0 for numeric params to use defaults, NULL for string params
zvec_status_t zvec_init(int log_type, int log_level,
                        const char* log_dir, const char* log_basename,
                        uint32_t log_file_size, uint32_t log_overdue_days,
                        uint32_t query_threads, uint32_t optimize_threads,
                        float invert_to_forward_scan_ratio,
                        float brute_force_by_keys_ratio,
                        uint64_t memory_limit_mb);

typedef struct {
    zvec_doc_t* docs;
    int count;
} zvec_query_result_t;

// Schema builder
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
void zvec_schema_add_field_vector_int8(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type);
void zvec_schema_add_field_sparse_vector_fp32(zvec_schema_t schema, const char* name, uint32_t metric_type);
void zvec_schema_add_field_sparse_vector_fp32(zvec_schema_t schema, const char* name, uint32_t metric_type);

// Collection lifecycle
zvec_status_t zvec_collection_create(const char* path, zvec_schema_t schema, int read_only, int enable_mmap, uint32_t max_buffer_size, zvec_collection_t* out);
zvec_status_t zvec_collection_open(const char* path, int read_only, int enable_mmap, uint32_t max_buffer_size, zvec_collection_t* out);
void zvec_collection_free(zvec_collection_t coll);
zvec_status_t zvec_collection_flush(zvec_collection_t coll);
zvec_status_t zvec_collection_optimize(zvec_collection_t coll, uint32_t concurrency);
zvec_status_t zvec_collection_destroy(zvec_collection_t coll);

// Collection inspect
zvec_status_t zvec_collection_stats(zvec_collection_t coll, char* buf, size_t buf_size);
zvec_status_t zvec_collection_schema(zvec_collection_t coll, char* buf, size_t buf_size);
zvec_status_t zvec_collection_path(zvec_collection_t coll, char* buf, size_t buf_size);
zvec_status_t zvec_collection_options(zvec_collection_t coll, int* read_only, int* enable_mmap, uint32_t* max_buffer_size);

// Schema evolution - Column DDL
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

// Alter column with field schema support (rename + optional type change)
// data_type: 4=INT32, 5=INT64, 6=UINT32, 7=UINT64, 8=FLOAT, 9=DOUBLE
// nullable: 0=false, 1=true
// new_name: can be NULL or empty for no rename
zvec_status_t zvec_collection_alter_column(zvec_collection_t coll, const char* column_name, const char* new_name, uint32_t data_type, int nullable, uint32_t concurrency);

// Schema evolution - Index DDL
zvec_status_t zvec_collection_create_invert_index(zvec_collection_t coll, const char* field_name, int enable_range, int enable_wildcard);
zvec_status_t zvec_collection_create_hnsw_index(zvec_collection_t coll, const char* field_name, uint32_t metric_type, int m, int ef_construction, uint32_t quantize_type, uint32_t concurrency);
zvec_status_t zvec_collection_create_flat_index(zvec_collection_t coll, const char* field_name, uint32_t metric_type, uint32_t quantize_type, uint32_t concurrency);
zvec_status_t zvec_collection_create_ivf_index(zvec_collection_t coll, const char* field_name, uint32_t metric_type, int n_list, int n_iters, int use_soar, uint32_t quantize_type, uint32_t concurrency);
zvec_status_t zvec_collection_drop_index(zvec_collection_t coll, const char* field_name);

// Doc
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
void zvec_doc_set_vector_int8(zvec_doc_t doc, const char* field, const int8_t* data, uint32_t dim);

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
int zvec_doc_get_vector_int8(zvec_doc_t doc, const char* field, const int8_t** out, uint32_t* dim);

// Doc introspection
int zvec_doc_has_field(zvec_doc_t doc, const char* field);
int zvec_doc_has_vector(zvec_doc_t doc, const char* field);
int zvec_doc_field_names(zvec_doc_t doc, char* buf, size_t buf_size);
int zvec_doc_vector_names(zvec_doc_t doc, char* buf, size_t buf_size);

// Insert / Upsert / Update / Delete
zvec_status_t zvec_collection_insert(zvec_collection_t coll, zvec_doc_t* docs, int count);
zvec_status_t zvec_collection_upsert(zvec_collection_t coll, zvec_doc_t* docs, int count);
zvec_status_t zvec_collection_update(zvec_collection_t coll, zvec_doc_t* docs, int count);
zvec_status_t zvec_collection_delete(zvec_collection_t coll, const char** pks, int count);
zvec_status_t zvec_collection_delete_by_filter(zvec_collection_t coll, const char* filter);

// Batch operations with per-document status
zvec_status_t zvec_collection_insert_batch(zvec_collection_t coll, zvec_doc_t* docs, int count, zvec_batch_result_t* result);
zvec_status_t zvec_collection_upsert_batch(zvec_collection_t coll, zvec_doc_t* docs, int count, zvec_batch_result_t* result);
zvec_status_t zvec_collection_update_batch(zvec_collection_t coll, zvec_doc_t* docs, int count, zvec_batch_result_t* result);
void zvec_batch_result_free(zvec_batch_result_t* result);

// Fetch
zvec_status_t zvec_collection_fetch(zvec_collection_t coll, const char** pks, int count, zvec_query_result_t* result);

// Query
zvec_status_t zvec_collection_query(zvec_collection_t coll, const char* field_name,
                                     const float* query_vector, uint32_t dim,
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
zvec_status_t zvec_collection_query_filter(zvec_collection_t coll, const char* filter,
                                            int topk, zvec_query_result_t* result);
zvec_status_t zvec_collection_query_filter_ex(zvec_collection_t coll, const char* filter,
                                               int topk,
                                               const char** output_fields, int output_fields_count,
                                               zvec_query_result_t* result);

// GroupBy Query
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

void zvec_query_result_free(zvec_query_result_t* result);
void zvec_query_result_free_array(zvec_query_result_t* result);

#ifdef __cplusplus
}
#endif

#endif
