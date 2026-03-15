#include "zvec_exception.h"
#include "zvec_vector_query.h"
#include "zvec_reranker.h"

#pragma push_macro("IS_NULL")
#undef IS_NULL

#include "zvec_collection.h"
#include "zvec_schema.h"
#include "zvec_doc.h"
#include <zvec/db/collection.h>
#include <zvec/db/doc.h>
#include <zvec/db/schema.h>
#include <zvec/db/index_params.h>
#include <zvec/db/options.h>
#include <zvec/db/config.h>
#include <zvec/db/status.h>
#include <zvec/ailego/utility/float_helper.h>

#pragma pop_macro("IS_NULL")

#include <vector>
#include <string>
#include <cstring>

using namespace zvec;

zend_class_entry *zvec_collection_ce = nullptr;
static zend_object_handlers zvec_collection_handlers;

static zend_object *zvec_collection_create_object_handler(zend_class_entry *ce) {
    auto *intern = static_cast<zvec_collection_object *>(
        ecalloc(1, sizeof(zvec_collection_object) + zend_object_properties_size(ce)));
    new (&intern->collection) Collection::Ptr(nullptr);
    intern->closed = true;
    zend_object_std_init(&intern->std, ce);
    object_properties_init(&intern->std, ce);
    intern->std.handlers = &zvec_collection_handlers;
    return &intern->std;
}

static void zvec_collection_free_object(zend_object *obj) {
    auto *intern = zvec_collection_from_obj(obj);
    intern->collection.reset();
    intern->collection.~shared_ptr();
    zend_object_std_dtor(obj);
}

static inline void check_closed(zvec_collection_object *intern) {
    if (intern->closed) {
        zvec_throw_exception(0, "Collection is closed or destroyed");
    }
}

static inline bool check_status(const Status &s) {
    if (!s.ok()) {
        zvec_throw_exception(static_cast<int>(s.code()), "%s", s.message().c_str());
        return false;
    }
    return true;
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

static std::vector<Doc> docs_from_args(zval *args, uint32_t argc) {
    std::vector<Doc> doc_vec;
    doc_vec.reserve(argc);
    for (uint32_t i = 0; i < argc; i++) {
        zval *z = &args[i];
        if (Z_TYPE_P(z) != IS_OBJECT || !instanceof_function(Z_OBJCE_P(z), zvec_doc_ce)) {
            zvec_throw_exception(0, "Argument %d must be a ZVecDoc instance", i + 1);
            return {};
        }
        auto *doc_obj = Z_ZVEC_DOC_P(z);
        doc_vec.push_back(*doc_obj->doc);
    }
    return doc_vec;
}

PHP_METHOD(ZVec, init) {
    zend_long log_type = 0, log_level = 2;
    char *log_dir = nullptr; size_t log_dir_len = 0;
    char *log_basename = nullptr; size_t log_basename_len = 0;
    zend_long log_file_size = 0, log_overdue_days = 0;
    zend_long query_threads = 0, optimize_threads = 0;
    double invert_ratio = 0.0, brute_ratio = 0.0;
    zend_long memory_limit = 0;

    ZEND_PARSE_PARAMETERS_START(0, 11)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(log_type)
        Z_PARAM_LONG(log_level)
        Z_PARAM_STRING_OR_NULL(log_dir, log_dir_len)
        Z_PARAM_STRING_OR_NULL(log_basename, log_basename_len)
        Z_PARAM_LONG(log_file_size)
        Z_PARAM_LONG(log_overdue_days)
        Z_PARAM_LONG(query_threads)
        Z_PARAM_LONG(optimize_threads)
        Z_PARAM_DOUBLE(invert_ratio)
        Z_PARAM_DOUBLE(brute_ratio)
        Z_PARAM_LONG(memory_limit)
    ZEND_PARSE_PARAMETERS_END();

    GlobalConfig::ConfigData config;
    auto lvl = static_cast<GlobalConfig::LogLevel>(log_level);

    if (log_type == 1) {
        config.log_config = std::make_shared<GlobalConfig::FileLogConfig>(
            lvl,
            log_dir ? log_dir : DEFAULT_LOG_DIR,
            log_basename ? log_basename : DEFAULT_LOG_BASENAME,
            log_file_size > 0 ? static_cast<uint32_t>(log_file_size) : DEFAULT_LOG_FILE_SIZE,
            log_overdue_days > 0 ? static_cast<uint32_t>(log_overdue_days) : DEFAULT_LOG_OVERDUE_DAYS);
    } else {
        config.log_config = std::make_shared<GlobalConfig::ConsoleLogConfig>(lvl);
    }

    if (query_threads > 0) config.query_thread_count = static_cast<uint32_t>(query_threads);
    if (optimize_threads > 0) config.optimize_thread_count = static_cast<uint32_t>(optimize_threads);
    if (invert_ratio > 0.0) config.invert_to_forward_scan_ratio = static_cast<float>(invert_ratio);
    if (brute_ratio > 0.0) config.brute_force_by_keys_ratio = static_cast<float>(brute_ratio);
    if (memory_limit > 0) config.memory_limit_bytes = static_cast<uint64_t>(memory_limit) * 1024ULL * 1024ULL;

    auto &gc = GlobalConfig::Instance();
    check_status(gc.Initialize(config));
}

PHP_METHOD(ZVec, create) {
    char *path; size_t path_len;
    zval *schema_zv;
    zend_bool read_only = 0, enable_mmap = 1;
    zend_long max_buffer_size = 67108864;

    ZEND_PARSE_PARAMETERS_START(2, 5)
        Z_PARAM_STRING(path, path_len)
        Z_PARAM_OBJECT_OF_CLASS(schema_zv, zvec_schema_ce)
        Z_PARAM_OPTIONAL
        Z_PARAM_BOOL(read_only)
        Z_PARAM_BOOL(enable_mmap)
        Z_PARAM_LONG(max_buffer_size)
    ZEND_PARSE_PARAMETERS_END();

    auto *schema = zvec_schema_get_native(schema_zv);
    CollectionOptions opts{(bool)read_only, (bool)enable_mmap, static_cast<uint32_t>(max_buffer_size)};
    auto result = Collection::CreateAndOpen(std::string(path, path_len), *schema, opts);
    if (!result.has_value()) {
        check_status(result.error());
        RETURN_THROWS();
    }

    object_init_ex(return_value, zvec_collection_ce);
    auto *intern = Z_ZVEC_COLLECTION_P(return_value);
    intern->collection = std::move(result).value();
    intern->closed = false;
}

PHP_METHOD(ZVec, open) {
    char *path; size_t path_len;
    zend_bool read_only = 0, enable_mmap = 1;
    zend_long max_buffer_size = 67108864;

    ZEND_PARSE_PARAMETERS_START(1, 4)
        Z_PARAM_STRING(path, path_len)
        Z_PARAM_OPTIONAL
        Z_PARAM_BOOL(read_only)
        Z_PARAM_BOOL(enable_mmap)
        Z_PARAM_LONG(max_buffer_size)
    ZEND_PARSE_PARAMETERS_END();

    CollectionOptions opts{(bool)read_only, (bool)enable_mmap, static_cast<uint32_t>(max_buffer_size)};
    auto result = Collection::Open(std::string(path, path_len), opts);
    if (!result.has_value()) {
        check_status(result.error());
        RETURN_THROWS();
    }

    object_init_ex(return_value, zvec_collection_ce);
    auto *intern = Z_ZVEC_COLLECTION_P(return_value);
    intern->collection = std::move(result).value();
    intern->closed = false;
}

PHP_METHOD(ZVec, close) {
    ZEND_PARSE_PARAMETERS_NONE();
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    if (!intern->closed) {
        intern->collection.reset();
        intern->closed = true;
    }
}

PHP_METHOD(ZVec, __destruct) {
    ZEND_PARSE_PARAMETERS_NONE();
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    if (!intern->closed) {
        intern->collection.reset();
        intern->closed = true;
    }
}

PHP_METHOD(ZVec, flush) {
    ZEND_PARSE_PARAMETERS_NONE();
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();
    check_status(intern->collection->Flush());
}

PHP_METHOD(ZVec, optimize) {
    zend_long concurrency = 0;
    ZEND_PARSE_PARAMETERS_START(0, 1)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(concurrency)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();
    OptimizeOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    check_status(intern->collection->Optimize(opts));
}

PHP_METHOD(ZVec, destroy) {
    ZEND_PARSE_PARAMETERS_NONE();
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    if (!intern->closed) {
        auto status = intern->collection->Destroy();
        intern->collection.reset();
        intern->closed = true;
        check_status(status);
    }
}

PHP_METHOD(ZVec, schema) {
    ZEND_PARSE_PARAMETERS_NONE();
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();
    auto res = intern->collection->Schema();
    if (!res.has_value()) { check_status(res.error()); RETURN_THROWS(); }
    auto str = res.value().to_string();
    RETURN_STRINGL(str.c_str(), str.length());
}

PHP_METHOD(ZVec, path) {
    ZEND_PARSE_PARAMETERS_NONE();
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();
    auto res = intern->collection->Path();
    if (!res.has_value()) { check_status(res.error()); RETURN_THROWS(); }
    RETURN_STRINGL(res.value().c_str(), res.value().length());
}

PHP_METHOD(ZVec, options) {
    ZEND_PARSE_PARAMETERS_NONE();
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();
    auto res = intern->collection->Options();
    if (!res.has_value()) { check_status(res.error()); RETURN_THROWS(); }
    array_init(return_value);
    add_assoc_bool(return_value, "read_only", res.value().read_only_);
    add_assoc_bool(return_value, "enable_mmap", res.value().enable_mmap_);
    add_assoc_long(return_value, "max_buffer_size", static_cast<zend_long>(res.value().max_buffer_size_));
}

PHP_METHOD(ZVec, stats) {
    ZEND_PARSE_PARAMETERS_NONE();
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();
    auto res = intern->collection->Stats();
    if (!res.has_value()) { check_status(res.error()); RETURN_THROWS(); }
    auto str = res.value().to_string();
    RETURN_STRINGL(str.c_str(), str.length());
}

// --- Column DDL ---

#define ZVEC_ADD_COLUMN_METHOD(php_name, data_type, default_expr_val) \
PHP_METHOD(ZVec, php_name) { \
    char *name; size_t name_len; \
    zend_bool nullable = 1; \
    char *default_expr = nullptr; size_t default_expr_len = 0; \
    zend_long concurrency = 0; \
    ZEND_PARSE_PARAMETERS_START(1, 4) \
        Z_PARAM_STRING(name, name_len) \
        Z_PARAM_OPTIONAL \
        Z_PARAM_BOOL(nullable) \
        Z_PARAM_STRING(default_expr, default_expr_len) \
        Z_PARAM_LONG(concurrency) \
    ZEND_PARSE_PARAMETERS_END(); \
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS); \
    check_closed(intern); \
    if (EG(exception)) RETURN_THROWS(); \
    intern->collection->Flush(); \
    auto field = std::make_shared<FieldSchema>(std::string(name, name_len), data_type, (bool)nullable); \
    AddColumnOptions opts; \
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency); \
    check_status(intern->collection->AddColumn(field, default_expr ? default_expr : default_expr_val, opts)); \
}

