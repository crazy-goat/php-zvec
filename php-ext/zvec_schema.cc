#include "zvec_schema.h"
#include "zvec_exception.h"
#include <zvec/db/schema.h>
#include <zvec/db/index_params.h>
#include <string>

using namespace zvec;

zend_class_entry *zvec_schema_ce = nullptr;
static zend_object_handlers zvec_schema_handlers;

static zend_object *zvec_schema_create_object_handler(zend_class_entry *ce) {
    auto *intern = static_cast<zvec_schema_object *>(
        ecalloc(1, sizeof(zvec_schema_object) + zend_object_properties_size(ce)));
    intern->schema = nullptr;
    zend_object_std_init(&intern->std, ce);
    object_properties_init(&intern->std, ce);
    intern->std.handlers = &zvec_schema_handlers;
    return &intern->std;
}

static void zvec_schema_free_object(zend_object *obj) {
    auto *intern = zvec_schema_from_obj(obj);
    if (intern->schema) {
        delete intern->schema;
    }
    intern->schema = nullptr;
    zend_object_std_dtor(obj);
}

CollectionSchema *zvec_schema_get_native(zval *zv) {
    return Z_ZVEC_SCHEMA_P(zv)->schema;
}

static MetricType to_metric_type(uint32_t v) {
    switch (v) {
        case 1: return MetricType::L2;
        case 2: return MetricType::IP;
        case 3: return MetricType::COSINE;
        default: return MetricType::IP;
    }
}

PHP_METHOD(ZVecSchema, __construct) {
    char *name; size_t name_len;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING(name, name_len)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_SCHEMA_P(ZEND_THIS);
    intern->schema = new CollectionSchema(std::string(name, name_len));
}

