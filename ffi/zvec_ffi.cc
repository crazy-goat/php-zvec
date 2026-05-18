#include "zvec_ffi.h"

#include <array>
#include <cstring>
#include <string>
#include <vector>
#include <zvec/db/collection.h>
#include <zvec/db/doc.h>
#include <zvec/db/index_params.h>
#include <zvec/db/options.h>
#include <zvec/db/schema.h>
#include <zvec/db/config.h>
#include <zvec/db/status.h>
#include <zvec/ailego/utility/float_helper.h>

using namespace zvec;

static zvec_status_t make_status(const Status& s) {
    zvec_status_t st;
    st.code = static_cast<int>(s.code());
    strncpy(st.message, s.message().c_str(), sizeof(st.message) - 1);
    st.message[sizeof(st.message) - 1] = '\0';
    return st;
}

static zvec_status_t ok_status() {
    zvec_status_t st;
    st.code = 0;
    st.message[0] = '\0';
    return st;
}

// Thread-local error details storage for enhanced error reporting
static thread_local struct {
    int code;
    const char* file;
    int line;
    const char* function;
} g_last_error;

static thread_local std::string g_last_error_message;

static void set_last_error(int code, const char* msg, const char* file, int line, const char* func) {
    g_last_error.code = code;
    g_last_error_message = msg ? msg : "";
    g_last_error.file = file;
    g_last_error.line = line;
    g_last_error.function = func;
}

// MAKE_STATUS wraps make_status and captures source location on error
#define MAKE_STATUS(s) \
    ([&]() -> zvec_status_t { \
        auto _st = make_status(s); \
        if (_st.code != 0) { \
            set_last_error(_st.code, _st.message, __FILE__, __LINE__, __func__); \
        } \
        return _st; \
    })()

// Set error details directly for manually constructed error statuses
#define SET_FFI_ERROR(st) \
    do { \
        if ((st).code != 0) { \
            set_last_error((st).code, (st).message, __FILE__, __LINE__, __func__); \
        } \
    } while(0)

int zvec_get_last_error_details(zvec_error_details_t* out) {
    if (!out) return 1;  // ZVEC_ERROR_INVALID_ARGUMENT
    out->code = g_last_error.code;
    out->message = g_last_error.code != 0 ? g_last_error_message.c_str() : nullptr;
    out->file = g_last_error.file;
    out->line = g_last_error.line;
    out->function = g_last_error.function;
    return 0;  // ZVEC_OK
}

void zvec_clear_error(void) {
    g_last_error = {};
    g_last_error_message.clear();
}

const char* zvec_error_code_to_string(int error_code) {
    switch (error_code) {
        case 0: return "OK";
        case 1: return "NOT_FOUND";
        case 2: return "ALREADY_EXISTS";
        case 3: return "INVALID_ARGUMENT";
        case 4: return "PERMISSION_DENIED";
        case 5: return "FAILED_PRECONDITION";
        case 6: return "RESOURCE_EXHAUSTED";
        case 7: return "UNAVAILABLE";
        case 8: return "INTERNAL_ERROR";
        case 9: return "NOT_SUPPORTED";
        case 10: return "UNKNOWN";
        default: return "UNRECOGNIZED";
    }
}

static MetricType to_metric_type(uint32_t v) {
    switch (v) {
        case 1: return MetricType::L2;
        case 2: return MetricType::IP;
        case 3: return MetricType::COSINE;
        default: return MetricType::IP;
    }
}

static QuantizeType to_quantize_type(uint32_t v) {
    switch (v) {
        case 0: return QuantizeType::UNDEFINED;
        case 1: return QuantizeType::FP16;
        case 2: return QuantizeType::INT8;
        case 3: return QuantizeType::INT4;
        default: return QuantizeType::UNDEFINED;
    }
}

// --- Init ---

zvec_status_t zvec_init(int log_type, int log_level,
                        const char* log_dir, const char* log_basename,
                        uint32_t log_file_size, uint32_t log_overdue_days,
                        uint32_t query_threads, uint32_t optimize_threads,
                        float invert_to_forward_scan_ratio,
                        float brute_force_by_keys_ratio,
                        uint64_t memory_limit_mb) {
    GlobalConfig::ConfigData config;

    auto lvl = static_cast<GlobalConfig::LogLevel>(log_level);

    if (log_type == 1) {
        config.log_config = std::make_shared<GlobalConfig::FileLogConfig>(
            lvl,
            log_dir ? log_dir : DEFAULT_LOG_DIR,
            log_basename ? log_basename : DEFAULT_LOG_BASENAME,
            log_file_size > 0 ? log_file_size : DEFAULT_LOG_FILE_SIZE,
            log_overdue_days > 0 ? log_overdue_days : DEFAULT_LOG_OVERDUE_DAYS
        );
    } else {
        config.log_config = std::make_shared<GlobalConfig::ConsoleLogConfig>(lvl);
    }

    if (query_threads > 0) config.query_thread_count = query_threads;
    if (optimize_threads > 0) config.optimize_thread_count = optimize_threads;
    if (invert_to_forward_scan_ratio > 0.0f) config.invert_to_forward_scan_ratio = invert_to_forward_scan_ratio;
    if (brute_force_by_keys_ratio > 0.0f) config.brute_force_by_keys_ratio = brute_force_by_keys_ratio;
    if (memory_limit_mb > 0) config.memory_limit_bytes = memory_limit_mb * 1024ULL * 1024ULL;

    auto& gc = GlobalConfig::Instance();
    return MAKE_STATUS(gc.Initialize(config));
}

// --- Schema ---

zvec_schema_t zvec_schema_create(const char* name) {
    auto* schema = new CollectionSchema(name);
    return static_cast<zvec_schema_t>(schema);
}

void zvec_schema_free(zvec_schema_t schema) {
    delete static_cast<CollectionSchema*>(schema);
}

void zvec_schema_set_max_doc_count_per_segment(zvec_schema_t schema, uint64_t count) {
    static_cast<CollectionSchema*>(schema)->set_max_doc_count_per_segment(count);
}

void zvec_schema_add_field_int64(zvec_schema_t schema, const char* name, int nullable, int with_invert_index) {
    auto* s = static_cast<CollectionSchema*>(schema);
    if (with_invert_index) {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::INT64, (bool)nullable, std::make_shared<InvertIndexParams>(true)));
    } else {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::INT64, (bool)nullable));
    }
}

void zvec_schema_add_field_string(zvec_schema_t schema, const char* name, int nullable, int with_invert_index) {
    auto* s = static_cast<CollectionSchema*>(schema);
    if (with_invert_index) {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::STRING, (bool)nullable, std::make_shared<InvertIndexParams>(false)));
    } else {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::STRING, (bool)nullable));
    }
}

void zvec_schema_add_field_float(zvec_schema_t schema, const char* name, int nullable) {
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::FLOAT, (bool)nullable));
}

void zvec_schema_add_field_double(zvec_schema_t schema, const char* name, int nullable) {
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::DOUBLE, (bool)nullable));
}

void zvec_schema_add_field_bool(zvec_schema_t schema, const char* name, int nullable, int with_invert_index) {
    auto* s = static_cast<CollectionSchema*>(schema);
    if (with_invert_index) {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::BOOL, (bool)nullable, std::make_shared<InvertIndexParams>(true)));
    } else {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::BOOL, (bool)nullable));
    }
}

void zvec_schema_add_field_int32(zvec_schema_t schema, const char* name, int nullable, int with_invert_index) {
    auto* s = static_cast<CollectionSchema*>(schema);
    if (with_invert_index) {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::INT32, (bool)nullable, std::make_shared<InvertIndexParams>(true)));
    } else {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::INT32, (bool)nullable));
    }
}

void zvec_schema_add_field_uint32(zvec_schema_t schema, const char* name, int nullable, int with_invert_index) {
    auto* s = static_cast<CollectionSchema*>(schema);
    if (with_invert_index) {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::UINT32, (bool)nullable, std::make_shared<InvertIndexParams>(true)));
    } else {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::UINT32, (bool)nullable));
    }
}

void zvec_schema_add_field_uint64(zvec_schema_t schema, const char* name, int nullable, int with_invert_index) {
    auto* s = static_cast<CollectionSchema*>(schema);
    if (with_invert_index) {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::UINT64, (bool)nullable, std::make_shared<InvertIndexParams>(true)));
    } else {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::UINT64, (bool)nullable));
    }
}

void zvec_schema_add_field_vector_fp32(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type) {
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::VECTOR_FP32, dimension, false,
        std::make_shared<HnswIndexParams>(to_metric_type(metric_type))));
}

void zvec_schema_add_field_vector_fp64(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type) {
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::VECTOR_FP64, dimension, false,
        std::make_shared<HnswIndexParams>(to_metric_type(metric_type))));
}

void zvec_schema_add_field_sparse_vector_fp32(zvec_schema_t schema, const char* name, uint32_t metric_type) {
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::SPARSE_VECTOR_FP32, 0, false,
        std::make_shared<HnswIndexParams>(to_metric_type(metric_type))));
}

void zvec_schema_add_field_vector_int8(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type) {
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::VECTOR_INT8, dimension, false,
        std::make_shared<HnswIndexParams>(to_metric_type(metric_type))));
}

void zvec_schema_add_field_vector_fp16(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type) {
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::VECTOR_FP16, dimension, false,
        std::make_shared<HnswIndexParams>(to_metric_type(metric_type))));
}

void zvec_schema_add_field_vector_int4(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type) {
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::VECTOR_INT4, dimension, false,
        std::make_shared<HnswIndexParams>(to_metric_type(metric_type))));
}