ZVEC_ADD_COLUMN_METHOD(addColumnInt64, DataType::INT64, "0")
ZVEC_ADD_COLUMN_METHOD(addColumnFloat, DataType::FLOAT, "0")
ZVEC_ADD_COLUMN_METHOD(addColumnDouble, DataType::DOUBLE, "0")
ZVEC_ADD_COLUMN_METHOD(addColumnString, DataType::STRING, "")
ZVEC_ADD_COLUMN_METHOD(addColumnBool, DataType::BOOL, "false")
ZVEC_ADD_COLUMN_METHOD(addColumnInt32, DataType::INT32, "0")
ZVEC_ADD_COLUMN_METHOD(addColumnUint32, DataType::UINT32, "0")
ZVEC_ADD_COLUMN_METHOD(addColumnUint64, DataType::UINT64, "0")

PHP_METHOD(ZVec, dropColumn) {
    char *name; size_t name_len;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING(name, name_len)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();
    intern->collection->Flush();
    check_status(intern->collection->DropColumn(std::string(name, name_len)));
}

PHP_METHOD(ZVec, renameColumn) {
    char *old_name; size_t old_name_len;
    char *new_name; size_t new_name_len;
    zend_long concurrency = 0;
    ZEND_PARSE_PARAMETERS_START(2, 3)
        Z_PARAM_STRING(old_name, old_name_len)
        Z_PARAM_STRING(new_name, new_name_len)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(concurrency)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();
    intern->collection->Flush();
    AlterColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    check_status(intern->collection->AlterColumn(
        std::string(old_name, old_name_len), std::string(new_name, new_name_len), nullptr, opts));
}

PHP_METHOD(ZVec, alterColumn) {
    char *col_name; size_t col_name_len;
    char *new_name = nullptr; size_t new_name_len = 0;
    zval *new_data_type_zv = nullptr;
    zval *nullable_zv = nullptr;
    zend_long concurrency = 0;

    ZEND_PARSE_PARAMETERS_START(1, 5)
        Z_PARAM_STRING(col_name, col_name_len)
        Z_PARAM_OPTIONAL
        Z_PARAM_STRING_OR_NULL(new_name, new_name_len)
        Z_PARAM_ZVAL_OR_NULL(new_data_type_zv)
        Z_PARAM_ZVAL_OR_NULL(nullable_zv)
        Z_PARAM_LONG(concurrency)
    ZEND_PARSE_PARAMETERS_END();

    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();
    intern->collection->Flush();

    uint32_t data_type = 0;
    if (new_data_type_zv && Z_TYPE_P(new_data_type_zv) == IS_LONG) {
        data_type = static_cast<uint32_t>(Z_LVAL_P(new_data_type_zv));
    }

    bool is_nullable = false;
    if (nullable_zv && Z_TYPE_P(nullable_zv) != IS_NULL) {
        is_nullable = zend_is_true(nullable_zv);
    }

    DataType dt = DataType::UNDEFINED;
    switch (data_type) {
        case 4: dt = DataType::INT32; break;
        case 5: dt = DataType::INT64; break;
        case 6: dt = DataType::UINT32; break;
        case 7: dt = DataType::UINT64; break;
        case 8: dt = DataType::FLOAT; break;
        case 9: dt = DataType::DOUBLE; break;
    }

    FieldSchema::Ptr new_schema = nullptr;
    if (dt != DataType::UNDEFINED) {
        new_schema = std::make_shared<FieldSchema>(std::string(col_name, col_name_len), dt, is_nullable);
    }

    std::string rename_str = new_name ? std::string(new_name, new_name_len) : "";
    AlterColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    check_status(intern->collection->AlterColumn(
        std::string(col_name, col_name_len), rename_str, new_schema, opts));
}

// --- Index DDL ---

PHP_METHOD(ZVec, createInvertIndex) {
    char *field; size_t field_len;
    zend_bool enable_range = 1, enable_wildcard = 0;
    ZEND_PARSE_PARAMETERS_START(1, 3)
        Z_PARAM_STRING(field, field_len)
        Z_PARAM_OPTIONAL
        Z_PARAM_BOOL(enable_range)
        Z_PARAM_BOOL(enable_wildcard)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();
    auto params = std::make_shared<InvertIndexParams>((bool)enable_range, (bool)enable_wildcard);
    check_status(intern->collection->CreateIndex(std::string(field, field_len), params));
}

PHP_METHOD(ZVec, createHnswIndex) {
    char *field; size_t field_len;
    zend_long metric_type = 2, m = 50, ef_construction = 500, quantize_type = 0, concurrency = 0;
    ZEND_PARSE_PARAMETERS_START(1, 6)
        Z_PARAM_STRING(field, field_len)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(metric_type)
        Z_PARAM_LONG(m)
        Z_PARAM_LONG(ef_construction)
        Z_PARAM_LONG(quantize_type)
        Z_PARAM_LONG(concurrency)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();
    auto params = std::make_shared<HnswIndexParams>(
        to_metric_type(static_cast<uint32_t>(metric_type)),
        static_cast<int>(m), static_cast<int>(ef_construction),
        to_quantize_type(static_cast<uint32_t>(quantize_type)));
    CreateIndexOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    check_status(intern->collection->CreateIndex(std::string(field, field_len), params, opts));
}

