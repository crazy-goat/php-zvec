#include "zvec_ffi.h"

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
    return make_status(gc.Initialize(config));
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

// --- Collection ---

static std::vector<Collection::Ptr> g_collections;

zvec_status_t zvec_collection_create(const char* path, zvec_schema_t schema, int read_only, int enable_mmap, uint32_t max_buffer_size, zvec_collection_t* out) {
    auto* s = static_cast<CollectionSchema*>(schema);
    CollectionOptions opts{(bool)read_only, (bool)enable_mmap, max_buffer_size};
    auto result = Collection::CreateAndOpen(path, *s, opts);
    if (!result.has_value()) {
        *out = nullptr;
        return make_status(result.error());
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
        return make_status(result.error());
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
    return make_status(c->Flush());
}

zvec_status_t zvec_collection_optimize(zvec_collection_t coll, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    OptimizeOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return make_status(c->Optimize(opts));
}

zvec_status_t zvec_collection_destroy(zvec_collection_t coll) {
    auto* raw = static_cast<Collection*>(coll);
    for (auto it = g_collections.begin(); it != g_collections.end(); ++it) {
        if (it->get() == raw) {
            auto status = raw->Destroy();
            g_collections.erase(it);
            return make_status(status);
        }
    }
    zvec_status_t st;
    st.code = 1;
    strncpy(st.message, "collection not found", sizeof(st.message) - 1);
    return st;
}

// --- Inspect ---

zvec_status_t zvec_collection_schema(zvec_collection_t coll, char* buf, size_t buf_size) {
    auto* c = static_cast<Collection*>(coll);
    auto res = c->Schema();
    if (!res.has_value()) {
        return make_status(res.error());
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
        return make_status(res.error());
    }
    strncpy(buf, res.value().c_str(), buf_size - 1);
    buf[buf_size - 1] = '\0';
    return ok_status();
}

zvec_status_t zvec_collection_options(zvec_collection_t coll, int* read_only, int* enable_mmap, uint32_t* max_buffer_size) {
    auto* c = static_cast<Collection*>(coll);
    auto res = c->Options();
    if (!res.has_value()) {
        return make_status(res.error());
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
    return make_status(c->AddColumn(field, default_expr ? default_expr : "0", opts));
}

zvec_status_t zvec_collection_add_column_float(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    c->Flush();
    auto field = std::make_shared<FieldSchema>(name, DataType::FLOAT, (bool)nullable);
    AddColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return make_status(c->AddColumn(field, default_expr ? default_expr : "0", opts));
}

zvec_status_t zvec_collection_add_column_double(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    c->Flush();
    auto field = std::make_shared<FieldSchema>(name, DataType::DOUBLE, (bool)nullable);
    AddColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return make_status(c->AddColumn(field, default_expr ? default_expr : "0", opts));
}

zvec_status_t zvec_collection_add_column_string(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    c->Flush();
    auto field = std::make_shared<FieldSchema>(name, DataType::STRING, (bool)nullable);
    AddColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return make_status(c->AddColumn(field, default_expr ? default_expr : "", opts));
}

zvec_status_t zvec_collection_add_column_bool(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    c->Flush();
    auto field = std::make_shared<FieldSchema>(name, DataType::BOOL, (bool)nullable);
    AddColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return make_status(c->AddColumn(field, default_expr ? default_expr : "false", opts));
}

zvec_status_t zvec_collection_add_column_int32(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    c->Flush();
    auto field = std::make_shared<FieldSchema>(name, DataType::INT32, (bool)nullable);
    AddColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return make_status(c->AddColumn(field, default_expr ? default_expr : "0", opts));
}

zvec_status_t zvec_collection_add_column_uint32(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    c->Flush();
    auto field = std::make_shared<FieldSchema>(name, DataType::UINT32, (bool)nullable);
    AddColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return make_status(c->AddColumn(field, default_expr ? default_expr : "0", opts));
}

zvec_status_t zvec_collection_add_column_uint64(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    c->Flush();
    auto field = std::make_shared<FieldSchema>(name, DataType::UINT64, (bool)nullable);
    AddColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return make_status(c->AddColumn(field, default_expr ? default_expr : "0", opts));
}

zvec_status_t zvec_collection_drop_column(zvec_collection_t coll, const char* name) {
    auto* c = static_cast<Collection*>(coll);
    c->Flush();
    return make_status(c->DropColumn(name));
}

zvec_status_t zvec_collection_rename_column(zvec_collection_t coll, const char* old_name, const char* new_name, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    c->Flush();
    AlterColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return make_status(c->AlterColumn(old_name, new_name, nullptr, opts));
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
    return make_status(c->AlterColumn(column_name, rename_str, new_schema, opts));
}

zvec_status_t zvec_collection_create_invert_index(zvec_collection_t coll, const char* field_name, int enable_range, int enable_wildcard) {
    auto* c = static_cast<Collection*>(coll);
    auto params = std::make_shared<InvertIndexParams>((bool)enable_range, (bool)enable_wildcard);
    return make_status(c->CreateIndex(field_name, params));
}

zvec_status_t zvec_collection_create_hnsw_index(zvec_collection_t coll, const char* field_name, uint32_t metric_type, int m, int ef_construction, uint32_t quantize_type, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    auto params = std::make_shared<HnswIndexParams>(to_metric_type(metric_type), m, ef_construction, to_quantize_type(quantize_type));
    CreateIndexOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return make_status(c->CreateIndex(field_name, params, opts));
}

zvec_status_t zvec_collection_create_flat_index(zvec_collection_t coll, const char* field_name, uint32_t metric_type, uint32_t quantize_type, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    auto params = std::make_shared<FlatIndexParams>(to_metric_type(metric_type), to_quantize_type(quantize_type));
    CreateIndexOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return make_status(c->CreateIndex(field_name, params, opts));
}

zvec_status_t zvec_collection_create_ivf_index(zvec_collection_t coll, const char* field_name, uint32_t metric_type, int n_list, int n_iters, int use_soar, uint32_t quantize_type, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    auto params = std::make_shared<IVFIndexParams>(to_metric_type(metric_type), n_list, n_iters, (bool)use_soar, to_quantize_type(quantize_type));
    CreateIndexOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return make_status(c->CreateIndex(field_name, params, opts));
}

zvec_status_t zvec_collection_drop_index(zvec_collection_t coll, const char* field_name) {
    auto* c = static_cast<Collection*>(coll);
    return make_status(c->DropIndex(field_name));
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

// --- Doc Introspection ---

int zvec_doc_has_field(zvec_doc_t doc, const char* field) {
    return static_cast<Doc*>(doc)->has(field) ? 1 : 0;
}

int zvec_doc_has_vector(zvec_doc_t doc, const char* field) {
    auto* d = static_cast<Doc*>(doc);
    if (!d->has(field)) return 0;
    // Check if it's a FP32 vector (we don't support other vector types in FFI yet)
    auto result = d->get_field<std::vector<float>>(field);
    return result.ok() ? 1 : 0;
}

static thread_local std::string g_names_buf;

int zvec_doc_field_names(zvec_doc_t doc, char* buf, size_t buf_size) {
    auto* d = static_cast<Doc*>(doc);
    auto names = d->field_names();
    g_names_buf.clear();
    
    bool first = true;
    for (const auto& name : names) {
        // Skip vector fields (check if it's FP32 vector)
        if (d->get_field<std::vector<float>>(name).ok()) continue;
        
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
    g_names_buf.clear();
    
    bool first = true;
    for (const auto& name : names) {
        // Only include vector fields (FP32 vectors)
        if (!d->get_field<std::vector<float>>(name).ok()) continue;
        
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
        return make_status(res.error());
    }
    for (const auto& s : res.value()) {
        if (!s.ok()) return make_status(s);
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
        return make_status(res.error());
    }
    for (const auto& s : res.value()) {
        if (!s.ok()) return make_status(s);
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
        return make_status(res.error());
    }
    for (const auto& s : res.value()) {
        if (!s.ok()) return make_status(s);
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
        return make_status(res.error());
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
        return make_status(res.error());
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
        return make_status(res.error());
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
        return make_status(res.error());
    }
    return ok_status();
}

zvec_status_t zvec_collection_delete_by_filter(zvec_collection_t coll, const char* filter) {
    auto* c = static_cast<Collection*>(coll);
    return make_status(c->DeleteByFilter(filter));
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
        return make_status(res.error());
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
        return make_status(res.error());
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
        return make_status(schema_res.error());
    }
    
    const auto& schema = schema_res.value();
    const FieldSchema* field = schema.get_field(field_name);
    if (!field) {
        zvec_status_t st;
        st.code = static_cast<int>(StatusCode::INVALID_ARGUMENT);
        std::string msg = std::string("Field not found: ") + field_name;
        strncpy(st.message, msg.c_str(), sizeof(st.message) - 1);
        st.message[sizeof(st.message) - 1] = '\0';
        return st;
    }
    
    IndexType actual_index_type = field->index_type();
    
    // Map query_param_type to expected IndexType
    IndexType expected_index_type = IndexType::UNDEFINED;
    switch (query_param_type) {
        case 1: expected_index_type = IndexType::HNSW; break;
        case 2: expected_index_type = IndexType::IVF; break;
        case 3: expected_index_type = IndexType::FLAT; break;
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
        return make_status(res.error());
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
        return make_status(res.error());
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
        return make_status(res.error());
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
        return make_status(res.error());
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
        return make_status(res.error());
    }
    auto str = res.value().to_string();
    strncpy(buf, str.c_str(), buf_size - 1);
    buf[buf_size - 1] = '\0';
    return ok_status();
}
