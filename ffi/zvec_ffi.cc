#include "zvec_ffi.h"

#include <array>
#include <algorithm>
#include <cstring>
#include <optional>
#include <string>
#include <unordered_map>
#include <vector>
#include <shared_mutex>
#include <zvec/db/collection.h>
#include <zvec/db/doc.h>
#include <zvec/db/index_params.h>
#include <zvec/db/options.h>
#include <zvec/db/schema.h>
#include <zvec/db/config.h>
#include <zvec/db/status.h>
#include <zvec/ailego/utility/float_helper.h>
#include <zvec/ailego/io/io_backend.h>

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

// Helper to strip __FILE__ to basename only (reduces information disclosure)
static const char* strip_path(const char* path) {
    const char* slash = strrchr(path, '/');
    return slash ? slash + 1 : path;
}

// MAKE_STATUS wraps make_status and captures source location on error
#define MAKE_STATUS(s) \
    ([&]() -> zvec_status_t { \
        auto _st = make_status(s); \
        if (_st.code != 0) { \
            set_last_error(_st.code, _st.message, strip_path(__FILE__), __LINE__, __func__); \
        } \
        return _st; \
    })()

// Set error details directly for manually constructed error statuses
#define SET_FFI_ERROR(st) \
    do { \
        if ((st).code != 0) { \
            set_last_error((st).code, (st).message, strip_path(__FILE__), __LINE__, __func__); \
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

// Version information — sourced from zvec zvec_version.h (build-time generated)
// When updating the zvec version, update these constants to match.
static constexpr int kVersionMajor = 0;
static constexpr int kVersionMinor = 7;
static constexpr int kVersionPatch = 0;
static constexpr const char* kVersionString = "v0.7.0";

const char* zvec_get_version(void) {
    return kVersionString;
}

int zvec_check_version(int major, int minor, int patch) {
    if (major < 0 || minor < 0 || patch < 0) return 0;
    if (kVersionMajor > major) return 1;
    if (kVersionMajor < major) return 0;
    if (kVersionMinor > minor) return 1;
    if (kVersionMinor < minor) return 0;
    return kVersionPatch >= patch ? 1 : 0;
}

int zvec_get_version_major(void) {
    return kVersionMajor;
}

int zvec_get_version_minor(void) {
    return kVersionMinor;
}

int zvec_get_version_patch(void) {
    return kVersionPatch;
}

// DiskANN I/O backend. current_io_backend_type() and
// current_io_backend_description() are ordinary functions compiled inside
// libzvec; the IOBackend singleton behind them is created only there, so this
// keeps the #215 singleton rule. Do NOT include the internal
// zvec/ailego/io/io_backend_def.h and do not reference IOBackend::Instance()
// from this module: that header is not part of the SDK, and instantiating the
// singleton here would build a second copy destroyed twice at exit.
int zvec_get_io_backend_type(void) {
    return static_cast<int>(zvec::ailego::current_io_backend_type());
}

const char* zvec_get_io_backend_type_name(int type) {
    switch (type) {
        case ZVEC_IO_BACKEND_PREAD: return "pread";
        case ZVEC_IO_BACKEND_LIBAIO: return "libaio";
        case ZVEC_IO_BACKEND_IO_URING: return "io_uring";
        default: return "unknown";
    }
}

const char* zvec_get_io_backend_description(void) {
    static thread_local std::string buf;
    buf = zvec::ailego::current_io_backend_description();
    return buf.c_str();
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

// Track initialization state for isInitialized/shutdown
#include <atomic>
#include <dlfcn.h>
#include <sys/stat.h>
static std::atomic<int> g_ffi_initialized{0};

// GlobalConfig::Instance() (from zvec/db/config.h) is an inline function-local
// static (Meyers singleton) defined via ailego::Singleton<GlobalConfig> and
// already instantiated once inside libzvec itself. If we let the compiler
// instantiate that same inline template here, this shared library ends up
// with its OWN copy of the function, its OWN local initialization guard
// variable, but shares the GNU_UNIQUE-deduplicated static object with
// libzvec's copy (GNU_UNIQUE symbols are merged process-wide by the
// dynamic linker, but the per-TU guard variable that decides whether to
// *construct* it is not). Result: the object's constructor runs twice (once
// per guard) -- so a config value set via our copy (e.g. query_thread_count)
// gets silently reset to the default by libzvec's copy running its own
// first-time construction after ours -- and its destructor also runs twice
// at process exit, corrupting the heap ("free(): chunks in smallbin
// corrupted", SIGABRT). Verified with valgrind. To guarantee there is
// exactly one instantiation process-wide, we never write
// `GlobalConfig::Instance()` anywhere in this file; instead we resolve the
// one instantiation that already lives inside libzvec via dlsym and call
// through that function pointer.
static GlobalConfig* global_config_ptr() {
    using Fn = GlobalConfig& (*)();
    static Fn fn = reinterpret_cast<Fn>(
        dlsym(RTLD_DEFAULT,
              "_ZN4zvec6ailego9SingletonINS_12GlobalConfigEE8InstanceEv"));
    return fn ? &fn() : nullptr;
}

// The SDK ships an FTS jieba dictionary at sdk/data/jieba_dict, which
// build_ffi.sh copies next to libzvec_ffi as zvec_data/jieba_dict. Wire it up
// as the process-wide lowest-priority fallback (see GlobalConfig::ConfigData
// ::jieba_dict_dir doc in config.h: per-field config > ZVEC_JIEBA_DICT_DIR
// env var > this) so FTS jieba tokenization works out-of-the-box without the
// caller having to know where the adapter's shared library lives. Locate our
// own .so via dladdr instead of a relative/CWD-based path since PHP's FFI
// loader may resolve libzvec_ffi from an arbitrary working directory.
static void set_default_jieba_dict_dir_if_available(GlobalConfig* gc) {
    if (!gc) return;
    Dl_info info;
    if (!dladdr(reinterpret_cast<void*>(&set_default_jieba_dict_dir_if_available), &info) ||
        !info.dli_fname) {
        return;
    }
    std::string self_path = info.dli_fname;
    auto pos = self_path.find_last_of('/');
    std::string dir = (pos != std::string::npos) ? self_path.substr(0, pos) : ".";
    std::string jieba_dir = dir + "/zvec_data/jieba_dict";
    struct stat st;
    if (stat(jieba_dir.c_str(), &st) == 0 && S_ISDIR(st.st_mode)) {
        gc->set_default_jieba_dict_dir(jieba_dir);
    }
}

struct LogConfigHolder {
    std::shared_ptr<GlobalConfig::LogConfig> config;
};

struct ConfigDataHolder {
    GlobalConfig::ConfigData config;
};

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

    auto* gc = global_config_ptr();
    if (!gc) {
        zvec_status_t st = {8, "internal: libzvec GlobalConfig::Instance symbol not found"};
        SET_FFI_ERROR(st);
        return st;
    }
    if (config.jieba_dict_dir.empty()) {
        set_default_jieba_dict_dir_if_available(gc);
    }
    auto st = MAKE_STATUS(gc->initialize(config));
    if (st.code == 0) {
        g_ffi_initialized.store(1, std::memory_order_release);
    }
    return st;
}

// --- Opaque Config Init API ---

zvec_log_config_t zvec_log_config_create_console(int level) {
    auto* holder = new LogConfigHolder();
    holder->config = std::make_shared<GlobalConfig::ConsoleLogConfig>(
        static_cast<GlobalConfig::LogLevel>(level)
    );
    return static_cast<zvec_log_config_t>(holder);
}

zvec_log_config_t zvec_log_config_create_file(int level, const char* dir, const char* basename, uint32_t file_size, uint32_t overdue_days) {
    auto* holder = new LogConfigHolder();
    holder->config = std::make_shared<GlobalConfig::FileLogConfig>(
        static_cast<GlobalConfig::LogLevel>(level),
        dir ? dir : DEFAULT_LOG_DIR,
        basename ? basename : DEFAULT_LOG_BASENAME,
        file_size > 0 ? file_size : DEFAULT_LOG_FILE_SIZE,
        overdue_days > 0 ? overdue_days : DEFAULT_LOG_OVERDUE_DAYS
    );
    return static_cast<zvec_log_config_t>(holder);
}

void zvec_log_config_free(zvec_log_config_t config) {
    if (config) {
        delete static_cast<LogConfigHolder*>(config);
    }
}

zvec_config_data_t zvec_config_data_create(void) {
    auto* holder = new ConfigDataHolder();
    return static_cast<zvec_config_data_t>(holder);
}

void zvec_config_data_free(zvec_config_data_t config) {
    if (config) {
        delete static_cast<ConfigDataHolder*>(config);
    }
}

void zvec_config_data_set_memory_limit(zvec_config_data_t config, uint64_t bytes) {
    if (config) {
        static_cast<ConfigDataHolder*>(config)->config.memory_limit_bytes = bytes;
    }
}

void zvec_config_data_set_log_config(zvec_config_data_t config, zvec_log_config_t log_config) {
    if (config && log_config) {
        static_cast<ConfigDataHolder*>(config)->config.log_config =
            static_cast<LogConfigHolder*>(log_config)->config;
    }
}

void zvec_config_data_set_query_thread_count(zvec_config_data_t config, uint32_t count) {
    if (config) {
        static_cast<ConfigDataHolder*>(config)->config.query_thread_count = count;
    }
}

void zvec_config_data_set_optimize_thread_count(zvec_config_data_t config, uint32_t count) {
    if (config) {
        static_cast<ConfigDataHolder*>(config)->config.optimize_thread_count = count;
    }
}

void zvec_config_data_set_invert_to_forward_scan_ratio(zvec_config_data_t config, float ratio) {
    if (config) {
        static_cast<ConfigDataHolder*>(config)->config.invert_to_forward_scan_ratio = ratio;
    }
}

void zvec_config_data_set_brute_force_by_keys_ratio(zvec_config_data_t config, float ratio) {
    if (config) {
        static_cast<ConfigDataHolder*>(config)->config.brute_force_by_keys_ratio = ratio;
    }
}

void zvec_config_data_set_fts_brute_force_by_keys_ratio(zvec_config_data_t config, float ratio) {
    if (config) {
        static_cast<ConfigDataHolder*>(config)->config.fts_brute_force_by_keys_ratio = ratio;
    }
}

void zvec_config_data_set_jieba_dict_dir(zvec_config_data_t config, const char* dir) {
    if (config) {
        static_cast<ConfigDataHolder*>(config)->config.jieba_dict_dir = dir ? dir : "";
    }
}

float zvec_global_config_get_fts_brute_force_by_keys_ratio(void) {
    auto* gc = global_config_ptr();
    return gc ? gc->fts_brute_force_by_keys_ratio() : 0.0f;
}

zvec_status_t zvec_global_config_get_jieba_dict_dir(char* buf, size_t buf_size) {
    auto* gc = global_config_ptr();
    if (!gc) {
        zvec_status_t st = {8, "internal: libzvec GlobalConfig::Instance symbol not found"};
        SET_FFI_ERROR(st);
        return st;
    }
    if (!buf || buf_size == 0) {
        zvec_status_t st = {1, "null buffer"};
        SET_FFI_ERROR(st);
        return st;
    }
    std::string dir = gc->jieba_dict_dir();
    strncpy(buf, dir.c_str(), buf_size - 1);
    buf[buf_size - 1] = '\0';
    return ok_status();
}

zvec_status_t zvec_ffi_initialize(zvec_config_data_t config) {
    auto* gc = global_config_ptr();
    if (!gc) {
        zvec_status_t st = {8, "internal: libzvec GlobalConfig::Instance symbol not found"};
        SET_FFI_ERROR(st);
        return st;
    }
    if (!config) {
        auto* holder = new ConfigDataHolder();
        if (holder->config.jieba_dict_dir.empty()) {
            set_default_jieba_dict_dir_if_available(gc);
        }
        auto st = MAKE_STATUS(gc->initialize(holder->config));
        delete holder;
        if (st.code == 0) {
            g_ffi_initialized.store(1, std::memory_order_release);
        }
        return st;
    }
    auto* holder = static_cast<ConfigDataHolder*>(config);
    if (holder->config.jieba_dict_dir.empty()) {
        set_default_jieba_dict_dir_if_available(gc);
    }
    auto st = MAKE_STATUS(gc->initialize(holder->config));
    if (st.code == 0) {
        g_ffi_initialized.store(1, std::memory_order_release);
    }
    return st;
}

zvec_status_t zvec_ffi_shutdown(void) {
    g_ffi_initialized.store(0, std::memory_order_release);
    return ok_status();
}

int zvec_ffi_is_initialized(void) {
    return g_ffi_initialized.load(std::memory_order_acquire);
}

// --- Schema ---

zvec_schema_t zvec_schema_create(const char* name) {
    auto* schema = new CollectionSchema(name);
    return static_cast<zvec_schema_t>(schema);
}

void zvec_schema_free(zvec_schema_t schema) {
    if (!schema) return;
    delete static_cast<CollectionSchema*>(schema);
}

void zvec_schema_set_max_doc_count_per_segment(zvec_schema_t schema, uint64_t count) {
    if (!schema) return;
    static_cast<CollectionSchema*>(schema)->set_max_doc_count_per_segment(count);
}

void zvec_schema_add_field_int64(zvec_schema_t schema, const char* name, int nullable, int with_invert_index) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    if (with_invert_index) {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::INT64, (bool)nullable, std::make_shared<InvertIndexParams>(true)));
    } else {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::INT64, (bool)nullable));
    }
}