PHP_METHOD(ZVec, createFlatIndex) {
    char *field; size_t field_len;
    zend_long metric_type = 2, quantize_type = 0, concurrency = 0;
    ZEND_PARSE_PARAMETERS_START(1, 4)
        Z_PARAM_STRING(field, field_len)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(metric_type)
        Z_PARAM_LONG(quantize_type)
        Z_PARAM_LONG(concurrency)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();
    auto params = std::make_shared<FlatIndexParams>(
        to_metric_type(static_cast<uint32_t>(metric_type)),
        to_quantize_type(static_cast<uint32_t>(quantize_type)));
    CreateIndexOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    check_status(intern->collection->CreateIndex(std::string(field, field_len), params, opts));
}

PHP_METHOD(ZVec, createIvfIndex) {
    char *field; size_t field_len;
    zend_long metric_type = 2, n_list = 1024, n_iters = 10, quantize_type = 0, concurrency = 0;
    zend_bool use_soar = 0;
    ZEND_PARSE_PARAMETERS_START(1, 7)
        Z_PARAM_STRING(field, field_len)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(metric_type)
        Z_PARAM_LONG(n_list)
        Z_PARAM_LONG(n_iters)
        Z_PARAM_BOOL(use_soar)
        Z_PARAM_LONG(quantize_type)
        Z_PARAM_LONG(concurrency)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();
    auto params = std::make_shared<IVFIndexParams>(
        to_metric_type(static_cast<uint32_t>(metric_type)),
        static_cast<int>(n_list), static_cast<int>(n_iters),
        (bool)use_soar, to_quantize_type(static_cast<uint32_t>(quantize_type)));
    CreateIndexOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    check_status(intern->collection->CreateIndex(std::string(field, field_len), params, opts));
}

PHP_METHOD(ZVec, dropIndex) {
    char *field; size_t field_len;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING(field, field_len)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();
    check_status(intern->collection->DropIndex(std::string(field, field_len)));
}

// --- Insert / Upsert / Update ---

PHP_METHOD(ZVec, insert) {
    zval *args; uint32_t argc;
    ZEND_PARSE_PARAMETERS_START(1, -1)
        Z_PARAM_VARIADIC('+', args, argc)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();
    auto doc_vec = docs_from_args(args, argc);
    if (EG(exception)) RETURN_THROWS();
    auto res = intern->collection->Insert(doc_vec);
    if (!res.has_value()) { check_status(res.error()); RETURN_THROWS(); }
    for (const auto &s : res.value()) {
        if (!s.ok()) { check_status(s); RETURN_THROWS(); }
    }
}

PHP_METHOD(ZVec, upsert) {
    zval *args; uint32_t argc;
    ZEND_PARSE_PARAMETERS_START(1, -1)
        Z_PARAM_VARIADIC('+', args, argc)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();
    auto doc_vec = docs_from_args(args, argc);
    if (EG(exception)) RETURN_THROWS();
    auto res = intern->collection->Upsert(doc_vec);
    if (!res.has_value()) { check_status(res.error()); RETURN_THROWS(); }
    for (const auto &s : res.value()) {
        if (!s.ok()) { check_status(s); RETURN_THROWS(); }
    }
}

PHP_METHOD(ZVec, update) {
    zval *args; uint32_t argc;
    ZEND_PARSE_PARAMETERS_START(1, -1)
        Z_PARAM_VARIADIC('+', args, argc)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();
    auto doc_vec = docs_from_args(args, argc);
    if (EG(exception)) RETURN_THROWS();
    auto res = intern->collection->Update(doc_vec);
    if (!res.has_value()) { check_status(res.error()); RETURN_THROWS(); }
    for (const auto &s : res.value()) {
        if (!s.ok()) { check_status(s); RETURN_THROWS(); }
    }
}

// --- Batch operations ---

template<typename F>
static void do_batch_op(INTERNAL_FUNCTION_PARAMETERS, F op) {
    zval *args; uint32_t argc;
    ZEND_PARSE_PARAMETERS_START(1, -1)
        Z_PARAM_VARIADIC('+', args, argc)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();

    std::vector<std::string> pks;
    std::vector<Doc> doc_vec;
    doc_vec.reserve(argc);
    pks.reserve(argc);
    for (uint32_t i = 0; i < argc; i++) {
        zval *z = &args[i];
        if (Z_TYPE_P(z) != IS_OBJECT || !instanceof_function(Z_OBJCE_P(z), zvec_doc_ce)) {
            zvec_throw_exception(0, "Argument %d must be a ZVecDoc instance", i + 1);
            RETURN_THROWS();
        }
        auto *doc_obj = Z_ZVEC_DOC_P(z);
        doc_vec.push_back(*doc_obj->doc);
        pks.push_back(doc_obj->doc->pk());
    }

    auto res = op(intern->collection.get(), doc_vec);
    if (!res.has_value()) { check_status(res.error()); RETURN_THROWS(); }

    const auto &statuses = res.value();
    array_init_size(return_value, statuses.size());
    for (size_t i = 0; i < statuses.size(); i++) {
        zval entry;
        array_init(&entry);
        add_assoc_string(&entry, "pk", pks[i].c_str());
        add_assoc_bool(&entry, "ok", statuses[i].ok());
        if (!statuses[i].ok()) {
            add_assoc_string(&entry, "error", statuses[i].message().c_str());
        } else {
            add_assoc_null(&entry, "error");
        }
        add_next_index_zval(return_value, &entry);
    }
}

PHP_METHOD(ZVec, insertBatch) {
    do_batch_op(INTERNAL_FUNCTION_PARAM_PASSTHRU,
        [](Collection *c, std::vector<Doc> &docs) { return c->Insert(docs); });
}

PHP_METHOD(ZVec, upsertBatch) {
    do_batch_op(INTERNAL_FUNCTION_PARAM_PASSTHRU,
        [](Collection *c, std::vector<Doc> &docs) { return c->Upsert(docs); });
}

PHP_METHOD(ZVec, updateBatch) {
    do_batch_op(INTERNAL_FUNCTION_PARAM_PASSTHRU,
        [](Collection *c, std::vector<Doc> &docs) { return c->Update(docs); });
}

// --- Delete ---

PHP_METHOD(ZVec, delete) {
    zval *args; uint32_t argc;
    ZEND_PARSE_PARAMETERS_START(1, -1)
        Z_PARAM_VARIADIC('+', args, argc)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();
    std::vector<std::string> pks;
    pks.reserve(argc);
    for (uint32_t i = 0; i < argc; i++) {
        if (Z_TYPE(args[i]) != IS_STRING) {
            zvec_throw_exception(0, "Argument %d must be a string", i + 1);
            RETURN_THROWS();
        }
        pks.emplace_back(Z_STRVAL(args[i]), Z_STRLEN(args[i]));
    }
    auto res = intern->collection->Delete(pks);
    if (!res.has_value()) { check_status(res.error()); RETURN_THROWS(); }
}

PHP_METHOD(ZVec, deleteByFilter) {
    char *filter; size_t filter_len;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING(filter, filter_len)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();
    check_status(intern->collection->DeleteByFilter(std::string(filter, filter_len)));
}

// --- Fetch ---

PHP_METHOD(ZVec, fetch) {
    zval *args; uint32_t argc;
    ZEND_PARSE_PARAMETERS_START(1, -1)
        Z_PARAM_VARIADIC('+', args, argc)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();
    std::vector<std::string> pks;
    pks.reserve(argc);
    for (uint32_t i = 0; i < argc; i++) {
        if (Z_TYPE(args[i]) != IS_STRING) {
            zvec_throw_exception(0, "Argument %d must be a string", i + 1);
            RETURN_THROWS();
        }
        pks.emplace_back(Z_STRVAL(args[i]), Z_STRLEN(args[i]));
    }
    auto res = intern->collection->Fetch(pks);
    if (!res.has_value()) { check_status(res.error()); RETURN_THROWS(); }

    array_init(return_value);
    for (auto &[k, v] : res.value()) {
        if (v) {
            auto *doc = new Doc(*v);
            zend_object *obj = zvec_doc_create_from_native(doc, true);
            zval zv;
            ZVAL_OBJ(&zv, obj);
            add_next_index_zval(return_value, &zv);
        }
    }
}