void zvec_schema_add_field_vector_int16(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type) {
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::VECTOR_INT16, dimension, false,
        std::make_shared<HnswIndexParams>(to_metric_type(metric_type))));
}

void zvec_schema_add_field_vector_binary32(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type) {
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::VECTOR_BINARY32, dimension, false,
        std::make_shared<HnswIndexParams>(to_metric_type(metric_type))));
}

void zvec_schema_add_field_vector_binary64(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type) {
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::VECTOR_BINARY64, dimension, false,
        std::make_shared<HnswIndexParams>(to_metric_type(metric_type))));
}

void zvec_schema_add_field_sparse_vector_fp16(zvec_schema_t schema, const char* name, uint32_t metric_type) {
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::SPARSE_VECTOR_FP16, 0, false,
        std::make_shared<HnswIndexParams>(to_metric_type(metric_type))));
}

void zvec_schema_add_field_binary(zvec_schema_t schema, const char* name, int nullable) {
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::BINARY, (bool)nullable));
}

void zvec_schema_add_field_array_string(zvec_schema_t schema, const char* name, int nullable) {
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::ARRAY_STRING, (bool)nullable));
}

void zvec_schema_add_field_array_bool(zvec_schema_t schema, const char* name, int nullable) {
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::ARRAY_BOOL, (bool)nullable));
}

void zvec_schema_add_field_array_int32(zvec_schema_t schema, const char* name, int nullable) {
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::ARRAY_INT32, (bool)nullable));
}

void zvec_schema_add_field_array_int64(zvec_schema_t schema, const char* name, int nullable) {
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::ARRAY_INT64, (bool)nullable));
}

void zvec_schema_add_field_array_uint32(zvec_schema_t schema, const char* name, int nullable) {
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::ARRAY_UINT32, (bool)nullable));
}

void zvec_schema_add_field_array_uint64(zvec_schema_t schema, const char* name, int nullable) {
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::ARRAY_UINT64, (bool)nullable));
}

void zvec_schema_add_field_array_float(zvec_schema_t schema, const char* name, int nullable) {
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::ARRAY_FLOAT, (bool)nullable));
}

void zvec_schema_add_field_array_double(zvec_schema_t schema, const char* name, int nullable) {
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::ARRAY_DOUBLE, (bool)nullable));
}

// --- Collection ---

static std::vector<Collection::Ptr> g_collections;

zvec_status_t zvec_collection_create(const char* path, zvec_schema_t schema, int read_only, int enable_mmap, uint32_t max_buffer_size, zvec_collection_t* out) {
    auto* s = static_cast<CollectionSchema*>(schema);
    CollectionOptions opts{(bool)read_only, (bool)enable_mmap, max_buffer_size};
    auto result = Collection::CreateAndOpen(path, *s, opts);
    if (!result.has_value()) {
        *out = nullptr;
        return MAKE_STATUS(result.error());
    }
    auto ptr = std::move(result).value();
    auto* raw = ptr.get();
    g_collections.push_back(std::move(ptr));
    *out = static_cast<zvec_collection_t>(raw);
    return ok_status();
}

zvec_status_t zvec_collection_open(const char* path, int read_only, int enable_mmap, uint32_t max_buffer_size, zvec_collection_t* out) {
    CollectionOptions opts{(bool)read_only, (bool)enable_mmap, max_buffer_size};
    auto result = Collection::Open(path, opts);
    if (!result.has_value()) {
        *out = nullptr;
        return MAKE_STATUS(result.error());
    }
    auto ptr = std::move(result).value();
    auto* raw = ptr.get();
    g_collections.push_back(std::move(ptr));
    *out = static_cast<zvec_collection_t>(raw);
    return ok_status();
}

void zvec_collection_free(zvec_collection_t coll) {
    auto* raw = static_cast<Collection*>(coll);
    for (auto it = g_collections.begin(); it != g_collections.end(); ++it) {
        if (it->get() == raw) {
            g_collections.erase(it);
            return;
        }
    }
}

zvec_status_t zvec_collection_flush(zvec_collection_t coll) {
    auto* c = static_cast<Collection*>(coll);
    return MAKE_STATUS(c->Flush());
}

zvec_status_t zvec_collection_optimize(zvec_collection_t coll, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    OptimizeOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->Optimize(opts));
}

zvec_status_t zvec_collection_destroy(zvec_collection_t coll) {
    auto* raw = static_cast<Collection*>(coll);
    for (auto it = g_collections.begin(); it != g_collections.end(); ++it) {
        if (it->get() == raw) {
            auto status = raw->Destroy();
            g_collections.erase(it);
            return MAKE_STATUS(status);
        }
    }
    zvec_status_t st;
    st.code = 1;
    strncpy(st.message, "collection not found", sizeof(st.message) - 1);
    st.message[sizeof(st.message) - 1] = '\0';
    SET_FFI_ERROR(st);
    return st;
}

// --- Inspect ---

zvec_status_t zvec_collection_schema(zvec_collection_t coll, char* buf, size_t buf_size) {
    auto* c = static_cast<Collection*>(coll);
    auto res = c->Schema();
    if (!res.has_value()) {
        return MAKE_STATUS(res.error());
    }
    auto str = res.value().to_string();
    strncpy(buf, str.c_str(), buf_size - 1);
    buf[buf_size - 1] = '\0';
    return ok_status();
}

zvec_status_t zvec_collection_path(zvec_collection_t coll, char* buf, size_t buf_size) {
    auto* c = static_cast<Collection*>(coll);
    auto res = c->Path();
    if (!res.has_value()) {
        return MAKE_STATUS(res.error());
    }
    strncpy(buf, res.value().c_str(), buf_size - 1);
    buf[buf_size - 1] = '\0';
    return ok_status();
}

zvec_status_t zvec_collection_options(zvec_collection_t coll, int* read_only, int* enable_mmap, uint32_t* max_buffer_size) {
    auto* c = static_cast<Collection*>(coll);
    auto res = c->Options();
    if (!res.has_value()) {
        return MAKE_STATUS(res.error());
    }
    *read_only = res.value().read_only_ ? 1 : 0;
    *enable_mmap = res.value().enable_mmap_ ? 1 : 0;
    *max_buffer_size = res.value().max_buffer_size_;
    return ok_status();
}

// --- Schema Evolution ---
// Flush before column DDL to ensure delete store files are persisted.
// Without this, zvec's delete store numbering can get out of sync
// when column DDL triggers internal segment compaction after deletes.

zvec_status_t zvec_collection_add_column_int64(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    c->Flush();
    auto field = std::make_shared<FieldSchema>(name, DataType::INT64, (bool)nullable);
    AddColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->AddColumn(field, default_expr ? default_expr : "0", opts));
}

zvec_status_t zvec_collection_add_column_float(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    c->Flush();
    auto field = std::make_shared<FieldSchema>(name, DataType::FLOAT, (bool)nullable);
    AddColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->AddColumn(field, default_expr ? default_expr : "0", opts));
}

zvec_status_t zvec_collection_add_column_double(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    c->Flush();
    auto field = std::make_shared<FieldSchema>(name, DataType::DOUBLE, (bool)nullable);
    AddColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->AddColumn(field, default_expr ? default_expr : "0", opts));
}

zvec_status_t zvec_collection_add_column_string(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    c->Flush();
    auto field = std::make_shared<FieldSchema>(name, DataType::STRING, (bool)nullable);
    AddColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->AddColumn(field, default_expr ? default_expr : "", opts));
}

zvec_status_t zvec_collection_add_column_bool(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    c->Flush();
    auto field = std::make_shared<FieldSchema>(name, DataType::BOOL, (bool)nullable);
    AddColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->AddColumn(field, default_expr ? default_expr : "false", opts));
}

zvec_status_t zvec_collection_add_column_int32(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    c->Flush();
    auto field = std::make_shared<FieldSchema>(name, DataType::INT32, (bool)nullable);
    AddColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->AddColumn(field, default_expr ? default_expr : "0", opts));
}

zvec_status_t zvec_collection_add_column_uint32(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    c->Flush();
    auto field = std::make_shared<FieldSchema>(name, DataType::UINT32, (bool)nullable);
    AddColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->AddColumn(field, default_expr ? default_expr : "0", opts));
}

zvec_status_t zvec_collection_add_column_uint64(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    c->Flush();
    auto field = std::make_shared<FieldSchema>(name, DataType::UINT64, (bool)nullable);
    AddColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->AddColumn(field, default_expr ? default_expr : "0", opts));
}

zvec_status_t zvec_collection_drop_column(zvec_collection_t coll, const char* name) {
    auto* c = static_cast<Collection*>(coll);
    c->Flush();
    return MAKE_STATUS(c->DropColumn(name));
}

zvec_status_t zvec_collection_rename_column(zvec_collection_t coll, const char* old_name, const char* new_name, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    c->Flush();
    AlterColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->AlterColumn(old_name, new_name, nullptr, opts));
}

zvec_status_t zvec_collection_alter_column(zvec_collection_t coll, const char* column_name, const char* new_name, uint32_t data_type, int nullable, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    c->Flush();
    
    // Convert data_type to DataType enum
    DataType dt = DataType::UNDEFINED;
    switch (data_type) {
        case 4: dt = DataType::INT32; break;
        case 5: dt = DataType::INT64; break;
        case 6: dt = DataType::UINT32; break;
        case 7: dt = DataType::UINT64; break;
        case 8: dt = DataType::FLOAT; break;
        case 9: dt = DataType::DOUBLE; break;
        default: dt = DataType::UNDEFINED; break;
    }
    
    FieldSchema::Ptr new_schema = nullptr;
    if (dt != DataType::UNDEFINED) {
        // FieldSchema needs the column name to identify which field to alter
        new_schema = std::make_shared<FieldSchema>(std::string(column_name), dt, (bool)nullable);
    }
    
    std::string rename_str = new_name ? new_name : "";
    AlterColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->AlterColumn(column_name, rename_str, new_schema, opts));
}