void zvec_schema_add_field_string(zvec_schema_t schema, const char* name, int nullable, int with_invert_index) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    if (with_invert_index) {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::STRING, (bool)nullable, std::make_shared<InvertIndexParams>(false)));
    } else {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::STRING, (bool)nullable));
    }
}

void zvec_schema_add_field_float(zvec_schema_t schema, const char* name, int nullable) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::FLOAT, (bool)nullable));
}

void zvec_schema_add_field_double(zvec_schema_t schema, const char* name, int nullable) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::DOUBLE, (bool)nullable));
}

void zvec_schema_add_field_bool(zvec_schema_t schema, const char* name, int nullable, int with_invert_index) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    if (with_invert_index) {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::BOOL, (bool)nullable, std::make_shared<InvertIndexParams>(true)));
    } else {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::BOOL, (bool)nullable));
    }
}

void zvec_schema_add_field_int32(zvec_schema_t schema, const char* name, int nullable, int with_invert_index) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    if (with_invert_index) {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::INT32, (bool)nullable, std::make_shared<InvertIndexParams>(true)));
    } else {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::INT32, (bool)nullable));
    }
}

void zvec_schema_add_field_uint32(zvec_schema_t schema, const char* name, int nullable, int with_invert_index) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    if (with_invert_index) {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::UINT32, (bool)nullable, std::make_shared<InvertIndexParams>(true)));
    } else {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::UINT32, (bool)nullable));
    }
}

void zvec_schema_add_field_uint64(zvec_schema_t schema, const char* name, int nullable, int with_invert_index) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    if (with_invert_index) {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::UINT64, (bool)nullable, std::make_shared<InvertIndexParams>(true)));
    } else {
        s->add_field(std::make_shared<FieldSchema>(name, DataType::UINT64, (bool)nullable));
    }
}

void zvec_schema_add_field_vector_fp32(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::VECTOR_FP32, dimension, false,
        std::make_shared<HnswIndexParams>(to_metric_type(metric_type))));
}

void zvec_schema_add_field_vector_fp64(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::VECTOR_FP64, dimension, false,
        std::make_shared<HnswIndexParams>(to_metric_type(metric_type))));
}

void zvec_schema_add_field_sparse_vector_fp32(zvec_schema_t schema, const char* name, uint32_t metric_type) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::SPARSE_VECTOR_FP32, 0, false,
        std::make_shared<HnswIndexParams>(to_metric_type(metric_type))));
}

void zvec_schema_add_field_vector_int8(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::VECTOR_INT8, dimension, false,
        std::make_shared<HnswIndexParams>(to_metric_type(metric_type))));
}

void zvec_schema_add_field_vector_fp16(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::VECTOR_FP16, dimension, false,
        std::make_shared<HnswIndexParams>(to_metric_type(metric_type))));
}

void zvec_schema_add_field_vector_int4(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::VECTOR_INT4, dimension, false,
        std::make_shared<HnswIndexParams>(to_metric_type(metric_type))));
}

void zvec_schema_add_field_vector_int16(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::VECTOR_INT16, dimension, false,
        std::make_shared<HnswIndexParams>(to_metric_type(metric_type))));
}

void zvec_schema_add_field_vector_binary32(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::VECTOR_BINARY32, dimension, false,
        std::make_shared<HnswIndexParams>(to_metric_type(metric_type))));
}

void zvec_schema_add_field_vector_binary64(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::VECTOR_BINARY64, dimension, false,
        std::make_shared<HnswIndexParams>(to_metric_type(metric_type))));
}

void zvec_schema_add_field_sparse_vector_fp16(zvec_schema_t schema, const char* name, uint32_t metric_type) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::SPARSE_VECTOR_FP16, 0, false,
        std::make_shared<HnswIndexParams>(to_metric_type(metric_type))));
}

void zvec_schema_add_field_binary(zvec_schema_t schema, const char* name, int nullable) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::BINARY, (bool)nullable));
}

void zvec_schema_add_field_array_string(zvec_schema_t schema, const char* name, int nullable) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::ARRAY_STRING, (bool)nullable));
}

void zvec_schema_add_field_array_bool(zvec_schema_t schema, const char* name, int nullable) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::ARRAY_BOOL, (bool)nullable));
}

void zvec_schema_add_field_array_int32(zvec_schema_t schema, const char* name, int nullable) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::ARRAY_INT32, (bool)nullable));
}

void zvec_schema_add_field_array_int64(zvec_schema_t schema, const char* name, int nullable) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::ARRAY_INT64, (bool)nullable));
}

void zvec_schema_add_field_array_uint32(zvec_schema_t schema, const char* name, int nullable) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::ARRAY_UINT32, (bool)nullable));
}

void zvec_schema_add_field_array_uint64(zvec_schema_t schema, const char* name, int nullable) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::ARRAY_UINT64, (bool)nullable));
}

void zvec_schema_add_field_array_float(zvec_schema_t schema, const char* name, int nullable) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::ARRAY_FLOAT, (bool)nullable));
}

void zvec_schema_add_field_array_double(zvec_schema_t schema, const char* name, int nullable) {
    if (!schema) return;
    auto* s = static_cast<CollectionSchema*>(schema);
    s->add_field(std::make_shared<FieldSchema>(name, DataType::ARRAY_DOUBLE, (bool)nullable));
}

// --- Collection ---

// Thread-safe collection registry: a pointer-keyed map so lookups are O(1).
//
// The registry is a function-local static (Meyers singleton), so it is created
// on first use and never destroyed at process exit. A namespace-scope
// std::shared_mutex cannot be used here: its destructor runs from .fini_array,
// which glibc executes *after* it has already torn down thread state, so
// pthread_mutex_destroy fails with "pthread lock: Invalid argument" and the
// process segfaults. That only surfaces in a shared-library build, where this
// file is its own module -- in the static build everything lives in one .so and
// the ordering happens to work. A std::mutex has no destructor, so it is safe
// as a static, and the registry has no concurrent readers, so exclusivity is
// all that is needed anyway.
static std::mutex g_collections_mutex;

static std::unordered_map<Collection*, std::shared_ptr<Collection>>&
collections_registry()
{
    static std::unordered_map<Collection*, std::shared_ptr<Collection>> registry;
    return registry;
}

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
    {
        std::unique_lock lock(g_collections_mutex);
        collections_registry()[raw] = std::move(ptr);
    }
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
    {
        std::unique_lock lock(g_collections_mutex);
        collections_registry()[raw] = std::move(ptr);
    }
    *out = static_cast<zvec_collection_t>(raw);
    return ok_status();
}

zvec_status_t zvec_collection_close(zvec_collection_t coll) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    // Deliberately does not touch the handle registry: the caller still has to
    // call zvec_collection_free(). Upstream close() returns FAILED_PRECONDITION
    // while iterators are open, in which case nothing changed and the
    // collection stays usable; any other result means it is closed.
    return MAKE_STATUS(c->close());
}

void zvec_collection_free(zvec_collection_t coll) {
    if (!coll) return;
    auto* raw = static_cast<Collection*>(coll);
    std::unique_lock lock(g_collections_mutex);
    collections_registry().erase(raw);
}

zvec_status_t zvec_collection_flush(zvec_collection_t coll) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    return MAKE_STATUS(c->flush());
}

zvec_status_t zvec_collection_optimize(zvec_collection_t coll, uint32_t concurrency) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    OptimizeOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->optimize(opts));
}

zvec_status_t zvec_collection_destroy(zvec_collection_t coll) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* raw = static_cast<Collection*>(coll);
    auto status = raw->destroy();
    if (status.ok()) {
        // On failure upstream leaves the collection untouched (e.g. read-only
        // mode, or iterators still open), so the handle must stay valid for the
        // caller -- erasing it here would leave the PHP object with a dangling
        // pointer and turn the next method call into a use-after-free.
        std::unique_lock lock(g_collections_mutex);
        collections_registry().erase(raw);
    }
    return MAKE_STATUS(status);
}

// --- Inspect ---

zvec_status_t zvec_collection_schema(zvec_collection_t coll, char* buf, size_t buf_size) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    auto res = c->schema();
    if (!res.has_value()) {
        return MAKE_STATUS(res.error());
    }
    auto str = res.value().to_string();
    strncpy(buf, str.c_str(), buf_size - 1);
    buf[buf_size - 1] = '\0';
    return ok_status();
}

zvec_status_t zvec_collection_path(zvec_collection_t coll, char* buf, size_t buf_size) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    auto res = c->path();
    if (!res.has_value()) {
        return MAKE_STATUS(res.error());
    }
    strncpy(buf, res.value().c_str(), buf_size - 1);
    buf[buf_size - 1] = '\0';
    return ok_status();
}

zvec_status_t zvec_collection_options(zvec_collection_t coll, int* read_only, int* enable_mmap, uint32_t* max_buffer_size) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    auto res = c->options();
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
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    c->flush();
    auto field = std::make_shared<FieldSchema>(name, DataType::INT64, (bool)nullable);
    AddColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->add_column(field, default_expr ? default_expr : "0", opts));
}

zvec_status_t zvec_collection_add_column_float(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    c->flush();
    auto field = std::make_shared<FieldSchema>(name, DataType::FLOAT, (bool)nullable);
    AddColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->add_column(field, default_expr ? default_expr : "0", opts));
}

zvec_status_t zvec_collection_add_column_double(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    c->flush();
    auto field = std::make_shared<FieldSchema>(name, DataType::DOUBLE, (bool)nullable);
    AddColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->add_column(field, default_expr ? default_expr : "0", opts));
}

zvec_status_t zvec_collection_add_column_string(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    c->flush();
    auto field = std::make_shared<FieldSchema>(name, DataType::STRING, (bool)nullable);
    AddColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->add_column(field, default_expr ? default_expr : "", opts));
}

zvec_status_t zvec_collection_add_column_bool(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    c->flush();
    auto field = std::make_shared<FieldSchema>(name, DataType::BOOL, (bool)nullable);
    AddColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->add_column(field, default_expr ? default_expr : "false", opts));
}

zvec_status_t zvec_collection_add_column_int32(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    c->flush();
    auto field = std::make_shared<FieldSchema>(name, DataType::INT32, (bool)nullable);
    AddColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->add_column(field, default_expr ? default_expr : "0", opts));
}

zvec_status_t zvec_collection_add_column_uint32(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    c->flush();
    auto field = std::make_shared<FieldSchema>(name, DataType::UINT32, (bool)nullable);
    AddColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->add_column(field, default_expr ? default_expr : "0", opts));
}

zvec_status_t zvec_collection_add_column_uint64(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    c->flush();
    auto field = std::make_shared<FieldSchema>(name, DataType::UINT64, (bool)nullable);
    AddColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->add_column(field, default_expr ? default_expr : "0", opts));
}

zvec_status_t zvec_collection_drop_column(zvec_collection_t coll, const char* name) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    c->flush();
    return MAKE_STATUS(c->drop_column(name));
}

zvec_status_t zvec_collection_rename_column(zvec_collection_t coll, const char* old_name, const char* new_name, uint32_t concurrency) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    c->flush();
    AlterColumnOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->alter_column(old_name, new_name, nullptr, opts));
}

zvec_status_t zvec_collection_alter_column(zvec_collection_t coll, const char* column_name, const char* new_name, uint32_t data_type, int nullable, uint32_t concurrency) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    c->flush();
    
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
    return MAKE_STATUS(c->alter_column(column_name, rename_str, new_schema, opts));
}

zvec_status_t zvec_collection_create_invert_index(zvec_collection_t coll, const char* field_name, int enable_range, int enable_wildcard) {
    auto* c = static_cast<Collection*>(coll);
    auto params = std::make_shared<InvertIndexParams>((bool)enable_range, (bool)enable_wildcard);
    return MAKE_STATUS(c->create_index(field_name, params));
}