// --- Query helpers ---

static void apply_output_fields(VectorQuery &query, zval *output_fields) {
    if (!output_fields || Z_TYPE_P(output_fields) != IS_ARRAY) return;
    HashTable *ht = Z_ARRVAL_P(output_fields);
    std::vector<std::string> fields;
    zval *val;
    ZEND_HASH_FOREACH_VAL(ht, val) {
        fields.emplace_back(Z_STRVAL_P(val), Z_STRLEN_P(val));
    } ZEND_HASH_FOREACH_END();
    query.output_fields_ = std::move(fields);
}

static void apply_query_params(VectorQuery &query, int type, int hnsw_ef, int ivf_nprobe,
                               float radius, bool is_linear, bool is_using_refiner) {
    if (type == 1) {
        query.query_params_ = std::make_shared<HnswQueryParams>(hnsw_ef, radius, is_linear, is_using_refiner);
    } else if (type == 2) {
        auto params = std::make_shared<IVFQueryParams>(ivf_nprobe, is_using_refiner);
        params->set_radius(radius);
        params->set_is_linear(is_linear);
        query.query_params_ = params;
    } else if (type == 3) {
        auto params = std::make_shared<FlatQueryParams>(is_using_refiner);
        params->set_radius(radius);
        params->set_is_linear(is_linear);
        query.query_params_ = params;
    }
}

static void fill_results(zval *return_value, const DocPtrList &doc_list) {
    array_init_size(return_value, doc_list.size());
    for (size_t i = 0; i < doc_list.size(); i++) {
        auto *doc = new Doc(*doc_list[i]);
        zend_object *obj = zvec_doc_create_from_native(doc, true);
        zval zv;
        ZVAL_OBJ(&zv, obj);
        add_next_index_zval(return_value, &zv);
    }
}

static bool validate_query_param_type(Collection *c, const std::string &field_name, int query_param_type) {
    if (query_param_type == 0) return true;
    auto schema_res = c->Schema();
    if (!schema_res.has_value()) { check_status(schema_res.error()); return false; }
    const auto &schema = schema_res.value();
    const FieldSchema *field = schema.get_field(field_name);
    if (!field) {
        zvec_throw_exception(static_cast<int>(StatusCode::INVALID_ARGUMENT), "Field not found: %s", field_name.c_str());
        return false;
    }
    IndexType actual = field->index_type();
    IndexType expected = IndexType::UNDEFINED;
    switch (query_param_type) {
        case 1: expected = IndexType::HNSW; break;
        case 2: expected = IndexType::IVF; break;
        case 3: expected = IndexType::FLAT; break;
    }
    if (expected != IndexType::UNDEFINED && actual != IndexType::UNDEFINED && actual != expected) {
        zvec_throw_exception(static_cast<int>(StatusCode::INVALID_ARGUMENT),
            "Query parameter type mismatch for field '%s': index type does not match query_param_type",
            field_name.c_str());
        return false;
    }
    return true;
}

// --- Query ---

PHP_METHOD(ZVec, query) {
    zval *field_name_zv;
    zval *query_vector_zv = nullptr;
    zend_long topk = 10;
    zend_bool include_vector = 0;
    char *filter = nullptr; size_t filter_len = 0;
    zval *output_fields = nullptr;
    zend_long query_param_type = 0, hnsw_ef = 200, ivf_nprobe = 10;
    double radius = 0.0;
    zend_bool is_linear = 0, is_using_refiner = 0;
    zval *reranker_zv = nullptr;

    ZEND_PARSE_PARAMETERS_START(1, 13)
        Z_PARAM_ZVAL(field_name_zv)
        Z_PARAM_OPTIONAL
        Z_PARAM_ARRAY(query_vector_zv)
        Z_PARAM_LONG(topk)
        Z_PARAM_BOOL(include_vector)
        Z_PARAM_STRING_OR_NULL(filter, filter_len)
        Z_PARAM_ARRAY_OR_NULL(output_fields)
        Z_PARAM_LONG(query_param_type)
        Z_PARAM_LONG(hnsw_ef)
        Z_PARAM_LONG(ivf_nprobe)
        Z_PARAM_DOUBLE(radius)
        Z_PARAM_BOOL(is_linear)
        Z_PARAM_BOOL(is_using_refiner)
        Z_PARAM_ZVAL_OR_NULL(reranker_zv)
    ZEND_PARSE_PARAMETERS_END();

    if (reranker_zv && Z_TYPE_P(reranker_zv) != IS_NULL &&
        (Z_TYPE_P(reranker_zv) != IS_OBJECT || !instanceof_function(Z_OBJCE_P(reranker_zv), zvec_reranker_ce))) {
        zend_type_error("ZVec::query(): Argument #14 ($reranker) must be of type ?ZVecReRanker, %s given",
            zend_zval_value_name(reranker_zv));
        RETURN_THROWS();
    }
    if (reranker_zv && Z_TYPE_P(reranker_zv) == IS_NULL) {
        reranker_zv = nullptr;
    }

    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();

    std::string field_name_str;
    std::vector<float> vec_data;

    if (Z_TYPE_P(field_name_zv) == IS_OBJECT && instanceof_function(Z_OBJCE_P(field_name_zv), zvec_vector_query_ce)) {
        zval *fn = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(field_name_zv), "fieldName", sizeof("fieldName") - 1, 1, nullptr);
        field_name_str = std::string(Z_STRVAL_P(fn), Z_STRLEN_P(fn));
        zval *vec = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(field_name_zv), "vector", sizeof("vector") - 1, 1, nullptr);
        if (Z_TYPE_P(vec) == IS_ARRAY) {
            zval *v;
            ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(vec), v) {
                vec_data.push_back(static_cast<float>(zval_get_double(v)));
            } ZEND_HASH_FOREACH_END();
        }
        zval *qpt = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(field_name_zv), "queryParamType", sizeof("queryParamType") - 1, 1, nullptr);
        query_param_type = Z_LVAL_P(qpt);
        zval *ef = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(field_name_zv), "hnswEf", sizeof("hnswEf") - 1, 1, nullptr);
        hnsw_ef = Z_LVAL_P(ef);
        zval *np = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(field_name_zv), "ivfNprobe", sizeof("ivfNprobe") - 1, 1, nullptr);
        ivf_nprobe = Z_LVAL_P(np);
        zval *rad = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(field_name_zv), "radius", sizeof("radius") - 1, 1, nullptr);
        radius = Z_DVAL_P(rad);
        zval *lin = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(field_name_zv), "isLinear", sizeof("isLinear") - 1, 1, nullptr);
        is_linear = zend_is_true(lin);
        zval *ref = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(field_name_zv), "isUsingRefiner", sizeof("isUsingRefiner") - 1, 1, nullptr);
        is_using_refiner = zend_is_true(ref);

        zval *did = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(field_name_zv), "docId", sizeof("docId") - 1, 1, nullptr);
        if (Z_TYPE_P(did) == IS_STRING) {
            zvec_throw_exception(0, "query() with docId not yet implemented. Use queryById() or fetch the vector first.");
            RETURN_THROWS();
        }
    } else if (Z_TYPE_P(field_name_zv) == IS_STRING) {
        field_name_str = std::string(Z_STRVAL_P(field_name_zv), Z_STRLEN_P(field_name_zv));
        if (query_vector_zv && Z_TYPE_P(query_vector_zv) == IS_ARRAY) {
            zval *v;
            ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(query_vector_zv), v) {
                vec_data.push_back(static_cast<float>(zval_get_double(v)));
            } ZEND_HASH_FOREACH_END();
        }
    } else {
        zvec_throw_exception(0, "First argument must be a string or ZVecVectorQuery");
        RETURN_THROWS();
    }

    zend_long fetch_topk = (reranker_zv != nullptr) ? std::max(topk * 2, (zend_long)100) : topk;

    if (!validate_query_param_type(intern->collection.get(), field_name_str, static_cast<int>(query_param_type))) {
        RETURN_THROWS();
    }

    VectorQuery query;
    query.topk_ = static_cast<int>(fetch_topk);
    query.field_name_ = field_name_str;
    query.include_vector_ = (bool)include_vector;
    query.query_vector_.assign(reinterpret_cast<const char *>(vec_data.data()), vec_data.size() * sizeof(float));
    if (filter && filter[0] != '\0') {
        query.filter_ = std::string(filter, filter_len);
    }
    if (output_fields) apply_output_fields(query, output_fields);
    if (query_param_type != 0) {
        apply_query_params(query, static_cast<int>(query_param_type),
            static_cast<int>(hnsw_ef), static_cast<int>(ivf_nprobe),
            static_cast<float>(radius), (bool)is_linear, (bool)is_using_refiner);
    }

    auto res = intern->collection->Query(query);
    if (!res.has_value()) { check_status(res.error()); RETURN_THROWS(); }

    if (reranker_zv != nullptr && Z_TYPE_P(reranker_zv) == IS_OBJECT) {
        zval docs_arr;
        fill_results(&docs_arr, res.value());

        zval query_results;
        array_init(&query_results);
        Z_TRY_ADDREF(docs_arr);
        add_assoc_zval(&query_results, field_name_str.c_str(), &docs_arr);

        zval rerank_args[1];
        ZVAL_COPY_VALUE(&rerank_args[0], &query_results);

        zval rerank_result;
        ZVAL_UNDEF(&rerank_result);
        zval method_name;
        ZVAL_STRING(&method_name, "rerank");
        int ret = call_user_function(NULL, reranker_zv, &method_name, &rerank_result, 1, rerank_args);
        zval_ptr_dtor(&method_name);
        zval_ptr_dtor(&docs_arr);
        zval_ptr_dtor(&query_results);

        if (ret == FAILURE || EG(exception)) {
            zval_ptr_dtor(&rerank_result);
            if (!EG(exception)) {
                zvec_throw_exception(0, "Failed to call reranker->rerank()");
            }
            RETURN_THROWS();
        }

        RETURN_COPY_VALUE(&rerank_result);
    }

    fill_results(return_value, res.value());
}