zvec_status_t zvec_collection_create_invert_index(zvec_collection_t coll, const char* field_name, int enable_range, int enable_wildcard) {
    auto* c = static_cast<Collection*>(coll);
    auto params = std::make_shared<InvertIndexParams>((bool)enable_range, (bool)enable_wildcard);
    return MAKE_STATUS(c->CreateIndex(field_name, params));
}

zvec_status_t zvec_collection_create_hnsw_index(zvec_collection_t coll, const char* field_name, uint32_t metric_type, int m, int ef_construction, uint32_t quantize_type, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    auto params = std::make_shared<HnswIndexParams>(to_metric_type(metric_type), m, ef_construction, to_quantize_type(quantize_type));
    CreateIndexOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->CreateIndex(field_name, params, opts));
}

zvec_status_t zvec_collection_create_hnsw_rabitq_index(zvec_collection_t coll, const char* field_name, uint32_t metric_type, int total_bits, int num_clusters, int m, int ef_construction, int sample_count, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    auto params = std::make_shared<HnswRabitqIndexParams>(to_metric_type(metric_type), total_bits, num_clusters, m, ef_construction, sample_count);
    CreateIndexOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->CreateIndex(field_name, params, opts));
}

zvec_status_t zvec_collection_create_flat_index(zvec_collection_t coll, const char* field_name, uint32_t metric_type, uint32_t quantize_type, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    auto params = std::make_shared<FlatIndexParams>(to_metric_type(metric_type), to_quantize_type(quantize_type));
    CreateIndexOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->CreateIndex(field_name, params, opts));
}

zvec_status_t zvec_collection_create_ivf_index(zvec_collection_t coll, const char* field_name, uint32_t metric_type, int n_list, int n_iters, int use_soar, uint32_t quantize_type, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    auto params = std::make_shared<IVFIndexParams>(to_metric_type(metric_type), n_list, n_iters, (bool)use_soar, to_quantize_type(quantize_type));
    CreateIndexOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->CreateIndex(field_name, params, opts));
}

zvec_status_t zvec_collection_drop_index(zvec_collection_t coll, const char* field_name) {
    auto* c = static_cast<Collection*>(coll);
    return MAKE_STATUS(c->DropIndex(field_name));
}

// --- Unified IndexParams API ---

struct IndexParamsHolder {
    IndexType type_;
    MetricType metric_type_;
    QuantizeType quantize_type_;
    int hnsw_m_;
    int hnsw_ef_construction_;
    int ivf_n_list_;
    int ivf_n_iters_;
    bool ivf_use_soar_;
    bool invert_enable_range_;
    bool invert_enable_wildcard_;
    int rabitq_total_bits_;
    int rabitq_num_clusters_;
    int rabitq_sample_count_;
    bool hnsw_use_contiguous_memory_;

    IndexParamsHolder(IndexType type, MetricType metric_type)
        : type_(type), metric_type_(metric_type), quantize_type_(QuantizeType::UNDEFINED),
          hnsw_m_(50), hnsw_ef_construction_(500),
          ivf_n_list_(1024), ivf_n_iters_(10), ivf_use_soar_(false),
          invert_enable_range_(true), invert_enable_wildcard_(false),
          rabitq_total_bits_(7), rabitq_num_clusters_(16), rabitq_sample_count_(0),
          hnsw_use_contiguous_memory_(false) {}

    IndexParams::Ptr build() const {
        switch (type_) {
            case IndexType::HNSW:
                return std::make_shared<HnswIndexParams>(metric_type_, hnsw_m_, hnsw_ef_construction_, quantize_type_, hnsw_use_contiguous_memory_);
            case IndexType::HNSW_RABITQ:
                return std::make_shared<HnswRabitqIndexParams>(metric_type_, rabitq_total_bits_, rabitq_num_clusters_, hnsw_m_, hnsw_ef_construction_, rabitq_sample_count_);
            case IndexType::FLAT:
                return std::make_shared<FlatIndexParams>(metric_type_, quantize_type_);
            case IndexType::IVF:
                return std::make_shared<IVFIndexParams>(metric_type_, ivf_n_list_, ivf_n_iters_, ivf_use_soar_, quantize_type_);
            case IndexType::INVERT:
                return std::make_shared<InvertIndexParams>(invert_enable_range_, invert_enable_wildcard_);
            default:
                return nullptr;
        }
    }
};

static IndexType to_index_type(int v) {
    switch (v) {
        case 1: return IndexType::HNSW;
        case 2: return IndexType::IVF;
        case 3: return IndexType::FLAT;
        case 4: return IndexType::HNSW_RABITQ;
        case 10: return IndexType::INVERT;
        default: return IndexType::UNDEFINED;
    }
}

zvec_index_params_t zvec_index_params_create(int index_type, int metric_type) {
    auto* holder = new IndexParamsHolder(to_index_type(index_type), to_metric_type(metric_type));
    return static_cast<zvec_index_params_t>(holder);
}

void zvec_index_params_free(zvec_index_params_t params) {
    delete static_cast<IndexParamsHolder*>(params);
}

void zvec_index_params_set_hnsw(zvec_index_params_t params, int m, int ef_construction, int quantize_type, int use_contiguous_memory) {
    auto* h = static_cast<IndexParamsHolder*>(params);
    h->hnsw_m_ = m;
    h->hnsw_ef_construction_ = ef_construction;
    h->quantize_type_ = to_quantize_type(quantize_type);
    h->hnsw_use_contiguous_memory_ = (bool)use_contiguous_memory;
}

void zvec_index_params_set_flat(zvec_index_params_t params, int quantize_type) {
    auto* h = static_cast<IndexParamsHolder*>(params);
    h->quantize_type_ = to_quantize_type(quantize_type);
}

void zvec_index_params_set_ivf(zvec_index_params_t params, int n_list, int n_iters, int use_soar, int quantize_type) {
    auto* h = static_cast<IndexParamsHolder*>(params);
    h->ivf_n_list_ = n_list;
    h->ivf_n_iters_ = n_iters;
    h->ivf_use_soar_ = (bool)use_soar;
    h->quantize_type_ = to_quantize_type(quantize_type);
}

void zvec_index_params_set_hnsw_rabitq(zvec_index_params_t params, int total_bits, int num_clusters, int m, int ef_construction, int sample_count) {
    auto* h = static_cast<IndexParamsHolder*>(params);
    h->rabitq_total_bits_ = total_bits;
    h->rabitq_num_clusters_ = num_clusters;
    h->hnsw_m_ = m;
    h->hnsw_ef_construction_ = ef_construction;
    h->rabitq_sample_count_ = sample_count;
}

void zvec_index_params_set_invert(zvec_index_params_t params, int enable_range, int enable_wildcard) {
    auto* h = static_cast<IndexParamsHolder*>(params);
    h->invert_enable_range_ = (bool)enable_range;
    h->invert_enable_wildcard_ = (bool)enable_wildcard;
}

void zvec_index_params_set_quantize_type(zvec_index_params_t params, int quantize_type) {
    auto* h = static_cast<IndexParamsHolder*>(params);
    h->quantize_type_ = to_quantize_type(quantize_type);
}

void zvec_index_params_set_metric_type(zvec_index_params_t params, int metric_type) {
    auto* h = static_cast<IndexParamsHolder*>(params);
    h->metric_type_ = to_metric_type(metric_type);
}

zvec_status_t zvec_collection_create_index(zvec_collection_t coll, const char* field_name, zvec_index_params_t params, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    auto* h = static_cast<IndexParamsHolder*>(params);
    auto index_params = h->build();
    if (!index_params) {
        return MAKE_STATUS(Status(StatusCode::INVALID_ARGUMENT, "Invalid or unsupported index type"));
    }
    CreateIndexOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->CreateIndex(field_name, index_params, opts));
}

// --- Doc ---

zvec_doc_t zvec_doc_create(const char* pk) {
    auto* doc = new Doc();
    doc->set_pk(pk);
    return static_cast<zvec_doc_t>(doc);
}

void zvec_doc_free(zvec_doc_t doc) {
    delete static_cast<Doc*>(doc);
}

void zvec_doc_set_int64(zvec_doc_t doc, const char* field, int64_t value) {
    static_cast<Doc*>(doc)->set<int64_t>(field, value);
}

void zvec_doc_set_string(zvec_doc_t doc, const char* field, const char* value) {
    static_cast<Doc*>(doc)->set<std::string>(field, std::string(value));
}

void zvec_doc_set_float(zvec_doc_t doc, const char* field, float value) {
    static_cast<Doc*>(doc)->set<float>(field, value);
}

void zvec_doc_set_double(zvec_doc_t doc, const char* field, double value) {
    static_cast<Doc*>(doc)->set<double>(field, value);
}

void zvec_doc_set_vector_fp32(zvec_doc_t doc, const char* field, const float* data, uint32_t dim) {
    std::vector<float> vec(data, data + dim);
    static_cast<Doc*>(doc)->set<std::vector<float>>(field, std::move(vec));
}

void zvec_doc_set_vector_fp64(zvec_doc_t doc, const char* field, const double* data, uint32_t dim) {
    std::vector<double> vec(data, data + dim);
    static_cast<Doc*>(doc)->set<std::vector<double>>(field, std::move(vec));
}

void zvec_doc_set_bool(zvec_doc_t doc, const char* field, int value) {
    static_cast<Doc*>(doc)->set<bool>(field, (bool)value);
}