PHP_METHOD(ZVecSchema, setMaxDocCountPerSegment) {
    zend_long count;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_LONG(count)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_SCHEMA_P(ZEND_THIS);
    intern->schema->set_max_doc_count_per_segment(static_cast<uint64_t>(count));
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecSchema, addInt64) {
    char *name; size_t name_len;
    zend_bool nullable = 0, with_invert_index = 0;
    ZEND_PARSE_PARAMETERS_START(1, 3)
        Z_PARAM_STRING(name, name_len)
        Z_PARAM_OPTIONAL
        Z_PARAM_BOOL(nullable)
        Z_PARAM_BOOL(with_invert_index)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_SCHEMA_P(ZEND_THIS);
    if (with_invert_index) {
        intern->schema->add_field(std::make_shared<FieldSchema>(
            std::string(name, name_len), DataType::INT64, (bool)nullable,
            std::make_shared<InvertIndexParams>(true)));
    } else {
        intern->schema->add_field(std::make_shared<FieldSchema>(
            std::string(name, name_len), DataType::INT64, (bool)nullable));
    }
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecSchema, addString) {
    char *name; size_t name_len;
    zend_bool nullable = 0, with_invert_index = 0;
    ZEND_PARSE_PARAMETERS_START(1, 3)
        Z_PARAM_STRING(name, name_len)
        Z_PARAM_OPTIONAL
        Z_PARAM_BOOL(nullable)
        Z_PARAM_BOOL(with_invert_index)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_SCHEMA_P(ZEND_THIS);
    if (with_invert_index) {
        intern->schema->add_field(std::make_shared<FieldSchema>(
            std::string(name, name_len), DataType::STRING, (bool)nullable,
            std::make_shared<InvertIndexParams>(false)));
    } else {
        intern->schema->add_field(std::make_shared<FieldSchema>(
            std::string(name, name_len), DataType::STRING, (bool)nullable));
    }
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecSchema, addFloat) {
    char *name; size_t name_len;
    zend_bool nullable = 1;
    ZEND_PARSE_PARAMETERS_START(1, 2)
        Z_PARAM_STRING(name, name_len)
        Z_PARAM_OPTIONAL
        Z_PARAM_BOOL(nullable)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_SCHEMA_P(ZEND_THIS);
    intern->schema->add_field(std::make_shared<FieldSchema>(
        std::string(name, name_len), DataType::FLOAT, (bool)nullable));
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecSchema, addDouble) {
    char *name; size_t name_len;
    zend_bool nullable = 1;
    ZEND_PARSE_PARAMETERS_START(1, 2)
        Z_PARAM_STRING(name, name_len)
        Z_PARAM_OPTIONAL
        Z_PARAM_BOOL(nullable)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_SCHEMA_P(ZEND_THIS);
    intern->schema->add_field(std::make_shared<FieldSchema>(
        std::string(name, name_len), DataType::DOUBLE, (bool)nullable));
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecSchema, addBool) {
    char *name; size_t name_len;
    zend_bool nullable = 0, with_invert_index = 0;
    ZEND_PARSE_PARAMETERS_START(1, 3)
        Z_PARAM_STRING(name, name_len)
        Z_PARAM_OPTIONAL
        Z_PARAM_BOOL(nullable)
        Z_PARAM_BOOL(with_invert_index)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_SCHEMA_P(ZEND_THIS);
    if (with_invert_index) {
        intern->schema->add_field(std::make_shared<FieldSchema>(
            std::string(name, name_len), DataType::BOOL, (bool)nullable,
            std::make_shared<InvertIndexParams>(true)));
    } else {
        intern->schema->add_field(std::make_shared<FieldSchema>(
            std::string(name, name_len), DataType::BOOL, (bool)nullable));
    }
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecSchema, addInt32) {
    char *name; size_t name_len;
    zend_bool nullable = 0, with_invert_index = 0;
    ZEND_PARSE_PARAMETERS_START(1, 3)
        Z_PARAM_STRING(name, name_len)
        Z_PARAM_OPTIONAL
        Z_PARAM_BOOL(nullable)
        Z_PARAM_BOOL(with_invert_index)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_SCHEMA_P(ZEND_THIS);
    if (with_invert_index) {
        intern->schema->add_field(std::make_shared<FieldSchema>(
            std::string(name, name_len), DataType::INT32, (bool)nullable,
            std::make_shared<InvertIndexParams>(true)));
    } else {
        intern->schema->add_field(std::make_shared<FieldSchema>(
            std::string(name, name_len), DataType::INT32, (bool)nullable));
    }
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecSchema, addUint32) {
    char *name; size_t name_len;
    zend_bool nullable = 0, with_invert_index = 0;
    ZEND_PARSE_PARAMETERS_START(1, 3)
        Z_PARAM_STRING(name, name_len)
        Z_PARAM_OPTIONAL
        Z_PARAM_BOOL(nullable)
        Z_PARAM_BOOL(with_invert_index)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_SCHEMA_P(ZEND_THIS);
    if (with_invert_index) {
        intern->schema->add_field(std::make_shared<FieldSchema>(
            std::string(name, name_len), DataType::UINT32, (bool)nullable,
            std::make_shared<InvertIndexParams>(true)));
    } else {
        intern->schema->add_field(std::make_shared<FieldSchema>(
            std::string(name, name_len), DataType::UINT32, (bool)nullable));
    }
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecSchema, addUint64) {
    char *name; size_t name_len;
    zend_bool nullable = 0, with_invert_index = 0;
    ZEND_PARSE_PARAMETERS_START(1, 3)
        Z_PARAM_STRING(name, name_len)
        Z_PARAM_OPTIONAL
        Z_PARAM_BOOL(nullable)
        Z_PARAM_BOOL(with_invert_index)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_SCHEMA_P(ZEND_THIS);
    if (with_invert_index) {
        intern->schema->add_field(std::make_shared<FieldSchema>(
            std::string(name, name_len), DataType::UINT64, (bool)nullable,
            std::make_shared<InvertIndexParams>(true)));
    } else {
        intern->schema->add_field(std::make_shared<FieldSchema>(
            std::string(name, name_len), DataType::UINT64, (bool)nullable));
    }
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecSchema, addVectorFp32) {
    char *name; size_t name_len;
    zend_long dimension, metric_type = 2;
    ZEND_PARSE_PARAMETERS_START(2, 3)
        Z_PARAM_STRING(name, name_len)
        Z_PARAM_LONG(dimension)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(metric_type)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_SCHEMA_P(ZEND_THIS);
    intern->schema->add_field(std::make_shared<FieldSchema>(
        std::string(name, name_len), DataType::VECTOR_FP32,
        static_cast<uint32_t>(dimension), false,
        std::make_shared<HnswIndexParams>(to_metric_type(static_cast<uint32_t>(metric_type)))));
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecSchema, addVectorInt8) {
    char *name; size_t name_len;
    zend_long dimension, metric_type = 2;
    ZEND_PARSE_PARAMETERS_START(2, 3)
        Z_PARAM_STRING(name, name_len)
        Z_PARAM_LONG(dimension)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(metric_type)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_SCHEMA_P(ZEND_THIS);
    intern->schema->add_field(std::make_shared<FieldSchema>(
        std::string(name, name_len), DataType::VECTOR_INT8,
        static_cast<uint32_t>(dimension), false,
        std::make_shared<HnswIndexParams>(to_metric_type(static_cast<uint32_t>(metric_type)))));
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecSchema, addVectorFp16) {
    char *name; size_t name_len;
    zend_long dimension, metric_type = 2;
    ZEND_PARSE_PARAMETERS_START(2, 3)
        Z_PARAM_STRING(name, name_len)
        Z_PARAM_LONG(dimension)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(metric_type)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_SCHEMA_P(ZEND_THIS);
    intern->schema->add_field(std::make_shared<FieldSchema>(
        std::string(name, name_len), DataType::VECTOR_FP16,
        static_cast<uint32_t>(dimension), false,
        std::make_shared<HnswIndexParams>(to_metric_type(static_cast<uint32_t>(metric_type)))));
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecSchema, addSparseVectorFp32) {
    char *name; size_t name_len;
    zend_long metric_type = 2;
    ZEND_PARSE_PARAMETERS_START(1, 2)
        Z_PARAM_STRING(name, name_len)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(metric_type)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_SCHEMA_P(ZEND_THIS);
    intern->schema->add_field(std::make_shared<FieldSchema>(
        std::string(name, name_len), DataType::SPARSE_VECTOR_FP32, 0, false,
        std::make_shared<HnswIndexParams>(to_metric_type(static_cast<uint32_t>(metric_type)))));
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

ZEND_BEGIN_ARG_INFO_EX(arginfo_zvec_schema___construct, 0, 0, 1)
    ZEND_ARG_TYPE_INFO(0, name, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_zvec_schema_fluent, 0, 1, ZVecSchema, 0)
    ZEND_ARG_INFO(0, value)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_zvec_schema_add_field, 0, 1, ZVecSchema, 0)
    ZEND_ARG_TYPE_INFO(0, name, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, nullable, _IS_BOOL, 0, "false")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, withInvertIndex, _IS_BOOL, 0, "false")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_zvec_schema_add_float, 0, 1, ZVecSchema, 0)
    ZEND_ARG_TYPE_INFO(0, name, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, nullable, _IS_BOOL, 0, "true")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_zvec_schema_add_vector, 0, 2, ZVecSchema, 0)
    ZEND_ARG_TYPE_INFO(0, name, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO(0, dimension, IS_LONG, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, metricType, IS_LONG, 0, "2")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_zvec_schema_add_sparse, 0, 1, ZVecSchema, 0)
    ZEND_ARG_TYPE_INFO(0, name, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, metricType, IS_LONG, 0, "2")