// --- queryFp16 ---

PHP_METHOD(ZVec, queryFp16) {
    char *field; size_t field_len;
    zval *query_vector_zv;
    zend_long topk = 10;
    zend_bool include_vector = 0;
    char *filter = nullptr; size_t filter_len = 0;

    ZEND_PARSE_PARAMETERS_START(2, 5)
        Z_PARAM_STRING(field, field_len)
        Z_PARAM_ARRAY(query_vector_zv)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(topk)
        Z_PARAM_BOOL(include_vector)
        Z_PARAM_STRING_OR_NULL(filter, filter_len)
    ZEND_PARSE_PARAMETERS_END();

    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();

    HashTable *ht = Z_ARRVAL_P(query_vector_zv);
    uint32_t dim = zend_hash_num_elements(ht);
    std::vector<ailego::Float16> fp16_vec;
    fp16_vec.reserve(dim);
    zval *v;
    ZEND_HASH_FOREACH_VAL(ht, v) {
        fp16_vec.push_back(ailego::FloatHelper::ToFP32(static_cast<uint16_t>(zval_get_long(v))));
    } ZEND_HASH_FOREACH_END();

    VectorQuery query;
    query.topk_ = static_cast<int>(topk);
    query.field_name_ = std::string(field, field_len);
    query.include_vector_ = (bool)include_vector;
    query.query_vector_.assign(reinterpret_cast<const char *>(fp16_vec.data()), dim * sizeof(ailego::Float16));
    if (filter && filter[0] != '\0') query.filter_ = std::string(filter, filter_len);

    auto res = intern->collection->Query(query);
    if (!res.has_value()) { check_status(res.error()); RETURN_THROWS(); }
    fill_results(return_value, res.value());
}

// --- queryMulti ---

PHP_METHOD(ZVec, queryMulti) {
    zval *queries_zv, *reranker_zv;
    zend_long topk = 10;
    char *filter = nullptr; size_t filter_len = 0;
    zval *output_fields = nullptr;

    ZEND_PARSE_PARAMETERS_START(2, 5)
        Z_PARAM_ARRAY(queries_zv)
        Z_PARAM_OBJECT(reranker_zv)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(topk)
        Z_PARAM_STRING_OR_NULL(filter, filter_len)
        Z_PARAM_ARRAY_OR_NULL(output_fields)
    ZEND_PARSE_PARAMETERS_END();

    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();

    if (zend_hash_num_elements(Z_ARRVAL_P(queries_zv)) == 0) {
        zvec_throw_exception(0, "At least one vector query is required");
        RETURN_THROWS();
    }

    zval query_results;
    array_init(&query_results);

    zval *vq_zv;
    ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(queries_zv), vq_zv) {
        if (Z_TYPE_P(vq_zv) != IS_OBJECT || !instanceof_function(Z_OBJCE_P(vq_zv), zvec_vector_query_ce)) {
            zvec_throw_exception(0, "All queries must be ZVecVectorQuery instances");
            zval_ptr_dtor(&query_results);
            RETURN_THROWS();
        }

        zval *fn = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(vq_zv), "fieldName", sizeof("fieldName") - 1, 1, nullptr);
        zval *vec = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(vq_zv), "vector", sizeof("vector") - 1, 1, nullptr);
        zval *qpt = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(vq_zv), "queryParamType", sizeof("queryParamType") - 1, 1, nullptr);
        zval *ef = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(vq_zv), "hnswEf", sizeof("hnswEf") - 1, 1, nullptr);
        zval *np = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(vq_zv), "ivfNprobe", sizeof("ivfNprobe") - 1, 1, nullptr);
        zval *rad = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(vq_zv), "radius", sizeof("radius") - 1, 1, nullptr);
        zval *lin = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(vq_zv), "isLinear", sizeof("isLinear") - 1, 1, nullptr);
        zval *ref = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(vq_zv), "isUsingRefiner", sizeof("isUsingRefiner") - 1, 1, nullptr);

        std::string field_name(Z_STRVAL_P(fn), Z_STRLEN_P(fn));
        std::vector<float> vec_data;
        if (Z_TYPE_P(vec) == IS_ARRAY) {
            zval *v;
            ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(vec), v) {
                vec_data.push_back(static_cast<float>(zval_get_double(v)));
            } ZEND_HASH_FOREACH_END();
        }

        zend_long fetch_topk = std::max(topk * 2, (zend_long)100);

        VectorQuery query;
        query.topk_ = static_cast<int>(fetch_topk);
        query.field_name_ = field_name;
        query.include_vector_ = false;
        query.query_vector_.assign(reinterpret_cast<const char *>(vec_data.data()), vec_data.size() * sizeof(float));
        if (filter && filter[0] != '\0') query.filter_ = std::string(filter, filter_len);
        if (output_fields) apply_output_fields(query, output_fields);
        int pt = static_cast<int>(Z_LVAL_P(qpt));
        if (pt != 0) {
            apply_query_params(query, pt, static_cast<int>(Z_LVAL_P(ef)),
                static_cast<int>(Z_LVAL_P(np)), static_cast<float>(Z_DVAL_P(rad)),
                zend_is_true(lin), zend_is_true(ref));
        }

        auto res = intern->collection->Query(query);
        if (!res.has_value()) {
            check_status(res.error());
            zval_ptr_dtor(&query_results);
            RETURN_THROWS();
        }

        zval docs_arr;
        fill_results(&docs_arr, res.value());
        add_assoc_zval(&query_results, field_name.c_str(), &docs_arr);
    } ZEND_HASH_FOREACH_END();

    zval func_name;
    ZVAL_STRING(&func_name, "rerank");
    zval retval;
    zval params[1];
    ZVAL_COPY_VALUE(&params[0], &query_results);
    if (call_user_function(NULL, reranker_zv, &func_name, &retval, 1, params) == SUCCESS) {
        ZVAL_COPY_VALUE(return_value, &retval);
    }
    zval_ptr_dtor(&func_name);
    zval_ptr_dtor(&query_results);
}

// --- queryByFilter ---