void zvec_doc_set_int32(zvec_doc_t doc, const char* field, int32_t value) {
    static_cast<Doc*>(doc)->set<int32_t>(field, value);
}

void zvec_doc_set_uint32(zvec_doc_t doc, const char* field, uint32_t value) {
    static_cast<Doc*>(doc)->set<uint32_t>(field, value);
}

void zvec_doc_set_uint64(zvec_doc_t doc, const char* field, uint64_t value) {
    static_cast<Doc*>(doc)->set<uint64_t>(field, value);
}

void zvec_doc_set_vector_int8(zvec_doc_t doc, const char* field, const int8_t* data, uint32_t dim) {
    std::vector<int8_t> vec(data, data + dim);
    static_cast<Doc*>(doc)->set<std::vector<int8_t>>(field, std::move(vec));
}

void zvec_doc_set_vector_fp16(zvec_doc_t doc, const char* field, const uint16_t* data, uint32_t dim) {
    std::vector<ailego::Float16> vec;
    vec.reserve(dim);
    for (uint32_t i = 0; i < dim; i++) {
        vec.push_back(ailego::FloatHelper::ToFP32(data[i]));
    }
    static_cast<Doc*>(doc)->set<std::vector<ailego::Float16>>(field, std::move(vec));
}

void zvec_doc_set_sparse_vector_fp32(zvec_doc_t doc, const char* field, const uint32_t* indices, const float* values, uint32_t count) {
    if (count == 0) {
        // Empty sparse vector - store empty pair
        static_cast<Doc*>(doc)->set<std::pair<std::vector<uint32_t>, std::vector<float>>>(field, std::make_pair(std::vector<uint32_t>(), std::vector<float>()));
    } else {
        std::vector<uint32_t> idx_vec(indices, indices + count);
        std::vector<float> val_vec(values, values + count);
        auto sparse_pair = std::make_pair(std::move(idx_vec), std::move(val_vec));
        static_cast<Doc*>(doc)->set<std::pair<std::vector<uint32_t>, std::vector<float>>>(field, std::move(sparse_pair));
    }
}

void zvec_doc_set_vector_int4(zvec_doc_t doc, const char* field, const int8_t* data, uint32_t dim) {
    std::vector<int8_t> vec(data, data + dim);
    static_cast<Doc*>(doc)->set<std::vector<int8_t>>(field, std::move(vec));
}

void zvec_doc_set_vector_int16(zvec_doc_t doc, const char* field, const int16_t* data, uint32_t dim) {
    std::vector<int16_t> vec(data, data + dim);
    static_cast<Doc*>(doc)->set<std::vector<int16_t>>(field, std::move(vec));
}

void zvec_doc_set_vector_binary32(zvec_doc_t doc, const char* field, const uint32_t* data, uint32_t dim) {
    std::vector<uint32_t> vec(data, data + dim);
    static_cast<Doc*>(doc)->set<std::vector<uint32_t>>(field, std::move(vec));
}

void zvec_doc_set_vector_binary64(zvec_doc_t doc, const char* field, const uint64_t* data, uint32_t dim) {
    std::vector<uint64_t> vec(data, data + dim);
    static_cast<Doc*>(doc)->set<std::vector<uint64_t>>(field, std::move(vec));
}

void zvec_doc_set_sparse_vector_fp16(zvec_doc_t doc, const char* field, const uint32_t* indices, const uint16_t* values, uint32_t count) {
    if (count == 0) {
        static_cast<Doc*>(doc)->set<std::pair<std::vector<uint32_t>, std::vector<ailego::Float16>>>(field,
            std::make_pair(std::vector<uint32_t>(), std::vector<ailego::Float16>()));
    } else {
        std::vector<uint32_t> idx_vec(indices, indices + count);
        std::vector<ailego::Float16> val_vec;
        val_vec.reserve(count);
        for (uint32_t i = 0; i < count; i++) {
            val_vec.push_back(ailego::FloatHelper::ToFP32(values[i]));
        }
        static_cast<Doc*>(doc)->set<std::pair<std::vector<uint32_t>, std::vector<ailego::Float16>>>(field,
            std::make_pair(std::move(idx_vec), std::move(val_vec)));
    }
}

void zvec_doc_set_binary(zvec_doc_t doc, const char* field, const uint8_t* data, uint32_t size) {
    static_cast<Doc*>(doc)->set<std::string>(field, std::string(reinterpret_cast<const char*>(data), size));
}

void zvec_doc_set_array_int32(zvec_doc_t doc, const char* field, const int32_t* data, uint32_t count) {
    std::vector<int32_t> vec(data, data + count);
    static_cast<Doc*>(doc)->set<std::vector<int32_t>>(field, std::move(vec));
}

void zvec_doc_set_array_int64(zvec_doc_t doc, const char* field, const int64_t* data, uint32_t count) {
    std::vector<int64_t> vec(data, data + count);
    static_cast<Doc*>(doc)->set<std::vector<int64_t>>(field, std::move(vec));
}

void zvec_doc_set_array_uint32(zvec_doc_t doc, const char* field, const uint32_t* data, uint32_t count) {
    std::vector<uint32_t> vec(data, data + count);
    static_cast<Doc*>(doc)->set<std::vector<uint32_t>>(field, std::move(vec));
}

void zvec_doc_set_array_uint64(zvec_doc_t doc, const char* field, const uint64_t* data, uint32_t count) {
    std::vector<uint64_t> vec(data, data + count);
    static_cast<Doc*>(doc)->set<std::vector<uint64_t>>(field, std::move(vec));
}

void zvec_doc_set_array_float(zvec_doc_t doc, const char* field, const float* data, uint32_t count) {
    std::vector<float> vec(data, data + count);
    static_cast<Doc*>(doc)->set<std::vector<float>>(field, std::move(vec));
}

void zvec_doc_set_array_double(zvec_doc_t doc, const char* field, const double* data, uint32_t count) {
    std::vector<double> vec(data, data + count);
    static_cast<Doc*>(doc)->set<std::vector<double>>(field, std::move(vec));
}

void zvec_doc_set_array_string(zvec_doc_t doc, const char* field, const char** strings, uint32_t count) {
    std::vector<std::string> vec;
    vec.reserve(count);
    for (uint32_t i = 0; i < count; i++) {
        vec.emplace_back(strings[i]);
    }
    static_cast<Doc*>(doc)->set<std::vector<std::string>>(field, std::move(vec));
}

void zvec_doc_set_array_bool(zvec_doc_t doc, const char* field, const uint8_t* data, uint32_t count) {
    std::vector<bool> vec;
    vec.reserve(count);
    for (uint32_t i = 0; i < count; i++) {
        vec.push_back((bool)data[i]);
    }
    static_cast<Doc*>(doc)->set<std::vector<bool>>(field, std::move(vec));
}

static thread_local std::string g_pk_buf;

const char* zvec_doc_get_pk(zvec_doc_t doc) {
    g_pk_buf = static_cast<Doc*>(doc)->pk();
    return g_pk_buf.c_str();
}

float zvec_doc_get_score(zvec_doc_t doc) {
    return static_cast<Doc*>(doc)->score();
}

int zvec_doc_get_int64(zvec_doc_t doc, const char* field, int64_t* out) {
    auto val = static_cast<Doc*>(doc)->get<int64_t>(field);
    if (val.has_value()) { *out = val.value(); return 1; }
    return 0;
}

static thread_local std::string g_string_buf;

int zvec_doc_get_string(zvec_doc_t doc, const char* field, const char** out) {
    auto result = static_cast<Doc*>(doc)->get_field<std::string>(field);
    if (result.ok()) {
        g_string_buf = result.value();
        *out = g_string_buf.c_str();
        return 1;
    }
    return 0;
}

int zvec_doc_get_float(zvec_doc_t doc, const char* field, float* out) {
    auto val = static_cast<Doc*>(doc)->get<float>(field);
    if (val.has_value()) { *out = val.value(); return 1; }
    return 0;
}

int zvec_doc_get_double(zvec_doc_t doc, const char* field, double* out) {
    auto val = static_cast<Doc*>(doc)->get<double>(field);
    if (val.has_value()) { *out = val.value(); return 1; }
    return 0;
}

static thread_local std::vector<float> g_vector_buf;
static thread_local std::vector<double> g_vector_fp64_buf;

int zvec_doc_get_vector_fp32(zvec_doc_t doc, const char* field, const float** out, uint32_t* dim) {
    auto result = static_cast<Doc*>(doc)->get_field<std::vector<float>>(field);
    if (result.ok()) {
        g_vector_buf = result.value();
        *out = g_vector_buf.data();
        *dim = static_cast<uint32_t>(g_vector_buf.size());
        return 1;
    }
    return 0;
}

int zvec_doc_get_vector_fp64(zvec_doc_t doc, const char* field, const double** out, uint32_t* dim) {
    auto result = static_cast<Doc*>(doc)->get_field<std::vector<double>>(field);
    if (result.ok()) {
        g_vector_fp64_buf = result.value();
        *out = g_vector_fp64_buf.data();
        *dim = static_cast<uint32_t>(g_vector_fp64_buf.size());
        return 1;
    }
    return 0;
}

int zvec_doc_get_bool(zvec_doc_t doc, const char* field, int* out) {
    auto val = static_cast<Doc*>(doc)->get<bool>(field);
    if (val.has_value()) { *out = val.value() ? 1 : 0; return 1; }
    return 0;
}

int zvec_doc_get_int32(zvec_doc_t doc, const char* field, int32_t* out) {
    auto val = static_cast<Doc*>(doc)->get<int32_t>(field);
    if (val.has_value()) { *out = val.value(); return 1; }
    return 0;
}