zvec_status_t zvec_collection_create_hnsw_index(zvec_collection_t coll, const char* field_name, uint32_t metric_type, int m, int ef_construction, uint32_t quantize_type, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    auto params = std::make_shared<HnswIndexParams>(to_metric_type(metric_type), m, ef_construction, to_quantize_type(quantize_type));
    CreateIndexOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->create_index(field_name, params, opts));
}

zvec_status_t zvec_collection_create_hnsw_rabitq_index(zvec_collection_t coll, const char* field_name, uint32_t metric_type, int total_bits, int num_clusters, int m, int ef_construction, int sample_count, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    auto params = std::make_shared<HnswRabitqIndexParams>(to_metric_type(metric_type), total_bits, num_clusters, m, ef_construction, sample_count);
    CreateIndexOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->create_index(field_name, params, opts));
}

zvec_status_t zvec_collection_create_flat_index(zvec_collection_t coll, const char* field_name, uint32_t metric_type, uint32_t quantize_type, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    auto params = std::make_shared<FlatIndexParams>(to_metric_type(metric_type), to_quantize_type(quantize_type));
    CreateIndexOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->create_index(field_name, params, opts));
}

zvec_status_t zvec_collection_create_ivf_index(zvec_collection_t coll, const char* field_name, uint32_t metric_type, int n_list, int n_iters, int use_soar, uint32_t quantize_type, uint32_t concurrency) {
    auto* c = static_cast<Collection*>(coll);
    auto params = std::make_shared<IVFIndexParams>(to_metric_type(metric_type), n_list, n_iters, (bool)use_soar, to_quantize_type(quantize_type));
    CreateIndexOptions opts;
    if (concurrency > 0) opts.concurrency_ = static_cast<int>(concurrency);
    return MAKE_STATUS(c->create_index(field_name, params, opts));
}

zvec_status_t zvec_collection_drop_index(zvec_collection_t coll, const char* field_name) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    return MAKE_STATUS(c->drop_index(field_name));
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
    int ivf_rabitq_nlist_;
    bool hnsw_use_contiguous_memory_;
    bool quantizer_enable_rotate_;
    int vamana_max_degree_;
    int vamana_search_list_size_;
    float vamana_alpha_;
    bool vamana_saturate_graph_;
    bool vamana_use_contiguous_memory_;
    bool vamana_use_id_map_;
    bool vamana_two_pass_build_;
    std::string fts_tokenizer_name_;
    std::vector<std::string> fts_filters_;
    std::string fts_extra_params_;
    int diskann_max_degree_;
    int diskann_list_size_;
    int diskann_pq_chunk_num_;

    IndexParamsHolder(IndexType type, MetricType metric_type)
        : type_(type), metric_type_(metric_type), quantize_type_(QuantizeType::UNDEFINED),
          hnsw_m_(50), hnsw_ef_construction_(500),
          ivf_n_list_(1024), ivf_n_iters_(10), ivf_use_soar_(false),
          invert_enable_range_(true), invert_enable_wildcard_(false),
          rabitq_total_bits_(7), rabitq_num_clusters_(16), rabitq_sample_count_(0),
          ivf_rabitq_nlist_(1024),
          hnsw_use_contiguous_memory_(false),
          quantizer_enable_rotate_(false),
          vamana_max_degree_(64), vamana_search_list_size_(100), vamana_alpha_(1.2f),
          vamana_saturate_graph_(false), vamana_use_contiguous_memory_(false), vamana_use_id_map_(false),
          vamana_two_pass_build_(false),
          // Upstream DiskAnnIndexParams defaults: max_degree 100, list_size 50, pq_chunk_num 0.
          diskann_max_degree_(100), diskann_list_size_(50), diskann_pq_chunk_num_(0),
          fts_tokenizer_name_("standard"), fts_filters_({"lowercase"}), fts_extra_params_() {}

    IndexParams::Ptr build() const {
        IndexParams::Ptr params;
        switch (type_) {
            case IndexType::HNSW:
                params = std::make_shared<HnswIndexParams>(metric_type_, hnsw_m_, hnsw_ef_construction_, quantize_type_, hnsw_use_contiguous_memory_);
                break;
            case IndexType::HNSW_RABITQ:
                params = std::make_shared<HnswRabitqIndexParams>(metric_type_, rabitq_total_bits_, rabitq_num_clusters_, hnsw_m_, hnsw_ef_construction_, rabitq_sample_count_);
                break;
            case IndexType::FLAT:
                params = std::make_shared<FlatIndexParams>(metric_type_, quantize_type_);
                break;
            case IndexType::IVF:
                params = std::make_shared<IVFIndexParams>(metric_type_, ivf_n_list_, ivf_n_iters_, ivf_use_soar_, quantize_type_);
                break;
            case IndexType::INVERT:
                params = std::make_shared<InvertIndexParams>(invert_enable_range_, invert_enable_wildcard_);
                break;
            case IndexType::IVF_RABITQ:
                // No quantize_type argument: the upstream constructor always sets
                // QuantizeType::RABITQ itself.
                params = std::make_shared<IvfRabitqIndexParams>(metric_type_, ivf_rabitq_nlist_, rabitq_total_bits_, rabitq_sample_count_);
                break;
            case IndexType::VAMANA:
                // Pass an empty QuantizerParam; the rotate branch below still
                // overwrites it when setQuantizerEnableRotate() was used.
                params = std::make_shared<VamanaIndexParams>(metric_type_, vamana_max_degree_, vamana_search_list_size_, vamana_alpha_, vamana_saturate_graph_, vamana_use_contiguous_memory_, vamana_use_id_map_, quantize_type_, QuantizerParam{}, vamana_two_pass_build_);
                break;
            case IndexType::DISKANN:
                params = std::make_shared<DiskAnnIndexParams>(metric_type_, diskann_max_degree_, diskann_list_size_, diskann_pq_chunk_num_, quantize_type_);
                break;
            case IndexType::FTS:
                params = std::make_shared<FtsIndexParams>(fts_tokenizer_name_, fts_filters_, fts_extra_params_);
                break;
            default:
                return nullptr;
        }
        if (quantizer_enable_rotate_) {
            // Rotation applies only to vector index types; non-vector types (INVERT)
            // silently skip it (upstream C API rejects with INVALID_ARGUMENT instead).
            auto* vector_params = dynamic_cast<VectorIndexParams*>(params.get());
            if (vector_params) {
                vector_params->set_quantizer_param(QuantizerParam(true));
            }
        }
        return params;
    }
};

static IndexType to_index_type(int v) {
    switch (v) {
        case 1: return IndexType::HNSW;
        case 2: return IndexType::IVF;
        case 3: return IndexType::FLAT;
        case 4: return IndexType::HNSW_RABITQ;
        case 5: return IndexType::VAMANA;
        case 6: return IndexType::DISKANN;
        case 7: return IndexType::IVF_RABITQ;
        case 10: return IndexType::INVERT;
        case 11: return IndexType::FTS;
        default: return IndexType::UNDEFINED;
    }
}

// Inverse of to_index_type(): PHP's ZVec::INDEX_TYPE_VAMANA=5 /
// INDEX_TYPE_DISKANN=6 are swapped relative to the C++ IndexType enum
// (DISKANN=5, VAMANA=6), so this must not simply static_cast the C++ value.
static int from_index_type(IndexType t) {
    switch (t) {
        case IndexType::HNSW: return 1;
        case IndexType::IVF: return 2;
        case IndexType::FLAT: return 3;
        case IndexType::HNSW_RABITQ: return 4;
        case IndexType::IVF_RABITQ: return 7;
        case IndexType::VAMANA: return 5;
        case IndexType::DISKANN: return 6;
        case IndexType::INVERT: return 10;
        case IndexType::FTS: return 11;
        default: return 0;
    }
}

zvec_index_params_t zvec_index_params_create(int index_type, int metric_type) {
    auto* holder = new IndexParamsHolder(to_index_type(index_type), to_metric_type(metric_type));
    return static_cast<zvec_index_params_t>(holder);
}

void zvec_index_params_free(zvec_index_params_t params) {
    if (!params) return;
    delete static_cast<IndexParamsHolder*>(params);
}

void zvec_index_params_set_hnsw(zvec_index_params_t params, int m, int ef_construction, int quantize_type, int use_contiguous_memory) {
    if (!params) return;
    auto* h = static_cast<IndexParamsHolder*>(params);
    h->hnsw_m_ = m;
    h->hnsw_ef_construction_ = ef_construction;
    h->quantize_type_ = to_quantize_type(quantize_type);
    h->hnsw_use_contiguous_memory_ = (bool)use_contiguous_memory;
}

void zvec_index_params_set_flat(zvec_index_params_t params, int quantize_type) {
    if (!params) return;
    auto* h = static_cast<IndexParamsHolder*>(params);
    h->quantize_type_ = to_quantize_type(quantize_type);
}

void zvec_index_params_set_ivf(zvec_index_params_t params, int n_list, int n_iters, int use_soar, int quantize_type) {
    if (!params) return;
    auto* h = static_cast<IndexParamsHolder*>(params);
    h->ivf_n_list_ = n_list;
    h->ivf_n_iters_ = n_iters;
    h->ivf_use_soar_ = (bool)use_soar;
    h->quantize_type_ = to_quantize_type(quantize_type);
}

void zvec_index_params_set_hnsw_rabitq(zvec_index_params_t params, int total_bits, int num_clusters, int m, int ef_construction, int sample_count) {
    if (!params) return;
    auto* h = static_cast<IndexParamsHolder*>(params);
    h->rabitq_total_bits_ = total_bits;
    h->rabitq_num_clusters_ = num_clusters;
    h->hnsw_m_ = m;
    h->hnsw_ef_construction_ = ef_construction;
    h->rabitq_sample_count_ = sample_count;
}

void zvec_index_params_set_ivf_rabitq(zvec_index_params_t params, int nlist, int total_bits, int sample_count) {
    if (!params) return;
    auto* h = static_cast<IndexParamsHolder*>(params);
    h->ivf_rabitq_nlist_ = nlist;
    h->rabitq_total_bits_ = total_bits;
    h->rabitq_sample_count_ = sample_count;
}

void zvec_index_params_set_vamana(zvec_index_params_t params, int max_degree, int search_list_size, float alpha, int saturate_graph, int use_contiguous_memory, int use_id_map, int quantize_type) {
    if (!params) return;
    auto* h = static_cast<IndexParamsHolder*>(params);
    h->vamana_max_degree_ = max_degree;
    h->vamana_search_list_size_ = search_list_size;
    h->vamana_alpha_ = alpha;
    h->vamana_saturate_graph_ = (bool)saturate_graph;
    h->vamana_use_contiguous_memory_ = (bool)use_contiguous_memory;
    h->vamana_use_id_map_ = (bool)use_id_map;
    h->quantize_type_ = to_quantize_type(quantize_type);
}

void zvec_index_params_set_vamana_two_pass_build(zvec_index_params_t params, int two_pass_build) {
    if (!params) return;
    static_cast<IndexParamsHolder*>(params)->vamana_two_pass_build_ = (two_pass_build != 0);
}

void zvec_index_params_set_diskann(zvec_index_params_t params, int max_degree, int list_size, int pq_chunk_num) {
    if (!params) return;
    auto* h = static_cast<IndexParamsHolder*>(params);
    h->diskann_max_degree_ = max_degree;
    h->diskann_list_size_ = list_size;
    h->diskann_pq_chunk_num_ = pq_chunk_num;
}

void zvec_index_params_set_fts(zvec_index_params_t params, const char* tokenizer_name, const char** filters, int filter_count, const char* extra_params) {
    if (!params) return;
    auto* h = static_cast<IndexParamsHolder*>(params);
    if (tokenizer_name && tokenizer_name[0] != '\0') {
        h->fts_tokenizer_name_ = tokenizer_name;
    }
    h->fts_filters_.clear();
    for (int i = 0; filters && i < filter_count; i++) {
        if (filters[i] && filters[i][0] != '\0') {
            h->fts_filters_.emplace_back(filters[i]);
        }
    }
    if (extra_params) {
        h->fts_extra_params_ = extra_params;
    }
}

void zvec_index_params_set_invert(zvec_index_params_t params, int enable_range, int enable_wildcard) {
    if (!params) return;
    auto* h = static_cast<IndexParamsHolder*>(params);
    h->invert_enable_range_ = (bool)enable_range;
    h->invert_enable_wildcard_ = (bool)enable_wildcard;
}