PHP_METHOD(ZVec, queryByFilter) {
    char *filter; size_t filter_len;
    zend_long topk = 100;
    zval *output_fields = nullptr;

    ZEND_PARSE_PARAMETERS_START(1, 3)
        Z_PARAM_STRING(filter, filter_len)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(topk)
        Z_PARAM_ARRAY_OR_NULL(output_fields)
    ZEND_PARSE_PARAMETERS_END();

    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();

    VectorQuery query;
    query.topk_ = static_cast<int>(topk);
    query.filter_ = std::string(filter, filter_len);
    if (output_fields) apply_output_fields(query, output_fields);

    auto res = intern->collection->Query(query);
    if (!res.has_value()) { check_status(res.error()); RETURN_THROWS(); }
    fill_results(return_value, res.value());
}

// --- queryById ---

PHP_METHOD(ZVec, queryById) {
    char *field; size_t field_len;
    char *doc_id; size_t doc_id_len;
    zend_long topk = 10;
    zend_bool include_vector = 0;
    char *filter = nullptr; size_t filter_len = 0;
    zval *output_fields = nullptr;
    zend_long query_param_type = 0, hnsw_ef = 200, ivf_nprobe = 10;
    double radius = 0.0;
    zend_bool is_linear = 0, is_using_refiner = 0;

    ZEND_PARSE_PARAMETERS_START(2, 12)
        Z_PARAM_STRING(field, field_len)
        Z_PARAM_STRING(doc_id, doc_id_len)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(topk)
        Z_PARAM_BOOL(include_vector)
        Z_PARAM_STRING_OR_NULL(filter, filter_len)
        Z_PARAM_ARRAY_OR_NULL(output_fields)
        Z_PARAM_LONG(query_param_type)
        Z_PARAM_LONG(hnsw_ef)
        Z_PARAM_LONG(ivf_nprobe)
        Z_PARAM_DOUBLE(radius)
        Z_PARAM_BOOL(is_linear)
        Z_PARAM_BOOL(is_using_refiner)
    ZEND_PARSE_PARAMETERS_END();

    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();

    std::vector<std::string> pks = {std::string(doc_id, doc_id_len)};
    auto fetch_res = intern->collection->Fetch(pks);
    if (!fetch_res.has_value()) { check_status(fetch_res.error()); RETURN_THROWS(); }

    Doc *found_doc = nullptr;
    for (auto &[k, v] : fetch_res.value()) {
        if (v) { found_doc = v.get(); break; }
    }
    if (!found_doc) {
        zvec_throw_exception(0, "Document not found: %s", doc_id);
        RETURN_THROWS();
    }

    std::string fname(field, field_len);
    auto vec_result = found_doc->get_field<std::vector<float>>(fname);
    if (!vec_result.ok()) {
        zvec_throw_exception(0, "Vector field '%s' not found in document: %s", field, doc_id);
        RETURN_THROWS();
    }
    const auto &vec = vec_result.value();

    if (!validate_query_param_type(intern->collection.get(), fname, static_cast<int>(query_param_type))) {
        RETURN_THROWS();
    }

    VectorQuery query;
    query.topk_ = static_cast<int>(topk);
    query.field_name_ = fname;
    query.include_vector_ = (bool)include_vector;
    query.query_vector_.assign(reinterpret_cast<const char *>(vec.data()), vec.size() * sizeof(float));
    if (filter && filter[0] != '\0') query.filter_ = std::string(filter, filter_len);
    if (output_fields) apply_output_fields(query, output_fields);
    if (query_param_type != 0) {
        apply_query_params(query, static_cast<int>(query_param_type),
            static_cast<int>(hnsw_ef), static_cast<int>(ivf_nprobe),
            static_cast<float>(radius), (bool)is_linear, (bool)is_using_refiner);
    }

    auto res = intern->collection->Query(query);
    if (!res.has_value()) { check_status(res.error()); RETURN_THROWS(); }
    fill_results(return_value, res.value());
}

// --- groupByQuery ---

PHP_METHOD(ZVec, groupByQuery) {
    zval *field_name_zv;
    zval *query_vector_zv;
    char *group_by_field; size_t group_by_field_len;
    zend_long group_count = 2, group_topk = 3;
    zend_bool include_vector = 0;
    char *filter = nullptr; size_t filter_len = 0;
    zval *output_fields = nullptr;
    zend_long query_param_type = 0, hnsw_ef = 200, ivf_nprobe = 10;
    double radius = 0.0;
    zend_bool is_linear = 0, is_using_refiner = 0;

    ZEND_PARSE_PARAMETERS_START(3, 14)
        Z_PARAM_ZVAL(field_name_zv)
        Z_PARAM_ARRAY(query_vector_zv)
        Z_PARAM_STRING(group_by_field, group_by_field_len)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(group_count)
        Z_PARAM_LONG(group_topk)
        Z_PARAM_BOOL(include_vector)
        Z_PARAM_STRING_OR_NULL(filter, filter_len)
        Z_PARAM_ARRAY_OR_NULL(output_fields)
        Z_PARAM_LONG(query_param_type)
        Z_PARAM_LONG(hnsw_ef)
        Z_PARAM_LONG(ivf_nprobe)
        Z_PARAM_DOUBLE(radius)
        Z_PARAM_BOOL(is_linear)
        Z_PARAM_BOOL(is_using_refiner)
    ZEND_PARSE_PARAMETERS_END();

    auto *intern = Z_ZVEC_COLLECTION_P(ZEND_THIS);
    check_closed(intern);
    if (EG(exception)) RETURN_THROWS();

    std::string field_name_str;
    std::vector<float> vec_data;
    if (Z_TYPE_P(field_name_zv) == IS_OBJECT && instanceof_function(Z_OBJCE_P(field_name_zv), zvec_vector_query_ce)) {
        zval *fn = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(field_name_zv), "fieldName", sizeof("fieldName") - 1, 1, nullptr);
        field_name_str = std::string(Z_STRVAL_P(fn), Z_STRLEN_P(fn));
        zval *vec = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(field_name_zv), "vector", sizeof("vector") - 1, 1, nullptr);
        if (Z_TYPE_P(vec) == IS_ARRAY) {
            zval *v;
            ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(vec), v) {
                vec_data.push_back(static_cast<float>(zval_get_double(v)));
            } ZEND_HASH_FOREACH_END();
        }
        zval *qpt = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(field_name_zv), "queryParamType", sizeof("queryParamType") - 1, 1, nullptr);
        query_param_type = Z_LVAL_P(qpt);
        zval *ef = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(field_name_zv), "hnswEf", sizeof("hnswEf") - 1, 1, nullptr);
        hnsw_ef = Z_LVAL_P(ef);
        zval *np = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(field_name_zv), "ivfNprobe", sizeof("ivfNprobe") - 1, 1, nullptr);
        ivf_nprobe = Z_LVAL_P(np);
        zval *rad = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(field_name_zv), "radius", sizeof("radius") - 1, 1, nullptr);
        radius = Z_DVAL_P(rad);
        zval *lin = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(field_name_zv), "isLinear", sizeof("isLinear") - 1, 1, nullptr);
        is_linear = zend_is_true(lin);
        zval *ref = zend_read_property(zvec_vector_query_ce, Z_OBJ_P(field_name_zv), "isUsingRefiner", sizeof("isUsingRefiner") - 1, 1, nullptr);
        is_using_refiner = zend_is_true(ref);
    } else {
        field_name_str = std::string(Z_STRVAL_P(field_name_zv), Z_STRLEN_P(field_name_zv));
        zval *v;
        ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(query_vector_zv), v) {
            vec_data.push_back(static_cast<float>(zval_get_double(v)));
        } ZEND_HASH_FOREACH_END();
    }

    GroupByVectorQuery query;
    query.field_name_ = field_name_str;
    query.query_vector_.assign(reinterpret_cast<const char *>(vec_data.data()), vec_data.size() * sizeof(float));
    query.group_by_field_name_ = std::string(group_by_field, group_by_field_len);
    query.group_count_ = static_cast<uint32_t>(group_count);
    query.group_topk_ = static_cast<uint32_t>(group_topk);
    query.include_vector_ = (bool)include_vector;
    if (filter && filter[0] != '\0') query.filter_ = std::string(filter, filter_len);
    if (output_fields) {
        HashTable *ht = Z_ARRVAL_P(output_fields);
        std::vector<std::string> fields;
        zval *val;
        ZEND_HASH_FOREACH_VAL(ht, val) {
            fields.emplace_back(Z_STRVAL_P(val), Z_STRLEN_P(val));
        } ZEND_HASH_FOREACH_END();
        query.output_fields_ = std::move(fields);
    }
    if (query_param_type != 0) {
        if (!validate_query_param_type(intern->collection.get(), field_name_str, static_cast<int>(query_param_type))) {
            RETURN_THROWS();
        }
    }

    if (query_param_type == 1) {
        query.query_params_ = std::make_shared<HnswQueryParams>(
            static_cast<int>(hnsw_ef), static_cast<float>(radius), (bool)is_linear, (bool)is_using_refiner);
    } else if (query_param_type == 2) {
        auto params = std::make_shared<IVFQueryParams>(static_cast<int>(ivf_nprobe), (bool)is_using_refiner);
        params->set_radius(static_cast<float>(radius));
        params->set_is_linear((bool)is_linear);
        query.query_params_ = params;
    } else if (query_param_type == 3) {
        auto params = std::make_shared<FlatQueryParams>((bool)is_using_refiner);
        params->set_radius(static_cast<float>(radius));
        params->set_is_linear((bool)is_linear);
        query.query_params_ = params;
    }

    auto res = intern->collection->GroupByQuery(query);
    if (!res.has_value()) { check_status(res.error()); RETURN_THROWS(); }

    auto &groups = res.value();
    array_init_size(return_value, groups.size());
    for (size_t i = 0; i < groups.size(); i++) {
        zval group_zv;
        array_init(&group_zv);
        add_assoc_string(&group_zv, "group_value", groups[i].group_by_value_.c_str());
        zval docs_arr;
        array_init_size(&docs_arr, groups[i].docs_.size());
        for (size_t j = 0; j < groups[i].docs_.size(); j++) {
            auto *doc = new Doc(groups[i].docs_[j]);
            zend_object *obj = zvec_doc_create_from_native(doc, true);
            zval dz;
            ZVAL_OBJ(&dz, obj);
            add_next_index_zval(&docs_arr, &dz);
        }
        add_assoc_zval(&group_zv, "docs", &docs_arr);
        add_next_index_zval(return_value, &group_zv);
    }
}