int zvec_doc_get_uint32(zvec_doc_t doc, const char* field, uint32_t* out) {
    auto val = static_cast<Doc*>(doc)->get<uint32_t>(field);
    if (val.has_value()) { *out = val.value(); return 1; }
    return 0;
}

int zvec_doc_get_uint64(zvec_doc_t doc, const char* field, uint64_t* out) {
    auto val = static_cast<Doc*>(doc)->get<uint64_t>(field);
    if (val.has_value()) { *out = val.value(); return 1; }
    return 0;
}

static thread_local std::vector<int8_t> g_vector_int8_buf;

int zvec_doc_get_vector_int8(zvec_doc_t doc, const char* field, const int8_t** out, uint32_t* dim) {
    auto result = static_cast<Doc*>(doc)->get_field<std::vector<int8_t>>(field);
    if (result.ok()) {
        g_vector_int8_buf = result.value();
        *out = g_vector_int8_buf.data();
        *dim = static_cast<uint32_t>(g_vector_int8_buf.size());
        return 1;
    }
    return 0;
}

static thread_local std::vector<uint16_t> g_vector_fp16_buf;

int zvec_doc_get_vector_fp16(zvec_doc_t doc, const char* field, const uint16_t** out, uint32_t* dim) {
    auto result = static_cast<Doc*>(doc)->get_field<std::vector<ailego::Float16>>(field);
    if (result.ok()) {
        const auto& fp16_vec = result.value();
        g_vector_fp16_buf.resize(fp16_vec.size());
        for (size_t i = 0; i < fp16_vec.size(); i++) {
            g_vector_fp16_buf[i] = ailego::FloatHelper::ToFP16(static_cast<float>(fp16_vec[i]));
        }
        *out = g_vector_fp16_buf.data();
        *dim = static_cast<uint32_t>(g_vector_fp16_buf.size());
        return 1;
    }
    return 0;
}

// Separate buffers for each sparse vector get call to avoid data mixing
// Use a circular buffer approach with 16 slots to handle multiple concurrent calls
static thread_local std::array<std::pair<std::vector<uint32_t>, std::vector<float>>, 16> g_sparse_buffers;
static thread_local size_t g_sparse_buffer_idx = 0;

int zvec_doc_get_sparse_vector_fp32(zvec_doc_t doc, const char* field, const uint32_t** indices_out, const float** values_out, uint32_t* count_out) {
    auto result = static_cast<Doc*>(doc)->get_field<std::pair<std::vector<uint32_t>, std::vector<float>>>(field);
    if (result.ok()) {
        // Use circular buffer to keep data alive - each call gets a new slot
        auto& slot = g_sparse_buffers[g_sparse_buffer_idx];
        slot = result.value();
        *indices_out = slot.first.data();
        *values_out = slot.second.data();
        *count_out = static_cast<uint32_t>(slot.first.size());
        g_sparse_buffer_idx = (g_sparse_buffer_idx + 1) % 16;
        return 1;
    }
    return 0;
}

static thread_local std::vector<int16_t> g_vector_int16_buf;

int zvec_doc_get_vector_int16(zvec_doc_t doc, const char* field, const int16_t** out, uint32_t* dim) {
    auto result = static_cast<Doc*>(doc)->get_field<std::vector<int16_t>>(field);
    if (result.ok()) {
        g_vector_int16_buf = result.value();
        *out = g_vector_int16_buf.data();
        *dim = static_cast<uint32_t>(g_vector_int16_buf.size());
        return 1;
    }
    return 0;
}

static thread_local std::vector<uint32_t> g_vector_binary32_buf;

int zvec_doc_get_vector_binary32(zvec_doc_t doc, const char* field, const uint32_t** out, uint32_t* dim) {
    auto result = static_cast<Doc*>(doc)->get_field<std::vector<uint32_t>>(field);
    if (result.ok()) {
        g_vector_binary32_buf = result.value();
        *out = g_vector_binary32_buf.data();
        *dim = static_cast<uint32_t>(g_vector_binary32_buf.size());
        return 1;
    }
    return 0;
}

static thread_local std::vector<uint64_t> g_vector_binary64_buf;

int zvec_doc_get_vector_binary64(zvec_doc_t doc, const char* field, const uint64_t** out, uint32_t* dim) {
    auto result = static_cast<Doc*>(doc)->get_field<std::vector<uint64_t>>(field);
    if (result.ok()) {
        g_vector_binary64_buf = result.value();
        *out = g_vector_binary64_buf.data();
        *dim = static_cast<uint32_t>(g_vector_binary64_buf.size());
        return 1;
    }
    return 0;
}

// For INT4, store in same buffer type (int8_t) since they share the same variant
int zvec_doc_get_vector_int4(zvec_doc_t doc, const char* field, const int8_t** out, uint32_t* dim) {
    auto result = static_cast<Doc*>(doc)->get_field<std::vector<int8_t>>(field);
    if (result.ok()) {
        g_vector_int8_buf = result.value();
        *out = g_vector_int8_buf.data();
        *dim = static_cast<uint32_t>(g_vector_int8_buf.size());
        return 1;
    }
    return 0;
}

// Sparse FP16 - circular buffer
static thread_local std::array<std::pair<std::vector<uint32_t>, std::vector<ailego::Float16>>, 16> g_sparse_fp16_buffers;
static thread_local size_t g_sparse_fp16_buffer_idx = 0;

int zvec_doc_get_sparse_vector_fp16(zvec_doc_t doc, const char* field, const uint32_t** indices_out, const uint16_t** values_out, uint32_t* count_out) {
    auto result = static_cast<Doc*>(doc)->get_field<std::pair<std::vector<uint32_t>, std::vector<ailego::Float16>>>(field);
    if (result.ok()) {
        auto& slot = g_sparse_fp16_buffers[g_sparse_fp16_buffer_idx];
        slot = result.value();
        *indices_out = slot.first.data();
        
        // Convert float16_t values back to uint16_t for PHP
        thread_local static std::vector<uint16_t> g_tmp_fp16_vals;
        g_tmp_fp16_vals.resize(slot.second.size());
        for (size_t i = 0; i < slot.second.size(); i++) {
            g_tmp_fp16_vals[i] = ailego::FloatHelper::ToFP16(static_cast<float>(slot.second[i]));
        }
        *values_out = g_tmp_fp16_vals.data();
        *count_out = static_cast<uint32_t>(slot.first.size());
        g_sparse_fp16_buffer_idx = (g_sparse_fp16_buffer_idx + 1) % 16;
        return 1;
    }
    return 0;
}

static thread_local std::string g_binary_buf;

int zvec_doc_get_binary(zvec_doc_t doc, const char* field, const uint8_t** out, uint32_t* size) {
    auto result = static_cast<Doc*>(doc)->get_field<std::string>(field);
    if (result.ok()) {
        g_binary_buf = result.value();
        *out = reinterpret_cast<const uint8_t*>(g_binary_buf.data());
        *size = static_cast<uint32_t>(g_binary_buf.size());
        return 1;
    }
    return 0;
}

static thread_local std::vector<int32_t> g_array_int32_buf;

int zvec_doc_get_array_int32(zvec_doc_t doc, const char* field, const int32_t** out, uint32_t* count) {
    auto result = static_cast<Doc*>(doc)->get_field<std::vector<int32_t>>(field);
    if (result.ok()) {
        g_array_int32_buf = result.value();
        *out = g_array_int32_buf.data();
        *count = static_cast<uint32_t>(g_array_int32_buf.size());
        return 1;
    }
    return 0;
}

static thread_local std::vector<int64_t> g_array_int64_buf;

int zvec_doc_get_array_int64(zvec_doc_t doc, const char* field, const int64_t** out, uint32_t* count) {
    auto result = static_cast<Doc*>(doc)->get_field<std::vector<int64_t>>(field);
    if (result.ok()) {
        g_array_int64_buf = result.value();
        *out = g_array_int64_buf.data();
        *count = static_cast<uint32_t>(g_array_int64_buf.size());
        return 1;
    }
    return 0;
}

static thread_local std::vector<uint32_t> g_array_uint32_buf;

int zvec_doc_get_array_uint32(zvec_doc_t doc, const char* field, const uint32_t** out, uint32_t* count) {
    auto result = static_cast<Doc*>(doc)->get_field<std::vector<uint32_t>>(field);
    if (result.ok()) {
        g_array_uint32_buf = result.value();
        *out = g_array_uint32_buf.data();
        *count = static_cast<uint32_t>(g_array_uint32_buf.size());
        return 1;
    }
    return 0;
}

static thread_local std::vector<uint64_t> g_array_uint64_buf;

int zvec_doc_get_array_uint64(zvec_doc_t doc, const char* field, const uint64_t** out, uint32_t* count) {
    auto result = static_cast<Doc*>(doc)->get_field<std::vector<uint64_t>>(field);
    if (result.ok()) {
        g_array_uint64_buf = result.value();
        *out = g_array_uint64_buf.data();
        *count = static_cast<uint32_t>(g_array_uint64_buf.size());
        return 1;
    }
    return 0;
}

static thread_local std::vector<float> g_array_float_buf;

int zvec_doc_get_array_float(zvec_doc_t doc, const char* field, const float** out, uint32_t* count) {
    auto result = static_cast<Doc*>(doc)->get_field<std::vector<float>>(field);
    if (result.ok()) {
        g_array_float_buf = result.value();
        *out = g_array_float_buf.data();
        *count = static_cast<uint32_t>(g_array_float_buf.size());
        return 1;
    }
    return 0;
}

static thread_local std::vector<double> g_array_double_buf;