void zvec_index_params_set_quantize_type(zvec_index_params_t params, int quantize_type) {
    if (!params) return;
    auto* h = static_cast<IndexParamsHolder*>(params);
    h->quantize_type_ = to_quantize_type(quantize_type);
}

void zvec_index_params_set_quantizer_enable_rotate(zvec_index_params_t params, int enable_rotate) {
    if (!params) return;
    auto* h = static_cast<IndexParamsHolder*>(params);
    h->quantizer_enable_rotate_ = (bool)enable_rotate;
}

void zvec_index_params_set_metric_type(zvec_index_params_t params, int metric_type) {
    if (!params) return;
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
    return MAKE_STATUS(c->create_index(field_name, index_params, opts));
}

zvec_status_t zvec_schema_add_field_string_with_index(zvec_schema_t schema, const char* name, int nullable, zvec_index_params_t params) {
    if (!schema || !name || !params) {
        return MAKE_STATUS(Status(StatusCode::INVALID_ARGUMENT, "null schema, name or index params"));
    }
    auto* s = static_cast<CollectionSchema*>(schema);
    auto index_params = static_cast<IndexParamsHolder*>(params)->build();
    if (!index_params) {
        return MAKE_STATUS(Status(StatusCode::INVALID_ARGUMENT, "Invalid or unsupported index type"));
    }
    // FieldSchema's constructor clones index_params, so the caller's ZVecIndexParams
    // stays valid and frees its own handle whenever it goes out of scope.
    return MAKE_STATUS(s->add_field(std::make_shared<FieldSchema>(name, DataType::STRING, (bool)nullable, index_params)));
}

// --- Doc ---

zvec_doc_t zvec_doc_create(const char* pk) {
    auto* doc = new Doc();
    doc->set_pk(pk);
    return static_cast<zvec_doc_t>(doc);
}

void zvec_doc_free(zvec_doc_t doc) {
    if (!doc) return;
    delete static_cast<Doc*>(doc);
}

void zvec_doc_set_int64(zvec_doc_t doc, const char* field, int64_t value) {
    if (!doc) return;
    static_cast<Doc*>(doc)->set<int64_t>(field, value);
}

void zvec_doc_set_string(zvec_doc_t doc, const char* field, const char* value) {
    if (!doc) return;
    static_cast<Doc*>(doc)->set<std::string>(field, std::string(value));
}

void zvec_doc_set_float(zvec_doc_t doc, const char* field, float value) {
    if (!doc) return;
    static_cast<Doc*>(doc)->set<float>(field, value);
}

void zvec_doc_set_double(zvec_doc_t doc, const char* field, double value) {
    if (!doc) return;
    static_cast<Doc*>(doc)->set<double>(field, value);
}

void zvec_doc_set_vector_fp32(zvec_doc_t doc, const char* field, const float* data, uint32_t dim) {
    if (!doc) return;
    std::vector<float> vec(data, data + dim);
    static_cast<Doc*>(doc)->set<std::vector<float>>(field, std::move(vec));
}

void zvec_doc_set_vector_fp64(zvec_doc_t doc, const char* field, const double* data, uint32_t dim) {
    if (!doc) return;
    std::vector<double> vec(data, data + dim);
    static_cast<Doc*>(doc)->set<std::vector<double>>(field, std::move(vec));
}

void zvec_doc_set_bool(zvec_doc_t doc, const char* field, int value) {
    if (!doc) return;
    static_cast<Doc*>(doc)->set<bool>(field, (bool)value);
}

void zvec_doc_set_int32(zvec_doc_t doc, const char* field, int32_t value) {
    if (!doc) return;
    static_cast<Doc*>(doc)->set<int32_t>(field, value);
}

void zvec_doc_set_uint32(zvec_doc_t doc, const char* field, uint32_t value) {
    if (!doc) return;
    static_cast<Doc*>(doc)->set<uint32_t>(field, value);
}

void zvec_doc_set_uint64(zvec_doc_t doc, const char* field, uint64_t value) {
    if (!doc) return;
    static_cast<Doc*>(doc)->set<uint64_t>(field, value);
}

void zvec_doc_set_vector_int8(zvec_doc_t doc, const char* field, const int8_t* data, uint32_t dim) {
    if (!doc) return;
    std::vector<int8_t> vec(data, data + dim);
    static_cast<Doc*>(doc)->set<std::vector<int8_t>>(field, std::move(vec));
}

void zvec_doc_set_vector_fp16(zvec_doc_t doc, const char* field, const uint16_t* data, uint32_t dim) {
    if (!doc) return;
    std::vector<ailego::Float16> vec;
    vec.reserve(dim);
    for (uint32_t i = 0; i < dim; i++) {
        vec.push_back(ailego::FloatHelper::ToFP32(data[i]));
    }
    static_cast<Doc*>(doc)->set<std::vector<ailego::Float16>>(field, std::move(vec));
}

void zvec_doc_set_sparse_vector_fp32(zvec_doc_t doc, const char* field, const uint32_t* indices, const float* values, uint32_t count) {
    if (!doc) return;
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
    if (!doc) return;
    std::vector<int8_t> vec(data, data + dim);
    static_cast<Doc*>(doc)->set<std::vector<int8_t>>(field, std::move(vec));
}

void zvec_doc_set_vector_int16(zvec_doc_t doc, const char* field, const int16_t* data, uint32_t dim) {
    if (!doc) return;
    std::vector<int16_t> vec(data, data + dim);
    static_cast<Doc*>(doc)->set<std::vector<int16_t>>(field, std::move(vec));
}

void zvec_doc_set_vector_binary32(zvec_doc_t doc, const char* field, const uint32_t* data, uint32_t dim) {
    if (!doc) return;
    std::vector<uint32_t> vec(data, data + dim);
    static_cast<Doc*>(doc)->set<std::vector<uint32_t>>(field, std::move(vec));
}

void zvec_doc_set_vector_binary64(zvec_doc_t doc, const char* field, const uint64_t* data, uint32_t dim) {
    if (!doc) return;
    std::vector<uint64_t> vec(data, data + dim);
    static_cast<Doc*>(doc)->set<std::vector<uint64_t>>(field, std::move(vec));
}

void zvec_doc_set_sparse_vector_fp16(zvec_doc_t doc, const char* field, const uint32_t* indices, const uint16_t* values, uint32_t count) {
    if (!doc) return;
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
    if (!doc) return;
    static_cast<Doc*>(doc)->set<std::string>(field, std::string(reinterpret_cast<const char*>(data), size));
}

void zvec_doc_set_array_int32(zvec_doc_t doc, const char* field, const int32_t* data, uint32_t count) {
    if (!doc) return;
    std::vector<int32_t> vec(data, data + count);
    static_cast<Doc*>(doc)->set<std::vector<int32_t>>(field, std::move(vec));
}

void zvec_doc_set_array_int64(zvec_doc_t doc, const char* field, const int64_t* data, uint32_t count) {
    if (!doc) return;
    std::vector<int64_t> vec(data, data + count);
    static_cast<Doc*>(doc)->set<std::vector<int64_t>>(field, std::move(vec));
}

void zvec_doc_set_array_uint32(zvec_doc_t doc, const char* field, const uint32_t* data, uint32_t count) {
    if (!doc) return;
    std::vector<uint32_t> vec(data, data + count);
    static_cast<Doc*>(doc)->set<std::vector<uint32_t>>(field, std::move(vec));
}

void zvec_doc_set_array_uint64(zvec_doc_t doc, const char* field, const uint64_t* data, uint32_t count) {
    if (!doc) return;
    std::vector<uint64_t> vec(data, data + count);
    static_cast<Doc*>(doc)->set<std::vector<uint64_t>>(field, std::move(vec));
}

void zvec_doc_set_array_float(zvec_doc_t doc, const char* field, const float* data, uint32_t count) {
    if (!doc) return;
    std::vector<float> vec(data, data + count);
    static_cast<Doc*>(doc)->set<std::vector<float>>(field, std::move(vec));
}

void zvec_doc_set_array_double(zvec_doc_t doc, const char* field, const double* data, uint32_t count) {
    if (!doc) return;
    std::vector<double> vec(data, data + count);
    static_cast<Doc*>(doc)->set<std::vector<double>>(field, std::move(vec));
}

void zvec_doc_set_array_string(zvec_doc_t doc, const char* field, const char** strings, uint32_t count) {
    if (!doc) return;
    std::vector<std::string> vec;
    vec.reserve(count);
    for (uint32_t i = 0; i < count; i++) {
        vec.emplace_back(strings[i]);
    }
    static_cast<Doc*>(doc)->set<std::vector<std::string>>(field, std::move(vec));
}

void zvec_doc_set_array_bool(zvec_doc_t doc, const char* field, const uint8_t* data, uint32_t count) {
    if (!doc) return;
    std::vector<bool> vec;
    vec.reserve(count);
    for (uint32_t i = 0; i < count; i++) {
        vec.push_back((bool)data[i]);
    }
    static_cast<Doc*>(doc)->set<std::vector<bool>>(field, std::move(vec));
}

// --- Enhanced Doc API ---

void zvec_doc_set_field_null(zvec_doc_t doc, const char* field) {
    if (!doc) return;
    static_cast<Doc*>(doc)->set_null(field);
}

int zvec_doc_is_field_null(zvec_doc_t doc, const char* field) {
    if (!doc) return 0;
    return static_cast<Doc*>(doc)->is_null(field) ? 1 : 0;
}

void zvec_doc_remove_field(zvec_doc_t doc, const char* field) {
    if (!doc) return;
    static_cast<Doc*>(doc)->remove(field);
}

void zvec_doc_merge(zvec_doc_t doc, zvec_doc_t other) {
    if (!doc || !other) return;
    static_cast<Doc*>(doc)->merge(*static_cast<Doc*>(other));
}

zvec_status_t zvec_doc_serialize(zvec_doc_t doc, uint8_t** data, size_t* size) {
    if (!doc) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto serialized = static_cast<Doc*>(doc)->serialize();
    *size = serialized.size();
    *data = static_cast<uint8_t*>(malloc(*size));
    if (!*data) {
        return MAKE_STATUS(Status(StatusCode::RESOURCE_EXHAUSTED, "Failed to allocate memory for serialized data"));
    }
    memcpy(*data, serialized.data(), *size);
    return ok_status();
}

void zvec_free_serialized(uint8_t* data) {
    free(data);
}

zvec_status_t zvec_doc_deserialize(const uint8_t* data, size_t size, zvec_doc_t* out) {
    auto doc = Doc::deserialize(data, size);
    if (!doc) {
        return MAKE_STATUS(Status(StatusCode::INVALID_ARGUMENT, "Failed to deserialize document"));
    }
    // Doc::deserialize returns a shared_ptr<Doc>, we need to release ownership
    // to a raw pointer that the caller will free with zvec_doc_free
    auto* raw_doc = new Doc(*doc);
    *out = static_cast<zvec_doc_t>(raw_doc);
    return ok_status();
}

int zvec_doc_is_empty(zvec_doc_t doc) {
    if (!doc) return 0;
    return static_cast<Doc*>(doc)->is_empty() ? 1 : 0;
}

void zvec_doc_clear(zvec_doc_t doc) {
    if (!doc) return;
    static_cast<Doc*>(doc)->clear();
}

size_t zvec_doc_memory_usage(zvec_doc_t doc) {
    if (!doc) return 0;
    return static_cast<Doc*>(doc)->memory_usage();
}

void zvec_doc_set_operator(zvec_doc_t doc, int op) {
    if (!doc) return;
    static_cast<Doc*>(doc)->set_operator(static_cast<Operator>(op));
}

int zvec_doc_get_operator(zvec_doc_t doc) {
    if (!doc) return 0;
    return static_cast<int>(static_cast<Doc*>(doc)->get_operator());
}

static thread_local std::string g_pk_buf;

const char* zvec_doc_get_pk(zvec_doc_t doc) {
    if (!doc) return nullptr;
    g_pk_buf = static_cast<Doc*>(doc)->pk();
    return g_pk_buf.c_str();
}

uint64_t zvec_doc_get_doc_id(zvec_doc_t doc) {
    if (!doc) return 0;
    return static_cast<Doc*>(doc)->doc_id();
}

float zvec_doc_get_score(zvec_doc_t doc) {
    if (!doc) return 0.0f;
    return static_cast<Doc*>(doc)->score();
}

int zvec_doc_get_int64(zvec_doc_t doc, const char* field, int64_t* out) {
    if (!doc) return 0;
    auto val = static_cast<Doc*>(doc)->get<int64_t>(field);
    if (val.has_value()) { *out = val.value(); return 1; }
    return 0;
}

static thread_local std::string g_string_buf;