// --- Arginfo ---

ZEND_BEGIN_ARG_INFO_EX(arginfo_zvec_init, 0, 0, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, logType, IS_LONG, 0, "0")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, logLevel, IS_LONG, 0, "2")
    ZEND_ARG_TYPE_INFO(0, logDir, IS_STRING, 1)
    ZEND_ARG_TYPE_INFO(0, logBasename, IS_STRING, 1)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, logFileSize, IS_LONG, 0, "0")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, logOverdueDays, IS_LONG, 0, "0")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, queryThreads, IS_LONG, 0, "0")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, optimizeThreads, IS_LONG, 0, "0")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, invertToForwardScanRatio, IS_DOUBLE, 0, "0.0")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, bruteForceByKeysRatio, IS_DOUBLE, 0, "0.0")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, memoryLimitMb, IS_LONG, 0, "0")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_zvec_create, 0, 2, ZVec, 0)
    ZEND_ARG_TYPE_INFO(0, path, IS_STRING, 0)
    ZEND_ARG_OBJ_INFO(0, schema, ZVecSchema, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, readOnly, _IS_BOOL, 0, "false")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, enableMmap, _IS_BOOL, 0, "true")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, maxBufferSize, IS_LONG, 0, "67108864")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_zvec_open, 0, 1, ZVec, 0)
    ZEND_ARG_TYPE_INFO(0, path, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, readOnly, _IS_BOOL, 0, "false")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, enableMmap, _IS_BOOL, 0, "true")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, maxBufferSize, IS_LONG, 0, "67108864")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_zvec_void, 0, 0, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_zvec_optimize, 0, 0, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, concurrency, IS_LONG, 0, "0")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_zvec_schema, 0, 0, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_zvec_options, 0, 0, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_zvec_add_column, 0, 0, 1)
    ZEND_ARG_TYPE_INFO(0, name, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, nullable, _IS_BOOL, 0, "true")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, defaultExpr, IS_STRING, 0, "\"0\"")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, concurrency, IS_LONG, 0, "0")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_zvec_drop_column, 0, 0, 1)
    ZEND_ARG_TYPE_INFO(0, name, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_zvec_rename_column, 0, 0, 2)
    ZEND_ARG_TYPE_INFO(0, oldName, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO(0, newName, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, concurrency, IS_LONG, 0, "0")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_zvec_alter_column, 0, 0, 1)
    ZEND_ARG_TYPE_INFO(0, columnName, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, newName, IS_STRING, 1, "null")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, newDataType, IS_LONG, 1, "null")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, nullable, _IS_BOOL, 1, "null")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, concurrency, IS_LONG, 0, "0")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_zvec_create_invert_index, 0, 0, 1)
    ZEND_ARG_TYPE_INFO(0, fieldName, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, enableRange, _IS_BOOL, 0, "true")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, enableWildcard, _IS_BOOL, 0, "false")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_zvec_create_hnsw_index, 0, 0, 1)
    ZEND_ARG_TYPE_INFO(0, fieldName, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, metricType, IS_LONG, 0, "2")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, m, IS_LONG, 0, "50")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, efConstruction, IS_LONG, 0, "500")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, quantizeType, IS_LONG, 0, "0")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, concurrency, IS_LONG, 0, "0")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_zvec_create_flat_index, 0, 0, 1)
    ZEND_ARG_TYPE_INFO(0, fieldName, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, metricType, IS_LONG, 0, "2")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, quantizeType, IS_LONG, 0, "0")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, concurrency, IS_LONG, 0, "0")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_zvec_create_ivf_index, 0, 0, 1)
    ZEND_ARG_TYPE_INFO(0, fieldName, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, metricType, IS_LONG, 0, "2")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, nList, IS_LONG, 0, "1024")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, nIters, IS_LONG, 0, "10")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, useSoar, _IS_BOOL, 0, "false")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, quantizeType, IS_LONG, 0, "0")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, concurrency, IS_LONG, 0, "0")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_zvec_variadic_docs, 0, 0, 1)
    ZEND_ARG_VARIADIC_OBJ_INFO(0, docs, ZVecDoc, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_zvec_batch, 0, 1, IS_ARRAY, 0)
    ZEND_ARG_VARIADIC_OBJ_INFO(0, docs, ZVecDoc, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_zvec_delete, 0, 0, 1)
    ZEND_ARG_VARIADIC_TYPE_INFO(0, pks, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_zvec_delete_by_filter, 0, 0, 1)
    ZEND_ARG_TYPE_INFO(0, filter, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_zvec_fetch, 0, 1, IS_ARRAY, 0)
    ZEND_ARG_VARIADIC_TYPE_INFO(0, pks, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_zvec_query, 0, 1, IS_ARRAY, 0)
    ZEND_ARG_INFO(0, fieldName)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, queryVector, IS_ARRAY, 0, "[]")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, topk, IS_LONG, 0, "10")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, includeVector, _IS_BOOL, 0, "false")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, filter, IS_STRING, 1, "null")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, outputFields, IS_ARRAY, 1, "null")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, queryParamType, IS_LONG, 0, "0")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, hnswEf, IS_LONG, 0, "200")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, ivfNprobe, IS_LONG, 0, "10")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, radius, IS_DOUBLE, 0, "0.0")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, isLinear, _IS_BOOL, 0, "false")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, isUsingRefiner, _IS_BOOL, 0, "false")
    ZEND_ARG_INFO_WITH_DEFAULT_VALUE(0, reranker, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_zvec_query_fp16, 0, 2, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, fieldName, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO(0, queryVector, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, topk, IS_LONG, 0, "10")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, includeVector, _IS_BOOL, 0, "false")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, filter, IS_STRING, 1, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_zvec_query_multi, 0, 2, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, vectorQueries, IS_ARRAY, 0)
    ZEND_ARG_OBJ_INFO(0, reranker, ZVecReRanker, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, topk, IS_LONG, 0, "10")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, filter, IS_STRING, 1, "null")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, outputFields, IS_ARRAY, 1, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_zvec_query_by_filter, 0, 1, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, filter, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, topk, IS_LONG, 0, "100")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, outputFields, IS_ARRAY, 1, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_zvec_query_by_id, 0, 2, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, fieldName, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO(0, docId, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, topk, IS_LONG, 0, "10")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, includeVector, _IS_BOOL, 0, "false")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, filter, IS_STRING, 1, "null")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, outputFields, IS_ARRAY, 1, "null")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, queryParamType, IS_LONG, 0, "0")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, hnswEf, IS_LONG, 0, "200")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, ivfNprobe, IS_LONG, 0, "10")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, radius, IS_DOUBLE, 0, "0.0")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, isLinear, _IS_BOOL, 0, "false")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, isUsingRefiner, _IS_BOOL, 0, "false")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_zvec_group_by_query, 0, 3, IS_ARRAY, 0)
    ZEND_ARG_INFO(0, fieldName)
    ZEND_ARG_TYPE_INFO(0, queryVector, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, groupByField, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, groupCount, IS_LONG, 0, "10")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, groupTopk, IS_LONG, 0, "10")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, includeVector, _IS_BOOL, 0, "false")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, filter, IS_STRING, 1, "null")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, outputFields, IS_ARRAY, 1, "null")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, queryParamType, IS_LONG, 0, "0")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, hnswEf, IS_LONG, 0, "200")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, ivfNprobe, IS_LONG, 0, "10")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, radius, IS_DOUBLE, 0, "0.0")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, isLinear, _IS_BOOL, 0, "false")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, isUsingRefiner, _IS_BOOL, 0, "false")