int zvec_doc_get_array_double(zvec_doc_t doc, const char* field, const double** out, uint32_t* count) {
    auto result = static_cast<Doc*>(doc)->get_field<std::vector<double>>(field);
    if (result.ok()) {
        g_array_double_buf = result.value();
        *out = g_array_double_buf.data();
        *count = static_cast<uint32_t>(g_array_double_buf.size());
        return 1;
    }
    return 0;
}

int zvec_doc_get_array_string(zvec_doc_t doc, const char* field, char* buf, size_t buf_size, uint32_t* count) {
    auto result = static_cast<Doc*>(doc)->get_field<std::vector<std::string>>(field);
    if (result.ok()) {
        const auto& strs = result.value();
        *count = static_cast<uint32_t>(strs.size());
        g_string_buf.clear();
        for (size_t i = 0; i < strs.size(); i++) {
            if (i > 0) g_string_buf += '\0';
            g_string_buf += strs[i];
        }
        size_t len = g_string_buf.length();
        if (len + 1 > buf_size) {
            return -1;
        }
        memcpy(buf, g_string_buf.data(), len);
        buf[len] = '\0';
        return static_cast<int>(len);
    }
    return 0;
}

int zvec_doc_get_array_bool(zvec_doc_t doc, const char* field, uint8_t* buf, size_t buf_size) {
    auto result = static_cast<Doc*>(doc)->get_field<std::vector<bool>>(field);
    if (result.ok()) {
        const auto& vec = result.value();
        size_t count = vec.size();
        if (count > buf_size) {
            return -1;
        }
        for (size_t i = 0; i < count; i++) {
            buf[i] = vec[i] ? 1 : 0;
        }
        return static_cast<int>(count);
    }
    return 0;
}

// --- Doc Introspection ---

int zvec_doc_has_field(zvec_doc_t doc, const char* field) {
    return static_cast<Doc*>(doc)->has(field) ? 1 : 0;
}

int zvec_doc_has_vector(zvec_doc_t doc, const char* field) {
    auto* d = static_cast<Doc*>(doc);
    if (!d->has(field)) return 0;
    // Check all vector variant types
    if (d->get_field<std::vector<float>>(field).ok()) return 1;
    if (d->get_field<std::vector<double>>(field).ok()) return 1;
    if (d->get_field<std::vector<int8_t>>(field).ok()) return 1;
    if (d->get_field<std::vector<int16_t>>(field).ok()) return 1;
    if (d->get_field<std::vector<uint32_t>>(field).ok()) return 1;
    if (d->get_field<std::vector<uint64_t>>(field).ok()) return 1;
    if (d->get_field<std::vector<ailego::Float16>>(field).ok()) return 1;
    if (d->get_field<std::pair<std::vector<uint32_t>, std::vector<float>>>(field).ok()) return 1;
    if (d->get_field<std::pair<std::vector<uint32_t>, std::vector<ailego::Float16>>>(field).ok()) return 1;
    return 0;
}

static thread_local std::string g_names_buf;

static bool is_vector_field(Doc* d, const std::string& name) {
    if (d->get_field<std::vector<float>>(name).ok()) return true;
    if (d->get_field<std::vector<double>>(name).ok()) return true;
    if (d->get_field<std::vector<int8_t>>(name).ok()) return true;
    if (d->get_field<std::vector<int16_t>>(name).ok()) return true;
    if (d->get_field<std::vector<uint32_t>>(name).ok()) return true;
    if (d->get_field<std::vector<uint64_t>>(name).ok()) return true;
    if (d->get_field<std::vector<ailego::Float16>>(name).ok()) return true;
    if (d->get_field<std::pair<std::vector<uint32_t>, std::vector<float>>>(name).ok()) return true;
    if (d->get_field<std::pair<std::vector<uint32_t>, std::vector<ailego::Float16>>>(name).ok()) return true;
    return false;
}

int zvec_doc_field_names(zvec_doc_t doc, char* buf, size_t buf_size) {
    auto* d = static_cast<Doc*>(doc);
    auto names = d->field_names();
    std::vector<std::string> filtered;
    for (const auto& name : names) {
        if (is_vector_field(d, name)) continue;
        filtered.push_back(name);
    }
    std::sort(filtered.begin(), filtered.end());
    g_names_buf.clear();
    bool first = true;
    for (const auto& name : filtered) {
        if (!first) g_names_buf += '\n';
        g_names_buf += name;
        first = false;
    }
    
    if (g_names_buf.length() >= buf_size) {
        // Buffer too small
        return -1;
    }
    
    strncpy(buf, g_names_buf.c_str(), buf_size - 1);
    buf[buf_size - 1] = '\0';
    return static_cast<int>(g_names_buf.length());
}

int zvec_doc_vector_names(zvec_doc_t doc, char* buf, size_t buf_size) {
    auto* d = static_cast<Doc*>(doc);
    auto names = d->field_names();
    std::vector<std::string> filtered;
    for (const auto& name : names) {
        if (is_vector_field(d, name)) {
            filtered.push_back(name);
        }
    }
    std::sort(filtered.begin(), filtered.end());
    g_names_buf.clear();
    bool first = true;
    for (const auto& name : filtered) {
        if (!first) g_names_buf += '\n';
        g_names_buf += name;
        first = false;
    }
    
    if (g_names_buf.length() >= buf_size) {
        return -1;
    }
    
    strncpy(buf, g_names_buf.c_str(), buf_size - 1);
    buf[buf_size - 1] = '\0';
    return static_cast<int>(g_names_buf.length());
}

// --- Insert / Upsert / Delete ---

zvec_status_t zvec_collection_insert(zvec_collection_t coll, zvec_doc_t* docs, int count) {
    auto* c = static_cast<Collection*>(coll);
    std::vector<Doc> doc_vec;
    doc_vec.reserve(count);
    for (int i = 0; i < count; i++) {
        doc_vec.push_back(*static_cast<Doc*>(docs[i]));
    }
    auto res = c->Insert(doc_vec);
    if (!res.has_value()) {
        return MAKE_STATUS(res.error());
    }
    for (const auto& s : res.value()) {
        if (!s.ok()) return MAKE_STATUS(s);
    }
    return ok_status();
}

zvec_status_t zvec_collection_upsert(zvec_collection_t coll, zvec_doc_t* docs, int count) {
    auto* c = static_cast<Collection*>(coll);
    std::vector<Doc> doc_vec;
    doc_vec.reserve(count);
    for (int i = 0; i < count; i++) {
        doc_vec.push_back(*static_cast<Doc*>(docs[i]));
    }
    auto res = c->Upsert(doc_vec);
    if (!res.has_value()) {
        return MAKE_STATUS(res.error());
    }
    for (const auto& s : res.value()) {
        if (!s.ok()) return MAKE_STATUS(s);
    }
    return ok_status();
}

zvec_status_t zvec_collection_update(zvec_collection_t coll, zvec_doc_t* docs, int count) {
    auto* c = static_cast<Collection*>(coll);
    std::vector<Doc> doc_vec;
    doc_vec.reserve(count);
    for (int i = 0; i < count; i++) {
        doc_vec.push_back(*static_cast<Doc*>(docs[i]));
    }
    auto res = c->Update(doc_vec);
    if (!res.has_value()) {
        return MAKE_STATUS(res.error());
    }
    for (const auto& s : res.value()) {
        if (!s.ok()) return MAKE_STATUS(s);
    }
    return ok_status();
}

// --- Batch operations with per-document status ---

zvec_status_t zvec_collection_insert_batch(zvec_collection_t coll, zvec_doc_t* docs, int count, zvec_batch_result_t* result) {
    auto* c = static_cast<Collection*>(coll);
    std::vector<Doc> doc_vec;
    doc_vec.reserve(count);
    std::vector<std::string> pk_vec;
    pk_vec.reserve(count);
    for (int i = 0; i < count; i++) {
        auto* doc = static_cast<Doc*>(docs[i]);
        doc_vec.push_back(*doc);
        pk_vec.push_back(doc->pk());
    }
    
    auto res = c->Insert(doc_vec);
    if (!res.has_value()) {
        result->count = 0;
        result->codes = nullptr;
        result->messages = nullptr;
        result->doc_pks = nullptr;
        return MAKE_STATUS(res.error());
    }
    
    const auto& statuses = res.value();
    result->count = count;
    result->codes = new int[count];
    result->messages = new char*[count];
    result->doc_pks = new char*[count];
    
    for (int i = 0; i < count; i++) {
        result->codes[i] = static_cast<int>(statuses[i].code());
        if (statuses[i].ok()) {
            result->messages[i] = nullptr;
        } else {
            std::string msg = statuses[i].message();
            result->messages[i] = new char[msg.length() + 1];
            strcpy(result->messages[i], msg.c_str());
        }
        result->doc_pks[i] = new char[pk_vec[i].length() + 1];
        strcpy(result->doc_pks[i], pk_vec[i].c_str());
    }
    
    return ok_status();
}

zvec_status_t zvec_collection_upsert_batch(zvec_collection_t coll, zvec_doc_t* docs, int count, zvec_batch_result_t* result) {
    auto* c = static_cast<Collection*>(coll);
    std::vector<Doc> doc_vec;
    doc_vec.reserve(count);
    std::vector<std::string> pk_vec;
    pk_vec.reserve(count);
    for (int i = 0; i < count; i++) {
        auto* doc = static_cast<Doc*>(docs[i]);
        doc_vec.push_back(*doc);
        pk_vec.push_back(doc->pk());
    }
    
    auto res = c->Upsert(doc_vec);
    if (!res.has_value()) {
        result->count = 0;
        result->codes = nullptr;
        result->messages = nullptr;
        result->doc_pks = nullptr;
        return MAKE_STATUS(res.error());
    }
    
    const auto& statuses = res.value();
    result->count = count;
    result->codes = new int[count];
    result->messages = new char*[count];
    result->doc_pks = new char*[count];
    
    for (int i = 0; i < count; i++) {
        result->codes[i] = static_cast<int>(statuses[i].code());
        if (statuses[i].ok()) {
            result->messages[i] = nullptr;
        } else {
            std::string msg = statuses[i].message();
            result->messages[i] = new char[msg.length() + 1];
            strcpy(result->messages[i], msg.c_str());
        }
        result->doc_pks[i] = new char[pk_vec[i].length() + 1];
        strcpy(result->doc_pks[i], pk_vec[i].c_str());
    }
    
    return ok_status();
}