int zvec_doc_get_string(zvec_doc_t doc, const char* field, const char** out) {
    if (!doc) return 0;
    auto result = static_cast<Doc*>(doc)->get_field<std::string>(field);
    if (result.ok()) {
        g_string_buf = result.value();
        *out = g_string_buf.c_str();
        return 1;
    }
    return 0;
}

int zvec_doc_get_float(zvec_doc_t doc, const char* field, float* out) {
    if (!doc) return 0;
    auto val = static_cast<Doc*>(doc)->get<float>(field);
    if (val.has_value()) { *out = val.value(); return 1; }
    return 0;
}

int zvec_doc_get_double(zvec_doc_t doc, const char* field, double* out) {
    if (!doc) return 0;
    auto val = static_cast<Doc*>(doc)->get<double>(field);
    if (val.has_value()) { *out = val.value(); return 1; }
    return 0;
}

static thread_local std::vector<float> g_vector_buf;
static thread_local std::vector<double> g_vector_fp64_buf;

int zvec_doc_get_vector_fp32(zvec_doc_t doc, const char* field, const float** out, uint32_t* dim) {
    if (!doc) return 0;
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
    if (!doc) return 0;
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
    if (!doc) return 0;
    auto val = static_cast<Doc*>(doc)->get<bool>(field);
    if (val.has_value()) { *out = val.value() ? 1 : 0; return 1; }
    return 0;
}

int zvec_doc_get_int32(zvec_doc_t doc, const char* field, int32_t* out) {
    if (!doc) return 0;
    auto val = static_cast<Doc*>(doc)->get<int32_t>(field);
    if (val.has_value()) { *out = val.value(); return 1; }
    return 0;
}

int zvec_doc_get_uint32(zvec_doc_t doc, const char* field, uint32_t* out) {
    if (!doc) return 0;
    auto val = static_cast<Doc*>(doc)->get<uint32_t>(field);
    if (val.has_value()) { *out = val.value(); return 1; }
    return 0;
}

int zvec_doc_get_uint64(zvec_doc_t doc, const char* field, uint64_t* out) {
    if (!doc) return 0;
    auto val = static_cast<Doc*>(doc)->get<uint64_t>(field);
    if (val.has_value()) { *out = val.value(); return 1; }
    return 0;
}

static thread_local std::vector<int8_t> g_vector_int8_buf;

int zvec_doc_get_vector_int8(zvec_doc_t doc, const char* field, const int8_t** out, uint32_t* dim) {
    if (!doc) return 0;
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
    if (!doc) return 0;
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
// Use a circular buffer approach with 256 slots to handle multiple concurrent calls
static thread_local std::array<std::pair<std::vector<uint32_t>, std::vector<float>>, 256> g_sparse_buffers;
static thread_local size_t g_sparse_buffer_idx = 0;

int zvec_doc_get_sparse_vector_fp32(zvec_doc_t doc, const char* field, const uint32_t** indices_out, const float** values_out, uint32_t* count_out) {
    if (!doc) return 0;
    auto result = static_cast<Doc*>(doc)->get_field<std::pair<std::vector<uint32_t>, std::vector<float>>>(field);
    if (result.ok()) {
        // Use circular buffer to keep data alive - each call gets a new slot
        auto& slot = g_sparse_buffers[g_sparse_buffer_idx];
        slot = result.value();
        *indices_out = slot.first.data();
        *values_out = slot.second.data();
        *count_out = static_cast<uint32_t>(slot.first.size());
        g_sparse_buffer_idx = (g_sparse_buffer_idx + 1) % 256;
        return 1;
    }
    return 0;
}

static thread_local std::vector<int16_t> g_vector_int16_buf;

int zvec_doc_get_vector_int16(zvec_doc_t doc, const char* field, const int16_t** out, uint32_t* dim) {
    if (!doc) return 0;
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
    if (!doc) return 0;
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
    if (!doc) return 0;
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
    if (!doc) return 0;
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
static thread_local std::array<std::pair<std::vector<uint32_t>, std::vector<ailego::Float16>>, 256> g_sparse_fp16_buffers;
static thread_local size_t g_sparse_fp16_buffer_idx = 0;

int zvec_doc_get_sparse_vector_fp16(zvec_doc_t doc, const char* field, const uint32_t** indices_out, const uint16_t** values_out, uint32_t* count_out) {
    if (!doc) return 0;
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
        g_sparse_fp16_buffer_idx = (g_sparse_fp16_buffer_idx + 1) % 256;
        return 1;
    }
    return 0;
}

static thread_local std::string g_binary_buf;

int zvec_doc_get_binary(zvec_doc_t doc, const char* field, const uint8_t** out, uint32_t* size) {
    if (!doc) return 0;
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
    if (!doc) return 0;
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
    if (!doc) return 0;
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
    if (!doc) return 0;
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
    if (!doc) return 0;
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
    if (!doc) return 0;
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
    if (!doc) return 0;
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
    if (!doc) return 0;
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
    if (!doc) return 0;
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
    if (!doc) return 0;
    return static_cast<Doc*>(doc)->has(field) ? 1 : 0;
}

int zvec_doc_has_vector(zvec_doc_t doc, const char* field) {
    if (!doc) return 0;
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
    if (!doc) return 0;
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
    if (!doc) return 0;
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
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    std::vector<Doc> doc_vec;
    doc_vec.reserve(count);
    for (int i = 0; i < count; i++) {
        doc_vec.push_back(*static_cast<Doc*>(docs[i]));
    }
    auto res = c->insert(doc_vec);
    if (!res.has_value()) {
        return MAKE_STATUS(res.error());
    }
    for (const auto& s : res.value()) {
        if (!s.ok()) return MAKE_STATUS(s);
    }
    return ok_status();
}

zvec_status_t zvec_collection_upsert(zvec_collection_t coll, zvec_doc_t* docs, int count) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    std::vector<Doc> doc_vec;
    doc_vec.reserve(count);
    for (int i = 0; i < count; i++) {
        doc_vec.push_back(*static_cast<Doc*>(docs[i]));
    }
    auto res = c->upsert(doc_vec);
    if (!res.has_value()) {
        return MAKE_STATUS(res.error());
    }
    for (const auto& s : res.value()) {
        if (!s.ok()) return MAKE_STATUS(s);
    }
    return ok_status();
}

zvec_status_t zvec_collection_update(zvec_collection_t coll, zvec_doc_t* docs, int count) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    std::vector<Doc> doc_vec;
    doc_vec.reserve(count);
    for (int i = 0; i < count; i++) {
        doc_vec.push_back(*static_cast<Doc*>(docs[i]));
    }
    auto res = c->update(doc_vec);
    if (!res.has_value()) {
        return MAKE_STATUS(res.error());
    }
    for (const auto& s : res.value()) {
        if (!s.ok()) return MAKE_STATUS(s);
    }
    return ok_status();
}

// --- Batch operations with per-document status ---

// Shared helper: doc preparation, operation dispatch, and result building.
// Each batch wrapper passes a lambda that calls the correct Collection method.
static zvec_status_t collection_batch_op(
    zvec_collection_t coll,
    zvec_doc_t* docs,
    int count,
    zvec_batch_result_t* result,
    std::function<Result<WriteResults>(Collection*, std::vector<Doc>&)> op)
{
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
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

    auto res = op(c, doc_vec);
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

zvec_status_t zvec_collection_insert_batch(zvec_collection_t coll, zvec_doc_t* docs, int count, zvec_batch_result_t* result) {
    return collection_batch_op(coll, docs, count, result,
        [](Collection* c, std::vector<Doc>& v) { return c->insert(v); });
}

zvec_status_t zvec_collection_upsert_batch(zvec_collection_t coll, zvec_doc_t* docs, int count, zvec_batch_result_t* result) {
    return collection_batch_op(coll, docs, count, result,
        [](Collection* c, std::vector<Doc>& v) { return c->upsert(v); });
}

zvec_status_t zvec_collection_update_batch(zvec_collection_t coll, zvec_doc_t* docs, int count, zvec_batch_result_t* result) {
    return collection_batch_op(coll, docs, count, result,
        [](Collection* c, std::vector<Doc>& v) { return c->update(v); });
}

void zvec_batch_result_free(zvec_batch_result_t* result) {
    if (!result) return;
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
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    std::vector<std::string> pk_vec;
    pk_vec.reserve(count);
    for (int i = 0; i < count; i++) {
        pk_vec.emplace_back(pks[i]);
    }
    auto res = c->delete_(pk_vec);
    if (!res.has_value()) {
        return MAKE_STATUS(res.error());
    }
    return ok_status();
}

zvec_status_t zvec_collection_delete_by_filter(zvec_collection_t coll, const char* filter) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    return MAKE_STATUS(c->delete_by_filter(filter));
}

// --- VectorQuery opaque object ---

struct VectorQueryHolder {
    SearchQuery query;
    float radius_ = 0.0f;
    bool is_linear_ = false;
    bool is_using_refiner_ = false;
    // HNSW prefetch, stored so it survives the query_params_ replacement that
    // every set*Params() call performs.
    uint32_t prefetch_offset_ = core_interface::kDefaultPrefetchOffset;
    uint32_t prefetch_lines_ = core_interface::kDefaultPrefetchLines;
};

struct GroupByVectorQueryHolder {
    GroupByVectorQuery query;
    float radius_ = 0.0f;
    bool is_linear_ = false;
    bool is_using_refiner_ = false;
};

// Merge radius/is_linear/is_using_refiner set before a set*Params() call into
// the newly created params so setter ordering does not matter (#197).
// Constructor defaults for these fields are 0.0f/false/false, so an
// unconditional merge is a no-op when they were never set.
static void merge_stored_query_settings(const VectorQueryHolder* holder) {
    auto& params = holder->query.target_.query_params_;
    if (!params) return;
    params->set_radius(holder->radius_);
    params->set_is_linear(holder->is_linear_);
    params->set_is_using_refiner(holder->is_using_refiner_);
    // Prefetch exists on HnswQueryParams and VamanaQueryParams; re-apply so it is
    // not lost when a set*Params() call rebuilt query_params_ after
    // setHnswPrefetch() / setVamanaPrefetch(). Both share the stored fields, so the
    // call order relative to set*Params() does not matter.
    if (auto* hnsw = dynamic_cast<HnswQueryParams*>(params.get())) {
        hnsw->set_prefetch_offset(holder->prefetch_offset_);
        hnsw->set_prefetch_lines(holder->prefetch_lines_);
    } else if (auto* vamana = dynamic_cast<VamanaQueryParams*>(params.get())) {
        vamana->set_prefetch_offset(holder->prefetch_offset_);
        vamana->set_prefetch_lines(holder->prefetch_lines_);
    }
}

zvec_vector_query_t zvec_vector_query_create(void) {
    auto* holder = new VectorQueryHolder();
    return static_cast<zvec_vector_query_t>(holder);
}

void zvec_vector_query_free(zvec_vector_query_t q) {
    if (!q) return;
    delete static_cast<VectorQueryHolder*>(q);
}

void zvec_vector_query_set_field_name(zvec_vector_query_t q, const char* field_name) {
    if (!q) return;
    static_cast<VectorQueryHolder*>(q)->query.target_.field_name_ = field_name ? field_name : "";
}

void zvec_vector_query_set_topk(zvec_vector_query_t q, int topk) {
    if (!q) return;
    static_cast<VectorQueryHolder*>(q)->query.topk_ = topk;
}

void zvec_vector_query_set_include_vector(zvec_vector_query_t q, int include) {
    if (!q) return;
    static_cast<VectorQueryHolder*>(q)->query.include_vector_ = (bool)include;
}

void zvec_vector_query_set_filter(zvec_vector_query_t q, const char* filter) {
    if (!q) return;
    if (filter && filter[0] != '\0') {
        static_cast<VectorQueryHolder*>(q)->query.filter_ = filter;
    }
}

void zvec_vector_query_set_output_fields(zvec_vector_query_t q, const char** fields, int count) {
    if (!q) return;
    if (fields && count > 0) {
        std::vector<std::string> field_vec;
        field_vec.reserve(count);
        for (int i = 0; i < count; i++) {
            field_vec.emplace_back(fields[i]);
        }
        static_cast<VectorQueryHolder*>(q)->query.output_fields_ = std::move(field_vec);
    }
}

void zvec_vector_query_set_include_doc_id(zvec_vector_query_t q, int include) {
    if (!q) return;
    static_cast<VectorQueryHolder*>(q)->query.include_doc_id_ = (bool)include;
}