ZEND_END_ARG_INFO()

static const zend_function_entry zvec_schema_methods[] = {
    PHP_ME(ZVecSchema, __construct, arginfo_zvec_schema___construct, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecSchema, setMaxDocCountPerSegment, arginfo_zvec_schema_fluent, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecSchema, addInt64, arginfo_zvec_schema_add_field, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecSchema, addString, arginfo_zvec_schema_add_field, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecSchema, addFloat, arginfo_zvec_schema_add_float, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecSchema, addDouble, arginfo_zvec_schema_add_float, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecSchema, addBool, arginfo_zvec_schema_add_field, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecSchema, addInt32, arginfo_zvec_schema_add_field, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecSchema, addUint32, arginfo_zvec_schema_add_field, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecSchema, addUint64, arginfo_zvec_schema_add_field, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecSchema, addVectorFp32, arginfo_zvec_schema_add_vector, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecSchema, addVectorInt8, arginfo_zvec_schema_add_vector, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecSchema, addVectorFp16, arginfo_zvec_schema_add_vector, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecSchema, addSparseVectorFp32, arginfo_zvec_schema_add_sparse, ZEND_ACC_PUBLIC)
    PHP_FE_END
};

void zvec_register_schema(INIT_FUNC_ARGS) {
    zend_class_entry ce;
    INIT_CLASS_ENTRY(ce, "ZVecSchema", zvec_schema_methods);
    zvec_schema_ce = zend_register_internal_class(&ce);
    zvec_schema_ce->create_object = zvec_schema_create_object_handler;

    memcpy(&zvec_schema_handlers, &std_object_handlers, sizeof(zend_object_handlers));
    zvec_schema_handlers.offset = XtOffsetOf(zvec_schema_object, std);
    zvec_schema_handlers.free_obj = zvec_schema_free_object;

    zend_declare_class_constant_long(zvec_schema_ce, "METRIC_L2", sizeof("METRIC_L2") - 1, 1);
    zend_declare_class_constant_long(zvec_schema_ce, "METRIC_IP", sizeof("METRIC_IP") - 1, 2);
    zend_declare_class_constant_long(zvec_schema_ce, "METRIC_COSINE", sizeof("METRIC_COSINE") - 1, 3);
}