zvec_status_t zvec_collection_update_batch(zvec_collection_t coll, zvec_doc_t* docs, int count, zvec_batch_result_t* result) {
    auto* c = static_cast<Collection*>(coll);
    std::vector<Doc> doc_vec;
    doc_vec.reserve(count);
    std::vector<std::string> pk_vec;
    pk_vec.reserve(count);
    for (int i = 0; i < count; i++) {
        auto* doc = static_cast<Doc*>(docs[i]);
        doc_vec.push_back(*doc);
        pk_vec.push_back(doc->pk());
    }
    
    auto res = c->Update(doc_vec);
    if (!res.has_value()) {
        result->count = 0;
        result->codes = nullptr;
        result->messages = nullptr;
        result->doc_pks = nullptr;
        return MAKE_STATUS(res.error());
    }
    
    const auto& statuses = res.value();
    result->count = count;
    result->codes = new int[count];
    result->messages = new char*[count];
    result->doc_pks = new char*[count];
    
    for (int i = 0; i < count; i++) {
        result->codes[i] = static_cast<int>(statuses[i].code());
        if (statuses[i].ok()) {
            result->messages[i] = nullptr;
        } else {
            std::string msg = statuses[i].message();
            result->messages[i] = new char[msg.length() + 1];
            strcpy(result->messages[i], msg.c_str());
        }
        result->doc_pks[i] = new char[pk_vec[i].length() + 1];
        strcpy(result->doc_pks[i], pk_vec[i].c_str());
    }
    
    return ok_status();
}

void zvec_batch_result_free(zvec_batch_result_t* result) {
    if (result->doc_pks) {
        for (int i = 0; i < result->count; i++) {
            delete[] result->doc_pks[i];
        }
        delete[] result->doc_pks;
        result->doc_pks = nullptr;
    }
    if (result->messages) {
        for (int i = 0; i < result->count; i++) {
            delete[] result->messages[i];
        }
        delete[] result->messages;
        result->messages = nullptr;
    }
    if (result->codes) {
        delete[] result->codes;
        result->codes = nullptr;
    }
    result->count = 0;
}

zvec_status_t zvec_collection_delete(zvec_collection_t coll, const char** pks, int count) {
    auto* c = static_cast<Collection*>(coll);
    std::vector<std::string> pk_vec;
    pk_vec.reserve(count);
    for (int i = 0; i < count; i++) {
        pk_vec.emplace_back(pks[i]);
    }
    auto res = c->Delete(pk_vec);
    if (!res.has_value()) {
        return MAKE_STATUS(res.error());
    }
    return ok_status();
}

zvec_status_t zvec_collection_delete_by_filter(zvec_collection_t coll, const char* filter) {
    auto* c = static_cast<Collection*>(coll);
    return MAKE_STATUS(c->DeleteByFilter(filter));
}

// --- Fetch ---

zvec_status_t zvec_collection_fetch(zvec_collection_t coll, const char** pks, int count, zvec_query_result_t* result) {
    auto* c = static_cast<Collection*>(coll);
    std::vector<std::string> pk_vec;
    pk_vec.reserve(count);
    for (int i = 0; i < count; i++) {
        pk_vec.emplace_back(pks[i]);
    }
    auto res = c->Fetch(pk_vec);
    if (!res.has_value()) {
        result->docs = nullptr;
        result->count = 0;
        return MAKE_STATUS(res.error());
    }
    auto& doc_map = res.value();
    int found = 0;
    for (auto& [k, v] : doc_map) {
        if (v) found++;
    }
    result->count = found;
    if (found > 0) {
        result->docs = new zvec_doc_t[found];
        int idx = 0;
        for (auto& [k, v] : doc_map) {
            if (v) {
                result->docs[idx++] = static_cast<zvec_doc_t>(new Doc(*v));
            }
        }
    } else {
        result->docs = nullptr;
    }
    return ok_status();
}

// --- Query ---

zvec_status_t zvec_collection_query(zvec_collection_t coll, const char* field_name,
                                     const float* query_vector, uint32_t dim,
                                     int topk, int include_vector,
                                     const char* filter,
                                     zvec_query_result_t* result) {
    auto* c = static_cast<Collection*>(coll);
    VectorQuery query;
    query.topk_ = topk;
    query.field_name_ = field_name;
    query.include_vector_ = (bool)include_vector;
    query.query_vector_.assign(reinterpret_cast<const char*>(query_vector), dim * sizeof(float));
    if (filter && filter[0] != '\0') {
        query.filter_ = filter;
    }

    auto res = c->Query(query);
    if (!res.has_value()) {
        result->docs = nullptr;
        result->count = 0;
        return MAKE_STATUS(res.error());
    }

    auto& doc_list = res.value();
    result->count = static_cast<int>(doc_list.size());
    if (result->count > 0) {
        result->docs = new zvec_doc_t[result->count];
        for (int i = 0; i < result->count; i++) {
            result->docs[i] = static_cast<zvec_doc_t>(new Doc(*doc_list[i]));
        }
    } else {
        result->docs = nullptr;
    }
    return ok_status();
}

zvec_status_t zvec_collection_query_fp16(zvec_collection_t coll, const char* field_name,
                                          const uint16_t* query_vector, uint32_t dim,
                                          int topk, int include_vector,
                                          const char* filter,
                                          zvec_query_result_t* result) {
    auto* c = static_cast<Collection*>(coll);
    
    std::vector<ailego::Float16> fp16_vec;
    fp16_vec.reserve(dim);
    for (uint32_t i = 0; i < dim; i++) {
        fp16_vec.push_back(ailego::FloatHelper::ToFP32(query_vector[i]));
    }
    
    VectorQuery query;
    query.topk_ = topk;
    query.field_name_ = field_name;
    query.include_vector_ = (bool)include_vector;
    query.query_vector_.assign(reinterpret_cast<const char*>(fp16_vec.data()), dim * sizeof(ailego::Float16));
    if (filter && filter[0] != '\0') {
        query.filter_ = filter;
    }

    auto res = c->Query(query);
    if (!res.has_value()) {
        result->docs = nullptr;
        result->count = 0;
        return MAKE_STATUS(res.error());
    }

    auto& doc_list = res.value();
    result->count = static_cast<int>(doc_list.size());
    if (result->count > 0) {
        result->docs = new zvec_doc_t[result->count];
        for (int i = 0; i < result->count; i++) {
            result->docs[i] = static_cast<zvec_doc_t>(new Doc(*doc_list[i]));
        }
    } else {
        result->docs = nullptr;
    }
    return ok_status();
}

static void fill_doc_list(const DocPtrList& doc_list, zvec_query_result_t* result);

zvec_status_t zvec_collection_query_fp64(zvec_collection_t coll, const char* field_name,
                                          const double* query_vector, uint32_t dim,
                                          int topk, int include_vector,
                                          const char* filter,
                                          zvec_query_result_t* result) {
    auto* c = static_cast<Collection*>(coll);

    std::vector<double> fp64_vec(query_vector, query_vector + dim);

    VectorQuery query;
    query.topk_ = topk;
    query.field_name_ = field_name;
    query.include_vector_ = (bool)include_vector;
    query.query_vector_.assign(reinterpret_cast<const char*>(fp64_vec.data()), dim * sizeof(double));
    if (filter && filter[0] != '\0') {
        query.filter_ = filter;
    }

    auto res = c->Query(query);
    if (!res.has_value()) {
        result->docs = nullptr;
        result->count = 0;
        return MAKE_STATUS(res.error());
    }

    fill_doc_list(res.value(), result);
    return ok_status();
}

static void apply_output_fields(VectorQuery& query, const char** output_fields, int count) {
    if (output_fields && count >= 0) {
        std::vector<std::string> fields;
        fields.reserve(count);
        for (int i = 0; i < count; i++) {
            fields.emplace_back(output_fields[i]);
        }
        query.output_fields_ = std::move(fields);
    }
}

static zvec_status_t validate_query_param_type(Collection* c, const char* field_name, int query_param_type) {
    if (query_param_type == 0) {
        return ok_status();
    }
    
    auto schema_res = c->Schema();
    if (!schema_res.has_value()) {
        return MAKE_STATUS(schema_res.error());
    }
    
    const auto& schema = schema_res.value();
    const FieldSchema* field = schema.get_field(field_name);
    if (!field) {
        zvec_status_t st;
        st.code = static_cast<int>(StatusCode::INVALID_ARGUMENT);
        std::string msg = std::string("Field not found: ") + field_name;
        strncpy(st.message, msg.c_str(), sizeof(st.message) - 1);
        st.message[sizeof(st.message) - 1] = '\0';
        SET_FFI_ERROR(st);
        return st;
    }
    
    IndexType actual_index_type = field->index_type();
    
    // Map query_param_type to expected IndexType
    IndexType expected_index_type = IndexType::UNDEFINED;
    switch (query_param_type) {
        case 1: expected_index_type = IndexType::HNSW; break;
        case 2: expected_index_type = IndexType::IVF; break;
        case 3: expected_index_type = IndexType::FLAT; break;
        case 4: expected_index_type = IndexType::HNSW_RABITQ; break;
    }
    
    if (expected_index_type != IndexType::UNDEFINED && 
        actual_index_type != IndexType::UNDEFINED &&
        actual_index_type != expected_index_type) {
        zvec_status_t st;
        st.code = static_cast<int>(StatusCode::INVALID_ARGUMENT);
        std::string msg = std::string("Query parameter type mismatch for field '") + field_name + 
                          "': index type does not match query_param_type";
        strncpy(st.message, msg.c_str(), sizeof(st.message) - 1);
        st.message[sizeof(st.message) - 1] = '\0';
        SET_FFI_ERROR(st);
        return st;
    }
    
    return ok_status();
}