void zvec_vector_query_set_hnsw_ef(zvec_vector_query_t q, int ef) {
    if (!q) return;
    auto* holder = static_cast<VectorQueryHolder*>(q);
    holder->query.target_.query_params_ = std::make_shared<HnswQueryParams>(ef);
    merge_stored_query_settings(holder);
}

void zvec_vector_query_set_hnsw_prefetch(zvec_vector_query_t q, int prefetch_offset, int prefetch_lines) {
    if (!q) return;
    auto* holder = static_cast<VectorQueryHolder*>(q);
    holder->prefetch_offset_ = prefetch_offset < 0 ? 0 : static_cast<uint32_t>(prefetch_offset);
    holder->prefetch_lines_ = prefetch_lines < 0 ? 0 : static_cast<uint32_t>(prefetch_lines);
    // If no params exist yet, create HNSW ones so the values are actually used.
    if (!holder->query.target_.query_params_) {
        holder->query.target_.query_params_ = std::make_shared<HnswQueryParams>();
    }
    merge_stored_query_settings(holder);
}

void zvec_vector_query_set_vamana_prefetch(zvec_vector_query_t q, int prefetch_offset, int prefetch_lines) {
    if (!q) return;
    auto* holder = static_cast<VectorQueryHolder*>(q);
    holder->prefetch_offset_ = prefetch_offset < 0 ? 0 : static_cast<uint32_t>(prefetch_offset);
    holder->prefetch_lines_ = prefetch_lines < 0 ? 0 : static_cast<uint32_t>(prefetch_lines);
    // Create Vamana params if none exist yet, so the values are actually used.
    // Creating HnswQueryParams here would be wrong: upstream rejects params whose
    // type does not match the field's index type.
    if (!holder->query.target_.query_params_) {
        holder->query.target_.query_params_ = std::make_shared<VamanaQueryParams>();
    }
    merge_stored_query_settings(holder);
}

void zvec_vector_query_set_hnsw_rabitq_ef(zvec_vector_query_t q, int ef) {
    if (!q) return;
    auto* holder = static_cast<VectorQueryHolder*>(q);
    holder->query.target_.query_params_ = std::make_shared<HnswRabitqQueryParams>(ef);
    merge_stored_query_settings(holder);
}

void zvec_vector_query_set_fts(zvec_vector_query_t q, const char* field_name, const char* query_string, const char* match_string, const char* default_operator) {
    if (!q) return;
    auto* holder = static_cast<VectorQueryHolder*>(q);
    if (field_name && field_name[0] != '\0') {
        holder->query.target_.field_name_ = field_name;
    }
    // FTS replaces the vector clause in the target variant; query_string is the
    // Lucene-style side, match_string the natural-language alternative.
    FtsClause fts;
    if (query_string) fts.query_string_ = query_string;
    if (match_string) fts.match_string_ = match_string;
    holder->query.target_.clause_ = fts;
    if (default_operator && default_operator[0] != '\0') {
        auto params = std::make_shared<FtsQueryParams>();
        params->set_default_operator(default_operator);
        holder->query.target_.query_params_ = params;
    }
    merge_stored_query_settings(holder);
}

void zvec_vector_query_set_vamana_ef_search(zvec_vector_query_t q, int ef_search) {
    if (!q) return;
    auto* holder = static_cast<VectorQueryHolder*>(q);
    holder->query.target_.query_params_ = std::make_shared<VamanaQueryParams>(ef_search);
    merge_stored_query_settings(holder);
}

void zvec_vector_query_set_diskann_list_size(zvec_vector_query_t q, int list_size) {
    if (!q) return;
    auto* holder = static_cast<VectorQueryHolder*>(q);
    holder->query.target_.query_params_ = std::make_shared<DiskAnnQueryParams>(list_size);
    merge_stored_query_settings(holder);
}

void zvec_vector_query_set_ivf_nprobe(zvec_vector_query_t q, int nprobe) {
    if (!q) return;
    auto* holder = static_cast<VectorQueryHolder*>(q);
    holder->query.target_.query_params_ = std::make_shared<IVFQueryParams>(nprobe);
    merge_stored_query_settings(holder);
}

void zvec_vector_query_set_ivf_rabitq_nprobe(zvec_vector_query_t q, int nprobe) {
    if (!q) return;
    auto* holder = static_cast<VectorQueryHolder*>(q);
    holder->query.target_.query_params_ = std::make_shared<IvfRabitqQueryParams>(nprobe);
    // merge_stored_query_settings() re-applies radius, linear and refiner.
    merge_stored_query_settings(holder);
}

void zvec_vector_query_set_flat_mode(zvec_vector_query_t q) {
    if (!q) return;
    auto* holder = static_cast<VectorQueryHolder*>(q);
    holder->query.target_.query_params_ = std::make_shared<FlatQueryParams>();
    merge_stored_query_settings(holder);
}

void zvec_vector_query_set_radius(zvec_vector_query_t q, float radius) {
    if (!q) return;
    auto* holder = static_cast<VectorQueryHolder*>(q);
    holder->radius_ = radius;
    if (holder->query.target_.query_params_) {
        holder->query.target_.query_params_->set_radius(radius);
    }
}

void zvec_vector_query_set_is_linear(zvec_vector_query_t q, int is_linear) {
    if (!q) return;
    auto* holder = static_cast<VectorQueryHolder*>(q);
    holder->is_linear_ = (bool)is_linear;
    if (holder->query.target_.query_params_) {
        holder->query.target_.query_params_->set_is_linear((bool)is_linear);
    }
}

void zvec_vector_query_set_using_refiner(zvec_vector_query_t q, int refiner) {
    if (!q) return;
    auto* holder = static_cast<VectorQueryHolder*>(q);
    holder->is_using_refiner_ = (bool)refiner;
    if (holder->query.target_.query_params_) {
        holder->query.target_.query_params_->set_is_using_refiner((bool)refiner);
    }
}

void zvec_vector_query_set_vector_fp32(zvec_vector_query_t q, const float* data, uint32_t dim) {
    if (!q) return;
    auto* holder = static_cast<VectorQueryHolder*>(q);
    holder->query.target_.set_vector(std::string(reinterpret_cast<const char*>(data), dim * sizeof(float)));
}

void zvec_vector_query_set_vector_fp64(zvec_vector_query_t q, const double* data, uint32_t dim) {
    if (!q) return;
    auto* holder = static_cast<VectorQueryHolder*>(q);
    holder->query.target_.set_vector(std::string(reinterpret_cast<const char*>(data), dim * sizeof(double)));
}

// GroupByVectorQuery

zvec_group_by_vector_query_t zvec_group_by_vector_query_create(void) {
    auto* holder = new GroupByVectorQueryHolder();
    return static_cast<zvec_group_by_vector_query_t>(holder);
}

void zvec_group_by_vector_query_free(zvec_group_by_vector_query_t q) {
    if (!q) return;
    delete static_cast<GroupByVectorQueryHolder*>(q);
}

void zvec_group_by_vector_query_set_field_name(zvec_group_by_vector_query_t q, const char* field_name) {
    if (!q) return;
    static_cast<GroupByVectorQueryHolder*>(q)->query.target_.field_name_ = field_name ? field_name : "";
}

void zvec_group_by_vector_query_set_vector_fp32(zvec_group_by_vector_query_t q, const float* data, uint32_t dim) {
    if (!q) return;
    auto* holder = static_cast<GroupByVectorQueryHolder*>(q);
    holder->query.target_.set_vector(std::string(reinterpret_cast<const char*>(data), dim * sizeof(float)));
}

void zvec_group_by_vector_query_set_group_by_field(zvec_group_by_vector_query_t q, const char* field) {
    if (!q) return;
    static_cast<GroupByVectorQueryHolder*>(q)->query.group_by_field_name_ = field ? field : "";
}

void zvec_group_by_vector_query_set_group_count(zvec_group_by_vector_query_t q, uint32_t count) {
    if (!q) return;
    static_cast<GroupByVectorQueryHolder*>(q)->query.group_count_ = count;
}

void zvec_group_by_vector_query_set_group_topk(zvec_group_by_vector_query_t q, uint32_t topk) {
    if (!q) return;
    static_cast<GroupByVectorQueryHolder*>(q)->query.topk_per_group_ = topk;
}

void zvec_group_by_vector_query_set_include_vector(zvec_group_by_vector_query_t q, int include) {
    if (!q) return;
    static_cast<GroupByVectorQueryHolder*>(q)->query.include_vector_ = (bool)include;
}

void zvec_group_by_vector_query_set_filter(zvec_group_by_vector_query_t q, const char* filter) {
    if (!q) return;
    if (filter && filter[0] != '\0') {
        static_cast<GroupByVectorQueryHolder*>(q)->query.filter_ = filter;
    }
}

void zvec_group_by_vector_query_set_output_fields(zvec_group_by_vector_query_t q, const char** fields, int count) {
    if (!q) return;
    if (fields && count > 0) {
        std::vector<std::string> field_vec;
        field_vec.reserve(count);
        for (int i = 0; i < count; i++) {
            field_vec.emplace_back(fields[i]);
        }
        static_cast<GroupByVectorQueryHolder*>(q)->query.output_fields_ = std::move(field_vec);
    }
}

void zvec_group_by_vector_query_set_radius(zvec_group_by_vector_query_t q, float radius) {
    if (!q) return;
    auto* holder = static_cast<GroupByVectorQueryHolder*>(q);
    holder->radius_ = radius;
    if (holder->query.target_.query_params_) {
        holder->query.target_.query_params_->set_radius(radius);
    }
}

void zvec_group_by_vector_query_set_is_linear(zvec_group_by_vector_query_t q, int is_linear) {
    if (!q) return;
    auto* holder = static_cast<GroupByVectorQueryHolder*>(q);
    holder->is_linear_ = (bool)is_linear;
    if (holder->query.target_.query_params_) {
        holder->query.target_.query_params_->set_is_linear((bool)is_linear);
    }
}

void zvec_group_by_vector_query_set_using_refiner(zvec_group_by_vector_query_t q, int refiner) {
    if (!q) return;
    auto* holder = static_cast<GroupByVectorQueryHolder*>(q);
    holder->is_using_refiner_ = (bool)refiner;
    if (holder->query.target_.query_params_) {
        holder->query.target_.query_params_->set_is_using_refiner((bool)refiner);
    }
}

// New query entry points

static void fill_doc_list(const DocPtrList& doc_list, zvec_query_result_t* result);

static void ensure_query_params_for_field(Collection* c, SearchQuery& query, const VectorQueryHolder* holder) {
    // If query_params_ already set (via setHnswParams, setIvfParams, etc.), keep it
    if (query.target_.query_params_) return;

    // If radius, is_linear, or is_using_refiner has non-default value, create params
    if (holder->radius_ != 0.0f || holder->is_linear_ || holder->is_using_refiner_) {
        // Try to determine the field's index type from schema
        auto schema_res = c->schema();
        if (schema_res.has_value()) {
            const FieldSchema* field = schema_res.value().get_field(query.target_.field_name_.c_str());
            if (field) {
                IndexType idx_type = field->index_type();
                switch (idx_type) {
                    case IndexType::HNSW:
                        query.target_.query_params_ = std::make_shared<HnswQueryParams>(200, holder->radius_, holder->is_linear_, holder->is_using_refiner_);
                        return;
                    case IndexType::IVF:
                        query.target_.query_params_ = std::make_shared<IVFQueryParams>(10, holder->is_using_refiner_);
                        query.target_.query_params_->set_radius(holder->radius_);
                        query.target_.query_params_->set_is_linear(holder->is_linear_);
                        return;
                    case IndexType::FLAT:
                        query.target_.query_params_ = std::make_shared<FlatQueryParams>(holder->is_using_refiner_);
                        query.target_.query_params_->set_radius(holder->radius_);
                        query.target_.query_params_->set_is_linear(holder->is_linear_);
                        return;
                    case IndexType::HNSW_RABITQ:
                        query.target_.query_params_ = std::make_shared<HnswRabitqQueryParams>(200, holder->radius_, holder->is_linear_, holder->is_using_refiner_);
                        return;
                    case IndexType::IVF_RABITQ:
                        query.target_.query_params_ = std::make_shared<IvfRabitqQueryParams>(10, holder->radius_, holder->is_linear_, holder->is_using_refiner_);
                        return;
                    case IndexType::VAMANA:
                        query.target_.query_params_ = std::make_shared<VamanaQueryParams>(200, holder->radius_, holder->is_linear_, holder->is_using_refiner_);
                        return;
                    default:
                        break;
                }
            }
        }
        // Fallback: use HNSW params if can't determine index type
        query.target_.query_params_ = std::make_shared<HnswQueryParams>(200, holder->radius_, holder->is_linear_, holder->is_using_refiner_);
    }
}