ZEND_END_ARG_INFO()

static const zend_function_entry zvec_collection_methods[] = {
    PHP_ME(ZVec, init, arginfo_zvec_init, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
    PHP_ME(ZVec, create, arginfo_zvec_create, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
    PHP_ME(ZVec, open, arginfo_zvec_open, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
    PHP_ME(ZVec, close, arginfo_zvec_void, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, __destruct, arginfo_zvec_void, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, flush, arginfo_zvec_void, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, optimize, arginfo_zvec_optimize, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, destroy, arginfo_zvec_void, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, schema, arginfo_zvec_schema, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, path, arginfo_zvec_schema, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, options, arginfo_zvec_options, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, stats, arginfo_zvec_schema, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, addColumnInt64, arginfo_zvec_add_column, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, addColumnFloat, arginfo_zvec_add_column, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, addColumnDouble, arginfo_zvec_add_column, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, addColumnString, arginfo_zvec_add_column, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, addColumnBool, arginfo_zvec_add_column, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, addColumnInt32, arginfo_zvec_add_column, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, addColumnUint32, arginfo_zvec_add_column, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, addColumnUint64, arginfo_zvec_add_column, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, dropColumn, arginfo_zvec_drop_column, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, renameColumn, arginfo_zvec_rename_column, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, alterColumn, arginfo_zvec_alter_column, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, createInvertIndex, arginfo_zvec_create_invert_index, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, createHnswIndex, arginfo_zvec_create_hnsw_index, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, createFlatIndex, arginfo_zvec_create_flat_index, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, createIvfIndex, arginfo_zvec_create_ivf_index, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, dropIndex, arginfo_zvec_drop_column, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, insert, arginfo_zvec_variadic_docs, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, upsert, arginfo_zvec_variadic_docs, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, update, arginfo_zvec_variadic_docs, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, insertBatch, arginfo_zvec_batch, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, upsertBatch, arginfo_zvec_batch, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, updateBatch, arginfo_zvec_batch, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, delete, arginfo_zvec_delete, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, deleteByFilter, arginfo_zvec_delete_by_filter, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, fetch, arginfo_zvec_fetch, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, query, arginfo_zvec_query, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, queryFp16, arginfo_zvec_query_fp16, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, queryMulti, arginfo_zvec_query_multi, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, queryByFilter, arginfo_zvec_query_by_filter, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, queryById, arginfo_zvec_query_by_id, ZEND_ACC_PUBLIC)
    PHP_ME(ZVec, groupByQuery, arginfo_zvec_group_by_query, ZEND_ACC_PUBLIC)
    PHP_FE_END
};

void zvec_register_collection(INIT_FUNC_ARGS) {
    zend_class_entry ce;
    INIT_CLASS_ENTRY(ce, "ZVec", zvec_collection_methods);
    zvec_collection_ce = zend_register_internal_class(&ce);
    zvec_collection_ce->create_object = zvec_collection_create_object_handler;

    memcpy(&zvec_collection_handlers, &std_object_handlers, sizeof(zend_object_handlers));
    zvec_collection_handlers.offset = XtOffsetOf(zvec_collection_object, std);
    zvec_collection_handlers.free_obj = zvec_collection_free_object;

    zend_declare_class_constant_long(zvec_collection_ce, "QUERY_PARAM_NONE", sizeof("QUERY_PARAM_NONE") - 1, 0);
    zend_declare_class_constant_long(zvec_collection_ce, "QUERY_PARAM_HNSW", sizeof("QUERY_PARAM_HNSW") - 1, 1);
    zend_declare_class_constant_long(zvec_collection_ce, "QUERY_PARAM_IVF", sizeof("QUERY_PARAM_IVF") - 1, 2);
    zend_declare_class_constant_long(zvec_collection_ce, "QUERY_PARAM_FLAT", sizeof("QUERY_PARAM_FLAT") - 1, 3);

    zend_declare_class_constant_long(zvec_collection_ce, "LOG_CONSOLE", sizeof("LOG_CONSOLE") - 1, 0);
    zend_declare_class_constant_long(zvec_collection_ce, "LOG_FILE", sizeof("LOG_FILE") - 1, 1);
    zend_declare_class_constant_long(zvec_collection_ce, "LOG_DEBUG", sizeof("LOG_DEBUG") - 1, 0);
    zend_declare_class_constant_long(zvec_collection_ce, "LOG_INFO", sizeof("LOG_INFO") - 1, 1);
    zend_declare_class_constant_long(zvec_collection_ce, "LOG_WARN", sizeof("LOG_WARN") - 1, 2);
    zend_declare_class_constant_long(zvec_collection_ce, "LOG_ERROR", sizeof("LOG_ERROR") - 1, 3);
    zend_declare_class_constant_long(zvec_collection_ce, "LOG_FATAL", sizeof("LOG_FATAL") - 1, 4);

    zend_declare_class_constant_long(zvec_collection_ce, "TYPE_BOOL", sizeof("TYPE_BOOL") - 1, 3);
    zend_declare_class_constant_long(zvec_collection_ce, "TYPE_INT32", sizeof("TYPE_INT32") - 1, 4);
    zend_declare_class_constant_long(zvec_collection_ce, "TYPE_INT64", sizeof("TYPE_INT64") - 1, 5);
    zend_declare_class_constant_long(zvec_collection_ce, "TYPE_UINT32", sizeof("TYPE_UINT32") - 1, 6);
    zend_declare_class_constant_long(zvec_collection_ce, "TYPE_UINT64", sizeof("TYPE_UINT64") - 1, 7);
    zend_declare_class_constant_long(zvec_collection_ce, "TYPE_FLOAT", sizeof("TYPE_FLOAT") - 1, 8);
    zend_declare_class_constant_long(zvec_collection_ce, "TYPE_DOUBLE", sizeof("TYPE_DOUBLE") - 1, 9);

    zend_declare_class_constant_long(zvec_collection_ce, "QUANTIZE_UNDEFINED", sizeof("QUANTIZE_UNDEFINED") - 1, 0);
    zend_declare_class_constant_long(zvec_collection_ce, "QUANTIZE_FP16", sizeof("QUANTIZE_FP16") - 1, 1);
    zend_declare_class_constant_long(zvec_collection_ce, "QUANTIZE_INT8", sizeof("QUANTIZE_INT8") - 1, 2);
    zend_declare_class_constant_long(zvec_collection_ce, "QUANTIZE_INT4", sizeof("QUANTIZE_INT4") - 1, 3);
}