static void apply_query_params(VectorQuery& query, int type, int hnsw_ef, int ivf_nprobe,
                                  float radius, int is_linear, int is_using_refiner) {
    if (type == 1) {
        query.query_params_ = std::make_shared<HnswQueryParams>(
            hnsw_ef, radius, (bool)is_linear, (bool)is_using_refiner);
    } else if (type == 2) {
        auto params = std::make_shared<IVFQueryParams>(ivf_nprobe, (bool)is_using_refiner);
        params->set_radius(radius);
        params->set_is_linear((bool)is_linear);
        query.query_params_ = params;
    } else if (type == 3) {
        auto params = std::make_shared<FlatQueryParams>((bool)is_using_refiner);
        params->set_radius(radius);
        params->set_is_linear((bool)is_linear);
        query.query_params_ = params;
    } else if (type == 4) {
        query.query_params_ = std::make_shared<HnswRabitqQueryParams>(
            hnsw_ef, radius, (bool)is_linear, (bool)is_using_refiner);
    }
}

static void fill_doc_list(const DocPtrList& doc_list, zvec_query_result_t* result) {
    result->count = static_cast<int>(doc_list.size());
    if (result->count > 0) {
        result->docs = new zvec_doc_t[result->count];
        for (int i = 0; i < result->count; i++) {
            result->docs[i] = static_cast<zvec_doc_t>(new Doc(*doc_list[i]));
        }
    } else {
        result->docs = nullptr;
    }
}

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
                                         zvec_query_result_t* result) {
    auto* c = static_cast<Collection*>(coll);
    
    // Validate query_param_type against actual index type
    auto validation_status = validate_query_param_type(c, field_name, query_param_type);
    if (validation_status.code != 0) {
        result->docs = nullptr;
        result->count = 0;
        return validation_status;
    }
    
    VectorQuery query;
    query.topk_ = topk;
    query.field_name_ = field_name;
    query.include_vector_ = (bool)include_vector;
    query.query_vector_.assign(reinterpret_cast<const char*>(query_vector), dim * sizeof(float));
    if (filter && filter[0] != '\0') {
        query.filter_ = filter;
    }
    apply_output_fields(query, output_fields, output_fields_count);
    apply_query_params(query, query_param_type, hnsw_ef, ivf_nprobe, radius, is_linear, is_using_refiner);

    auto res = c->Query(query);
    if (!res.has_value()) {
        result->docs = nullptr;
        result->count = 0;
        return MAKE_STATUS(res.error());
    }
    fill_doc_list(res.value(), result);
    return ok_status();
}

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
                                              zvec_query_result_t* result) {
    auto* c = static_cast<Collection*>(coll);

    auto validation_status = validate_query_param_type(c, field_name, query_param_type);
    if (validation_status.code != 0) {
        result->docs = nullptr;
        result->count = 0;
        return validation_status;
    }

    std::vector<double> fp64_vec(query_vector, query_vector + dim);

    VectorQuery query;
    query.topk_ = topk;
    query.field_name_ = field_name;
    query.include_vector_ = (bool)include_vector;
    query.query_vector_.assign(reinterpret_cast<const char*>(fp64_vec.data()), dim * sizeof(double));
    if (filter && filter[0] != '\0') {
        query.filter_ = filter;
    }
    apply_output_fields(query, output_fields, output_fields_count);
    apply_query_params(query, query_param_type, hnsw_ef, ivf_nprobe, radius, is_linear, is_using_refiner);

    auto res = c->Query(query);
    if (!res.has_value()) {
        result->docs = nullptr;
        result->count = 0;
        return MAKE_STATUS(res.error());
    }
    fill_doc_list(res.value(), result);
    return ok_status();
}

zvec_status_t zvec_collection_query_filter(zvec_collection_t coll, const char* filter,
                                             int topk, zvec_query_result_t* result) {
    auto* c = static_cast<Collection*>(coll);
    VectorQuery query;
    query.topk_ = topk;
    query.filter_ = filter;

    auto res = c->Query(query);
    if (!res.has_value()) {
        result->docs = nullptr;
        result->count = 0;
        return MAKE_STATUS(res.error());
    }
    fill_doc_list(res.value(), result);
    return ok_status();
}

zvec_status_t zvec_collection_query_filter_ex(zvec_collection_t coll, const char* filter,
                                               int topk,
                                               const char** output_fields, int output_fields_count,
                                               zvec_query_result_t* result) {
    auto* c = static_cast<Collection*>(coll);
    VectorQuery query;
    query.topk_ = topk;
    query.filter_ = filter;
    apply_output_fields(query, output_fields, output_fields_count);

    auto res = c->Query(query);
    if (!res.has_value()) {
        result->docs = nullptr;
        result->count = 0;
        return MAKE_STATUS(res.error());
    }
    fill_doc_list(res.value(), result);
    return ok_status();
}

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
                                               zvec_group_results_t* result) {
    auto* c = static_cast<Collection*>(coll);
    
    // Validate query_param_type against actual index type
    auto validation_status = validate_query_param_type(c, field_name, query_param_type);
    if (validation_status.code != 0) {
        result->groups = nullptr;
        result->count = 0;
        return validation_status;
    }
    
    GroupByVectorQuery query;
    query.field_name_ = field_name;
    query.query_vector_.assign(reinterpret_cast<const char*>(query_vector), dim * sizeof(float));
    query.group_by_field_name_ = group_by_field;
    query.group_count_ = group_count;
    query.group_topk_ = group_topk;
    query.include_vector_ = (bool)include_vector;
    if (filter && filter[0] != '\0') {
        query.filter_ = filter;
    }
    if (output_fields && output_fields_count >= 0) {
        std::vector<std::string> fields;
        fields.reserve(output_fields_count);
        for (int i = 0; i < output_fields_count; i++) {
            fields.emplace_back(output_fields[i]);
        }
        query.output_fields_ = std::move(fields);
    }
    if (query_param_type == 1) {
        query.query_params_ = std::make_shared<HnswQueryParams>(
            hnsw_ef, radius, (bool)is_linear, (bool)is_using_refiner);
    } else if (query_param_type == 2) {
        auto params = std::make_shared<IVFQueryParams>(ivf_nprobe, (bool)is_using_refiner);
        params->set_radius(radius);
        params->set_is_linear((bool)is_linear);
        query.query_params_ = params;
    } else if (query_param_type == 3) {
        auto params = std::make_shared<FlatQueryParams>((bool)is_using_refiner);
        params->set_radius(radius);
        params->set_is_linear((bool)is_linear);
        query.query_params_ = params;
    }

    auto res = c->GroupByQuery(query);
    if (!res.has_value()) {
        result->groups = nullptr;
        result->count = 0;
        return MAKE_STATUS(res.error());
    }

    auto& groups = res.value();
    result->count = static_cast<int>(groups.size());
    if (result->count > 0) {
        result->groups = new zvec_group_result_t[result->count];
        for (int i = 0; i < result->count; i++) {
            result->groups[i].docs = nullptr;
            result->groups[i].count = 0;
            result->groups[i].group_by_value = strdup(groups[i].group_by_value_.c_str());
            int doc_count = static_cast<int>(groups[i].docs_.size());
            result->groups[i].count = doc_count;
            if (doc_count > 0) {
                result->groups[i].docs = new zvec_doc_t[doc_count];
                for (int j = 0; j < doc_count; j++) {
                    result->groups[i].docs[j] = static_cast<zvec_doc_t>(new Doc(groups[i].docs_[j]));
                }
            }
        }
    } else {
        result->groups = nullptr;
    }
    return ok_status();
}

void zvec_group_results_free(zvec_group_results_t* result) {
    if (result->groups) {
        for (int i = 0; i < result->count; i++) {
            if (result->groups[i].group_by_value) {
                free(const_cast<char*>(result->groups[i].group_by_value));
            }
            if (result->groups[i].docs) {
                delete[] result->groups[i].docs;
            }
        }
        delete[] result->groups;
        result->groups = nullptr;
        result->count = 0;
    }
}

void zvec_query_result_free(zvec_query_result_t* result) {
    if (result->docs) {
        for (int i = 0; i < result->count; i++) {
            delete static_cast<Doc*>(result->docs[i]);
        }
        delete[] result->docs;
        result->docs = nullptr;
        result->count = 0;
    }
}

void zvec_query_result_free_array(zvec_query_result_t* result) {
    if (result->docs) {
        delete[] result->docs;
        result->docs = nullptr;
        result->count = 0;
    }
}

// --- Stats ---

zvec_status_t zvec_collection_stats(zvec_collection_t coll, char* buf, size_t buf_size) {
    auto* c = static_cast<Collection*>(coll);
    auto res = c->Stats();
    if (!res.has_value()) {
        return MAKE_STATUS(res.error());
    }
    auto str = res.value().to_string();
    strncpy(buf, str.c_str(), buf_size - 1);
    buf[buf_size - 1] = '\0';
    return ok_status();
}