zvec_status_t zvec_collection_query_vector(zvec_collection_t coll, const zvec_vector_query_t q, zvec_query_result_t* result) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    auto* holder = static_cast<VectorQueryHolder*>(q);

    // Ensure query params match the field's index type if radius/linear/refiner are set
    ensure_query_params_for_field(c, holder->query, holder);

    auto res = c->query(holder->query);
    if (!res.has_value()) {
        result->docs = nullptr;
        result->count = 0;
        return MAKE_STATUS(res.error());
    }

    fill_doc_list(res.value(), result);
    return ok_status();
}

zvec_status_t zvec_collection_group_by_query_vector(zvec_collection_t coll, const zvec_group_by_vector_query_t q, zvec_group_results_t* result) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    auto* holder = static_cast<GroupByVectorQueryHolder*>(q);

    // Ensure query params match the field's index type if radius/linear/refiner are set
    if (!holder->query.target_.query_params_ && (holder->radius_ != 0.0f || holder->is_linear_ || holder->is_using_refiner_)) {
        auto schema_res = c->schema();
        if (schema_res.has_value()) {
            const FieldSchema* field = schema_res.value().get_field(holder->query.target_.field_name_.c_str());
            if (field) {
                IndexType idx_type = field->index_type();
                switch (idx_type) {
                    case IndexType::HNSW:
                        holder->query.target_.query_params_ = std::make_shared<HnswQueryParams>(200, holder->radius_, holder->is_linear_, holder->is_using_refiner_);
                        break;
                    case IndexType::IVF:
                        holder->query.target_.query_params_ = std::make_shared<IVFQueryParams>(10, holder->is_using_refiner_);
                        holder->query.target_.query_params_->set_radius(holder->radius_);
                        holder->query.target_.query_params_->set_is_linear(holder->is_linear_);
                        break;
                    case IndexType::FLAT:
                        holder->query.target_.query_params_ = std::make_shared<FlatQueryParams>(holder->is_using_refiner_);
                        holder->query.target_.query_params_->set_radius(holder->radius_);
                        holder->query.target_.query_params_->set_is_linear(holder->is_linear_);
                        break;
                    case IndexType::HNSW_RABITQ:
                        holder->query.target_.query_params_ = std::make_shared<HnswRabitqQueryParams>(200, holder->radius_, holder->is_linear_, holder->is_using_refiner_);
                        break;
                    case IndexType::IVF_RABITQ:
                        holder->query.target_.query_params_ = std::make_shared<IvfRabitqQueryParams>(10, holder->radius_, holder->is_linear_, holder->is_using_refiner_);
                        break;
                    case IndexType::VAMANA:
                        holder->query.target_.query_params_ = std::make_shared<VamanaQueryParams>(200, holder->radius_, holder->is_linear_, holder->is_using_refiner_);
                        break;
                    default:
                        holder->query.target_.query_params_ = std::make_shared<HnswQueryParams>(200, holder->radius_, holder->is_linear_, holder->is_using_refiner_);
                        break;
                }
            }
        }
    }

    auto res = c->group_by_query(holder->query);
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

// --- Fetch ---

static void normalize_nullable_fields_for_fetch(const CollectionSchema& schema, DocPtrMap& doc_map) {
    std::vector<std::string> nullable_fields;
    nullable_fields.reserve(schema.fields().size());
    for (const auto& field : schema.fields()) {
        if (field && field->nullable()) {
            nullable_fields.push_back(field->name());
        }
    }
    if (nullable_fields.empty()) {
        return;
    }
    for (auto& [_, doc_ptr] : doc_map) {
        if (!doc_ptr) {
            continue;
        }
        for (const auto& field_name : nullable_fields) {
            if (!doc_ptr->has(field_name)) {
                doc_ptr->set_null(field_name);
            }
        }
    }
}

zvec_status_t zvec_collection_fetch(zvec_collection_t coll, const char** pks, int count,
                                     const char** output_fields, int output_field_count,
                                     int include_vector,
                                     zvec_query_result_t* result) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    std::vector<std::string> pk_vec;
    pk_vec.reserve(count);
    for (int i = 0; i < count; i++) {
        pk_vec.emplace_back(pks[i]);
    }
    std::optional<std::vector<std::string>> cpp_output_fields;
    if (output_fields && output_field_count > 0) {
        std::vector<std::string> fields;
        fields.reserve(output_field_count);
        for (int i = 0; i < output_field_count; i++) {
            if (!output_fields[i]) {
                zvec_status_t st = {1, "null output field"};
                SET_FFI_ERROR(st);
                return st;
            }
            fields.emplace_back(output_fields[i]);
        }
        cpp_output_fields = std::move(fields);
    }
    auto res = c->fetch(pk_vec, cpp_output_fields, (bool)include_vector);
    if (!res.has_value()) {
        result->docs = nullptr;
        result->count = 0;
        return MAKE_STATUS(res.error());
    }
    auto& doc_map = res.value();
    auto schema_res = c->schema();
    if (schema_res.has_value()) {
        normalize_nullable_fields_for_fetch(schema_res.value(), doc_map);
    }
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

// --- Doc iterator ---

namespace {
struct DocIteratorHolder {
    // Declaration order is destruction order reversed, so `iterator` is
    // released first -- it hands its slot back to the collection -- and
    // `collection` last. Upstream requires the collection to outlive its
    // iterators (create_iterator's release_slot captures the raw pointer), and
    // ~CollectionImpl blocks until every iterator is closed, which would
    // deadlock a single-threaded PHP process. Keeping our own shared_ptr copy
    // also means zvec_collection_free() on the PHP side cannot pull the
    // collection out from under a live iterator.
    std::shared_ptr<Collection> collection;
    std::vector<std::string> nullable_fields;  // requested nullable fields only
    DocIterator::Ptr iterator;
};
}  // namespace

zvec_status_t zvec_collection_create_iterator(zvec_collection_t coll,
                                              int has_output_fields,
                                              const char** output_fields,
                                              int output_field_count,
                                              int include_vector,
                                              zvec_doc_iterator_t* out) {
    if (!out) {
        zvec_status_t st = {1, "null out pointer"};
        SET_FFI_ERROR(st);
        return st;
    }
    *out = nullptr;
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    if (output_field_count < 0 || (output_field_count > 0 && !output_fields)) {
        zvec_status_t st = {1, "invalid output fields"};
        SET_FFI_ERROR(st);
        return st;
    }

    // Copy the collection out of the registry so the iterator owns a reference.
    std::shared_ptr<Collection> owner;
    {
        std::unique_lock lock(g_collections_mutex);
        auto& reg = collections_registry();
        auto it = reg.find(static_cast<Collection*>(coll));
        if (it == reg.end()) {
            zvec_status_t st = {1, "collection handle is not open"};
            SET_FFI_ERROR(st);
            return st;
        }
        owner = it->second;
    }

    IteratorOptions opts;
    opts.include_vector_ = include_vector != 0;
    if (has_output_fields) {
        std::vector<std::string> fields;
        fields.reserve(output_field_count);
        for (int i = 0; i < output_field_count; i++) {
            if (!output_fields[i]) {
                zvec_status_t st = {1, "null output field"};
                SET_FFI_ERROR(st);
                return st;
            }
            fields.emplace_back(output_fields[i]);
        }
        // Assigned even when empty: upstream reads an empty vector as "PK only".
        opts.output_fields_ = std::move(fields);
    }

    // Since #192 fetch() reports an absent nullable field as present-and-null.
    // Iterate consistently, but only for the fields actually requested -- with
    // outputFields: ['id'], hasField('weight') must stay false.
    std::vector<std::string> nullable_fields;
    auto schema_res = owner->schema();
    if (schema_res.has_value()) {
        for (const auto& field : schema_res.value().fields()) {
            if (field && field->nullable()) {
                if (!has_output_fields) {
                    nullable_fields.push_back(field->name());
                } else if (opts.output_fields_ &&
                           std::find(opts.output_fields_->begin(), opts.output_fields_->end(), field->name()) !=
                               opts.output_fields_->end()) {
                    nullable_fields.push_back(field->name());
                }
            }
        }
    }

    auto res = owner->create_iterator(opts);
    if (!res.has_value()) {
        return MAKE_STATUS(res.error());
    }
    auto* h = new DocIteratorHolder{std::move(owner), std::move(nullable_fields), std::move(res).value()};
    *out = static_cast<zvec_doc_iterator_t>(h);
    return ok_status();
}

zvec_status_t zvec_doc_iterator_next(zvec_doc_iterator_t it, zvec_doc_t* out_doc) {
    if (!out_doc) {
        zvec_status_t st = {1, "null out pointer"};
        SET_FFI_ERROR(st);
        return st;
    }
    *out_doc = nullptr;
    if (!it) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* h = static_cast<DocIteratorHolder*>(it);
    auto res = h->iterator->next();
    if (!res.has_value()) {
        return MAKE_STATUS(res.error());
    }
    if (!res.value()) {
        return ok_status();  // end of iteration
    }
    // Copy the doc: PHP frees documents with zvec_doc_free, which deletes a
    // Doc*, so the shared_ptr cannot be handed out.
    auto* doc = new Doc(*res.value());
    for (const auto& name : h->nullable_fields) {
        if (!doc->has(name)) {
            doc->set_null(name);
        }
    }
    *out_doc = static_cast<zvec_doc_t>(doc);
    return ok_status();
}

void zvec_doc_iterator_free(zvec_doc_iterator_t it) {
    if (!it) return;
    delete static_cast<DocIteratorHolder*>(it);
}

// --- Query ---

zvec_status_t zvec_collection_query(zvec_collection_t coll, const char* field_name,
                                     const float* query_vector, uint32_t dim,
                                     int topk, int include_vector,
                                     const char* filter,
                                     zvec_query_result_t* result) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    SearchQuery query;
    query.topk_ = topk;
    query.target_.field_name_ = field_name;
    query.include_vector_ = (bool)include_vector;
    query.target_.set_vector(std::string(reinterpret_cast<const char*>(query_vector), dim * sizeof(float)));
    if (filter && filter[0] != '\0') {
        query.filter_ = filter;
    }

    auto res = c->query(query);
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
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    
    std::vector<ailego::Float16> fp16_vec;
    fp16_vec.reserve(dim);
    for (uint32_t i = 0; i < dim; i++) {
        fp16_vec.push_back(ailego::FloatHelper::ToFP32(query_vector[i]));
    }
    
    SearchQuery query;
    query.topk_ = topk;
    query.target_.field_name_ = field_name;
    query.include_vector_ = (bool)include_vector;
    query.target_.set_vector(std::string(reinterpret_cast<const char*>(fp16_vec.data()), dim * sizeof(ailego::Float16)));
    if (filter && filter[0] != '\0') {
        query.filter_ = filter;
    }

    auto res = c->query(query);
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
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);

    std::vector<double> fp64_vec(query_vector, query_vector + dim);

    SearchQuery query;
    query.topk_ = topk;
    query.target_.field_name_ = field_name;
    query.include_vector_ = (bool)include_vector;
    query.target_.set_vector(std::string(reinterpret_cast<const char*>(fp64_vec.data()), dim * sizeof(double)));
    if (filter && filter[0] != '\0') {
        query.filter_ = filter;
    }

    auto res = c->query(query);
    if (!res.has_value()) {
        result->docs = nullptr;
        result->count = 0;
        return MAKE_STATUS(res.error());
    }

    fill_doc_list(res.value(), result);
    return ok_status();
}

static void apply_output_fields(SearchQuery& query, const char** output_fields, int count) {
    if (output_fields && count > 0) {
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
    
    auto schema_res = c->schema();
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
        case 5: expected_index_type = IndexType::VAMANA; break;
        case 7: expected_index_type = IndexType::IVF_RABITQ; break;
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

static void apply_query_params(SearchQuery& query, int type, int hnsw_ef, int ivf_nprobe,
                                  float radius, int is_linear, int is_using_refiner) {
    if (type == 1) {
        query.target_.query_params_ = std::make_shared<HnswQueryParams>(
            hnsw_ef, radius, (bool)is_linear, (bool)is_using_refiner);
    } else if (type == 2) {
        auto params = std::make_shared<IVFQueryParams>(ivf_nprobe, (bool)is_using_refiner);
        params->set_radius(radius);
        params->set_is_linear((bool)is_linear);
        query.target_.query_params_ = params;
    } else if (type == 3) {
        auto params = std::make_shared<FlatQueryParams>((bool)is_using_refiner);
        params->set_radius(radius);
        params->set_is_linear((bool)is_linear);
        query.target_.query_params_ = params;
    } else if (type == 4) {
        query.target_.query_params_ = std::make_shared<HnswRabitqQueryParams>(
            hnsw_ef, radius, (bool)is_linear, (bool)is_using_refiner);
    } else if (type == 5) {
        query.target_.query_params_ = std::make_shared<VamanaQueryParams>(
            hnsw_ef, radius, (bool)is_linear, (bool)is_using_refiner);
    } else if (type == 7) {
        // The legacy path has no dedicated nprobe argument for IVF_RABITQ, so it
        // reuses the one IVF already has.
        query.target_.query_params_ = std::make_shared<IvfRabitqQueryParams>(
            ivf_nprobe, radius, (bool)is_linear, (bool)is_using_refiner);
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
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    
    // Validate query_param_type against actual index type
    auto validation_status = validate_query_param_type(c, field_name, query_param_type);
    if (validation_status.code != 0) {
        result->docs = nullptr;
        result->count = 0;
        return validation_status;
    }
    
    SearchQuery query;
    query.topk_ = topk;
    query.target_.field_name_ = field_name;
    query.include_vector_ = (bool)include_vector;
    query.target_.set_vector(std::string(reinterpret_cast<const char*>(query_vector), dim * sizeof(float)));
    if (filter && filter[0] != '\0') {
        query.filter_ = filter;
    }
    apply_output_fields(query, output_fields, output_fields_count);
    apply_query_params(query, query_param_type, hnsw_ef, ivf_nprobe, radius, is_linear, is_using_refiner);

    auto res = c->query(query);
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
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);

    auto validation_status = validate_query_param_type(c, field_name, query_param_type);
    if (validation_status.code != 0) {
        result->docs = nullptr;
        result->count = 0;
        return validation_status;
    }

    std::vector<double> fp64_vec(query_vector, query_vector + dim);

    SearchQuery query;
    query.topk_ = topk;
    query.target_.field_name_ = field_name;
    query.include_vector_ = (bool)include_vector;
    query.target_.set_vector(std::string(reinterpret_cast<const char*>(fp64_vec.data()), dim * sizeof(double)));
    if (filter && filter[0] != '\0') {
        query.filter_ = filter;
    }
    apply_output_fields(query, output_fields, output_fields_count);
    apply_query_params(query, query_param_type, hnsw_ef, ivf_nprobe, radius, is_linear, is_using_refiner);

    auto res = c->query(query);
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
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    SearchQuery query;
    query.topk_ = topk;
    query.filter_ = filter;

    auto res = c->query(query);
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
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    SearchQuery query;
    query.topk_ = topk;
    query.filter_ = filter;
    apply_output_fields(query, output_fields, output_fields_count);

    auto res = c->query(query);
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
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    
    // Validate query_param_type against actual index type
    auto validation_status = validate_query_param_type(c, field_name, query_param_type);
    if (validation_status.code != 0) {
        result->groups = nullptr;
        result->count = 0;
        return validation_status;
    }
    
    GroupByVectorQuery query;
    query.target_.field_name_ = field_name;
    query.target_.set_vector(std::string(reinterpret_cast<const char*>(query_vector), dim * sizeof(float)));
    query.group_by_field_name_ = group_by_field;
    query.group_count_ = group_count;
    query.topk_per_group_ = group_topk;
    query.include_vector_ = (bool)include_vector;
    if (filter && filter[0] != '\0') {
        query.filter_ = filter;
    }
    if (output_fields && output_fields_count > 0) {
        std::vector<std::string> fields;
        fields.reserve(output_fields_count);
        for (int i = 0; i < output_fields_count; i++) {
            fields.emplace_back(output_fields[i]);
        }
        query.output_fields_ = std::move(fields);
    }
    if (query_param_type == 1) {
        query.target_.query_params_ = std::make_shared<HnswQueryParams>(
            hnsw_ef, radius, (bool)is_linear, (bool)is_using_refiner);
    } else if (query_param_type == 2) {
        auto params = std::make_shared<IVFQueryParams>(ivf_nprobe, (bool)is_using_refiner);
        params->set_radius(radius);
        params->set_is_linear((bool)is_linear);
        query.target_.query_params_ = params;
    } else if (query_param_type == 3) {
        auto params = std::make_shared<FlatQueryParams>((bool)is_using_refiner);
        params->set_radius(radius);
        params->set_is_linear((bool)is_linear);
        query.target_.query_params_ = params;
    } else if (query_param_type == 5) {
        query.target_.query_params_ = std::make_shared<VamanaQueryParams>(
            hnsw_ef, radius, (bool)is_linear, (bool)is_using_refiner);
    }

    auto res = c->group_by_query(query);
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
    if (!result) return;
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
    if (!result) return;
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
    if (!result) return;
    if (result->docs) {
        delete[] result->docs;
        result->docs = nullptr;
        result->count = 0;
    }
}

// --- CollectionStats struct ---

struct CollectionStatsHolder {
    zvec::CollectionStats stats;
    // Thread-local buffers for index names
    std::vector<std::string> index_names;
};

static CollectionStatsHolder* stats_holder_from_coll(Collection* c) {
    auto res = c->stats();
    if (!res.has_value()) {
        return nullptr;
    }
    auto* holder = new CollectionStatsHolder();
    holder->stats = std::move(res.value());
    // Extract index names into vector for indexed access
    for (const auto& pair : holder->stats.index_completeness) {
        holder->index_names.push_back(pair.first);
    }
    return holder;
}

zvec_status_t zvec_collection_get_stats_struct(zvec_collection_t coll, zvec_collection_stats_t* out) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    auto* holder = stats_holder_from_coll(c);
    if (!holder) {
        *out = nullptr;
        auto res = c->stats();
        if (!res.has_value()) {
            return MAKE_STATUS(res.error());
        }
        return MAKE_STATUS(Status(StatusCode::INTERNAL_ERROR, "Failed to create stats holder"));
    }
    *out = static_cast<zvec_collection_stats_t>(holder);
    return ok_status();
}

void zvec_collection_stats_free(zvec_collection_stats_t stats) {
    if (!stats) return;
    delete static_cast<CollectionStatsHolder*>(stats);
}

uint64_t zvec_collection_stats_get_doc_count(zvec_collection_stats_t stats) {
    if (!stats) return 0ULL;
    auto* holder = static_cast<CollectionStatsHolder*>(stats);
    return holder->stats.doc_count;
}

uint32_t zvec_collection_stats_get_index_count(zvec_collection_stats_t stats) {
    if (!stats) return 0U;
    auto* holder = static_cast<CollectionStatsHolder*>(stats);
    return static_cast<uint32_t>(holder->index_names.size());
}

const char* zvec_collection_stats_get_index_name(zvec_collection_stats_t stats, uint32_t index) {
    if (!stats) return nullptr;
    auto* holder = static_cast<CollectionStatsHolder*>(stats);
    if (index >= holder->index_names.size()) return nullptr;
    return holder->index_names[index].c_str();
}

float zvec_collection_stats_get_index_completeness(zvec_collection_stats_t stats, uint32_t index) {
    if (!stats) return 0.0f;
    auto* holder = static_cast<CollectionStatsHolder*>(stats);
    if (index >= holder->index_names.size()) return 0.0f;
    const auto& name = holder->index_names[index];
    auto it = holder->stats.index_completeness.find(name);
    if (it != holder->stats.index_completeness.end()) {
        return it->second;
    }
    return 0.0f;
}

zvec_status_t zvec_collection_stats(zvec_collection_t coll, char* buf, size_t buf_size) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    auto res = c->stats();
    if (!res.has_value()) {
        return MAKE_STATUS(res.error());
    }
    auto str = res.value().to_string();
    strncpy(buf, str.c_str(), buf_size - 1);
    buf[buf_size - 1] = '\0';
    return ok_status();
}

// --- FieldSchema introspection ---

struct FieldSchemaHolder {
    std::string name;
    DataType data_type;
    uint32_t dimension;
    bool nullable;
    IndexType index_type;
    bool has_index;
};

zvec_status_t zvec_collection_get_field_schema(zvec_collection_t coll, const char* field_name, zvec_field_schema_t* out) {
    if (!coll) {
        zvec_status_t st = {1, "null handle"};
        SET_FFI_ERROR(st);
        return st;
    }
    auto* c = static_cast<Collection*>(coll);
    auto schema_res = c->schema();
    if (!schema_res.has_value()) {
        *out = nullptr;
        return MAKE_STATUS(schema_res.error());
    }
    
    const auto& schema = schema_res.value();
    const FieldSchema* field = schema.get_field(field_name);
    if (!field) {
        *out = nullptr;
        zvec_status_t st;
        st.code = static_cast<int>(StatusCode::NOT_FOUND);
        std::string msg = std::string("Field not found: ") + field_name;
        strncpy(st.message, msg.c_str(), sizeof(st.message) - 1);
        st.message[sizeof(st.message) - 1] = '\0';
        SET_FFI_ERROR(st);
        return st;
    }
    
    auto* holder = new FieldSchemaHolder();
    holder->name = field->name();
    holder->data_type = field->data_type();
    holder->dimension = field->dimension();
    holder->nullable = field->nullable();
    holder->index_type = field->index_type();
    holder->has_index = field->index_type() != IndexType::UNDEFINED;
    
    *out = static_cast<zvec_field_schema_t>(holder);
    return ok_status();
}

void zvec_field_schema_free(zvec_field_schema_t schema) {
    if (!schema) return;
    delete static_cast<FieldSchemaHolder*>(schema);
}

const char* zvec_field_schema_get_name(zvec_field_schema_t schema) {
    if (!schema) return nullptr;
    return static_cast<FieldSchemaHolder*>(schema)->name.c_str();
}

static thread_local std::string g_elem_data_type_name_buf;

int zvec_field_schema_get_data_type(zvec_field_schema_t schema) {
    if (!schema) return 0;
    return static_cast<int>(static_cast<FieldSchemaHolder*>(schema)->data_type);
}

int zvec_field_schema_get_element_data_type(zvec_field_schema_t schema) {
    if (!schema) return 0;
    auto* h = static_cast<FieldSchemaHolder*>(schema);
    return static_cast<int>(FieldSchema::get_element_data_type(h->data_type));
}

size_t zvec_field_schema_get_element_data_size(zvec_field_schema_t schema) {
    if (!schema) return 0;
    auto* h = static_cast<FieldSchemaHolder*>(schema);
    return FieldSchema::get_element_data_size(h->data_type);
}

uint32_t zvec_field_schema_get_dimension(zvec_field_schema_t schema) {
    if (!schema) return 0U;
    return static_cast<FieldSchemaHolder*>(schema)->dimension;
}

int zvec_field_schema_is_vector_field(zvec_field_schema_t schema) {
    if (!schema) return 0;
    auto* h = static_cast<FieldSchemaHolder*>(schema);
    return FieldSchema::is_vector_field(h->data_type) ? 1 : 0;
}

int zvec_field_schema_is_dense_vector(zvec_field_schema_t schema) {
    if (!schema) return 0;
    auto* h = static_cast<FieldSchemaHolder*>(schema);
    return FieldSchema::is_dense_vector_field(h->data_type) ? 1 : 0;
}

int zvec_field_schema_is_sparse_vector(zvec_field_schema_t schema) {
    if (!schema) return 0;
    auto* h = static_cast<FieldSchemaHolder*>(schema);
    return FieldSchema::is_sparse_vector_field(h->data_type) ? 1 : 0;
}

int zvec_field_schema_is_array_type(zvec_field_schema_t schema) {
    if (!schema) return 0;
    auto* h = static_cast<FieldSchemaHolder*>(schema);
    return h->data_type >= DataType::ARRAY_BINARY && h->data_type <= DataType::ARRAY_DOUBLE ? 1 : 0;
}

int zvec_field_schema_is_nullable(zvec_field_schema_t schema) {
    if (!schema) return 0;
    return static_cast<FieldSchemaHolder*>(schema)->nullable ? 1 : 0;
}

int zvec_field_schema_has_invert_index(zvec_field_schema_t schema) {
    if (!schema) return 0;
    auto* h = static_cast<FieldSchemaHolder*>(schema);
    return (!FieldSchema::is_vector_field(h->data_type) && h->has_index) ? 1 : 0;
}

int zvec_field_schema_has_index(zvec_field_schema_t schema) {
    if (!schema) return 0;
    return static_cast<FieldSchemaHolder*>(schema)->has_index ? 1 : 0;
}

int zvec_field_schema_get_index_type(zvec_field_schema_t schema) {
    if (!schema) return 0;
    return from_index_type(static_cast<FieldSchemaHolder*>(schema)->index_type);
}