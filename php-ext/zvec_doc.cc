#include "zvec_doc.h"
#include "zvec_exception.h"
#include <zvec/db/doc.h>
#include <zvec/ailego/utility/float_helper.h>
#include <algorithm>
#include <vector>
#include <string>
#include <cstring>

using namespace zvec;

zend_class_entry *zvec_doc_ce = nullptr;
static zend_object_handlers zvec_doc_handlers;

static zend_object *zvec_doc_create_object_handler(zend_class_entry *ce) {
    auto *intern = static_cast<zvec_doc_object *>(
        ecalloc(1, sizeof(zvec_doc_object) + zend_object_properties_size(ce)));
    intern->doc = nullptr;
    intern->owns_handle = true;
    zend_object_std_init(&intern->std, ce);
    object_properties_init(&intern->std, ce);
    intern->std.handlers = &zvec_doc_handlers;
    return &intern->std;
}

static void zvec_doc_free_object(zend_object *obj) {
    auto *intern = zvec_doc_from_obj(obj);
    if (intern->doc && intern->owns_handle) {
        delete intern->doc;
    }
    intern->doc = nullptr;
    zend_object_std_dtor(obj);
}

zend_object *zvec_doc_create_from_native(Doc *doc, bool owns) {
    zval zv;
    object_init_ex(&zv, zvec_doc_ce);
    auto *intern = Z_ZVEC_DOC_P(&zv);
    intern->doc = doc;
    intern->owns_handle = owns;
    return Z_OBJ(zv);
}

PHP_METHOD(ZVecDoc, __construct) {
    char *pk;
    size_t pk_len;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING(pk, pk_len)
    ZEND_PARSE_PARAMETERS_END();

    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    intern->doc = new Doc();
    intern->doc->set_pk(std::string(pk, pk_len));
    intern->owns_handle = true;
}

PHP_METHOD(ZVecDoc, setInt64) {
    char *field; size_t field_len;
    zend_long value;
    ZEND_PARSE_PARAMETERS_START(2, 2)
        Z_PARAM_STRING(field, field_len)
        Z_PARAM_LONG(value)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    intern->doc->set<int64_t>(std::string(field, field_len), static_cast<int64_t>(value));
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecDoc, setString) {
    char *field; size_t field_len;
    char *value; size_t value_len;
    ZEND_PARSE_PARAMETERS_START(2, 2)
        Z_PARAM_STRING(field, field_len)
        Z_PARAM_STRING(value, value_len)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    intern->doc->set<std::string>(std::string(field, field_len), std::string(value, value_len));
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecDoc, setFloat) {
    char *field; size_t field_len;
    double value;
    ZEND_PARSE_PARAMETERS_START(2, 2)
        Z_PARAM_STRING(field, field_len)
        Z_PARAM_DOUBLE(value)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    intern->doc->set<float>(std::string(field, field_len), static_cast<float>(value));
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecDoc, setDouble) {
    char *field; size_t field_len;
    double value;
    ZEND_PARSE_PARAMETERS_START(2, 2)
        Z_PARAM_STRING(field, field_len)
        Z_PARAM_DOUBLE(value)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    intern->doc->set<double>(std::string(field, field_len), value);
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecDoc, setBool) {
    char *field; size_t field_len;
    zend_bool value;
    ZEND_PARSE_PARAMETERS_START(2, 2)
        Z_PARAM_STRING(field, field_len)
        Z_PARAM_BOOL(value)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    intern->doc->set<bool>(std::string(field, field_len), static_cast<bool>(value));
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecDoc, setInt32) {
    char *field; size_t field_len;
    zend_long value;
    ZEND_PARSE_PARAMETERS_START(2, 2)
        Z_PARAM_STRING(field, field_len)
        Z_PARAM_LONG(value)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    intern->doc->set<int32_t>(std::string(field, field_len), static_cast<int32_t>(value));
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecDoc, setUint32) {
    char *field; size_t field_len;
    zend_long value;
    ZEND_PARSE_PARAMETERS_START(2, 2)
        Z_PARAM_STRING(field, field_len)
        Z_PARAM_LONG(value)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    intern->doc->set<uint32_t>(std::string(field, field_len), static_cast<uint32_t>(value));
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecDoc, setUint64) {
    char *field; size_t field_len;
    zend_long value;
    ZEND_PARSE_PARAMETERS_START(2, 2)
        Z_PARAM_STRING(field, field_len)
        Z_PARAM_LONG(value)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    intern->doc->set<uint64_t>(std::string(field, field_len), static_cast<uint64_t>(value));
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecDoc, setVectorFp32) {
    char *field; size_t field_len;
    zval *arr;
    ZEND_PARSE_PARAMETERS_START(2, 2)
        Z_PARAM_STRING(field, field_len)
        Z_PARAM_ARRAY(arr)
    ZEND_PARSE_PARAMETERS_END();

    HashTable *ht = Z_ARRVAL_P(arr);
    uint32_t dim = zend_hash_num_elements(ht);
    std::vector<float> vec;
    vec.reserve(dim);
    zval *val;
    ZEND_HASH_FOREACH_VAL(ht, val) {
        vec.push_back(static_cast<float>(zval_get_double(val)));
    } ZEND_HASH_FOREACH_END();

    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    intern->doc->set<std::vector<float>>(std::string(field, field_len), std::move(vec));
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecDoc, setVectorInt8) {
    char *field; size_t field_len;
    zval *arr;
    ZEND_PARSE_PARAMETERS_START(2, 2)
        Z_PARAM_STRING(field, field_len)
        Z_PARAM_ARRAY(arr)
    ZEND_PARSE_PARAMETERS_END();

    HashTable *ht = Z_ARRVAL_P(arr);
    uint32_t dim = zend_hash_num_elements(ht);
    std::vector<int8_t> vec;
    vec.reserve(dim);
    zval *val;
    ZEND_HASH_FOREACH_VAL(ht, val) {
        vec.push_back(static_cast<int8_t>(zval_get_long(val)));
    } ZEND_HASH_FOREACH_END();

    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    intern->doc->set<std::vector<int8_t>>(std::string(field, field_len), std::move(vec));
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecDoc, setVectorFp16) {
    char *field; size_t field_len;
    zval *arr;
    ZEND_PARSE_PARAMETERS_START(2, 2)
        Z_PARAM_STRING(field, field_len)
        Z_PARAM_ARRAY(arr)
    ZEND_PARSE_PARAMETERS_END();

    HashTable *ht = Z_ARRVAL_P(arr);
    uint32_t dim = zend_hash_num_elements(ht);
    std::vector<ailego::Float16> vec;
    vec.reserve(dim);
    zval *val;
    ZEND_HASH_FOREACH_VAL(ht, val) {
        vec.push_back(ailego::FloatHelper::ToFP32(static_cast<uint16_t>(zval_get_long(val))));
    } ZEND_HASH_FOREACH_END();

    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    intern->doc->set<std::vector<ailego::Float16>>(std::string(field, field_len), std::move(vec));
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecDoc, setSparseVectorFp32) {
    char *field; size_t field_len;
    zval *indices_arr, *values_arr;
    ZEND_PARSE_PARAMETERS_START(3, 3)
        Z_PARAM_STRING(field, field_len)
        Z_PARAM_ARRAY(indices_arr)
        Z_PARAM_ARRAY(values_arr)
    ZEND_PARSE_PARAMETERS_END();

    HashTable *idx_ht = Z_ARRVAL_P(indices_arr);
    HashTable *val_ht = Z_ARRVAL_P(values_arr);
    uint32_t idx_count = zend_hash_num_elements(idx_ht);
    uint32_t val_count = zend_hash_num_elements(val_ht);

    if (idx_count != val_count) {
        zvec_throw_exception(0, "Indices and values arrays must have the same length");
        RETURN_THROWS();
    }

    std::vector<uint32_t> indices;
    std::vector<float> values;
    indices.reserve(idx_count);
    values.reserve(val_count);

    zval *v;
    ZEND_HASH_FOREACH_VAL(idx_ht, v) {
        indices.push_back(static_cast<uint32_t>(zval_get_long(v)));
    } ZEND_HASH_FOREACH_END();
    ZEND_HASH_FOREACH_VAL(val_ht, v) {
        values.push_back(static_cast<float>(zval_get_double(v)));
    } ZEND_HASH_FOREACH_END();

    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    auto sparse_pair = std::make_pair(std::move(indices), std::move(values));
    intern->doc->set<std::pair<std::vector<uint32_t>, std::vector<float>>>(
        std::string(field, field_len), std::move(sparse_pair));
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecDoc, getPk) {
    ZEND_PARSE_PARAMETERS_NONE();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    std::string pk = intern->doc->pk();
    RETURN_STRINGL(pk.c_str(), pk.length());
}

PHP_METHOD(ZVecDoc, getScore) {
    ZEND_PARSE_PARAMETERS_NONE();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    RETURN_DOUBLE(static_cast<double>(intern->doc->score()));
}

PHP_METHOD(ZVecDoc, getInt64) {
    char *field; size_t field_len;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING(field, field_len)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    auto val = intern->doc->get<int64_t>(std::string(field, field_len));
    if (val.has_value()) {
        RETURN_LONG(static_cast<zend_long>(val.value()));
    }
    RETURN_NULL();
}

PHP_METHOD(ZVecDoc, getString) {
    char *field; size_t field_len;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING(field, field_len)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    auto result = intern->doc->get_field<std::string>(std::string(field, field_len));
    if (result.ok()) {
        const auto &s = result.value();
        RETURN_STRINGL(s.c_str(), s.length());
    }
    RETURN_NULL();
}

PHP_METHOD(ZVecDoc, getFloat) {
    char *field; size_t field_len;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING(field, field_len)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    auto val = intern->doc->get<float>(std::string(field, field_len));
    if (val.has_value()) {
        RETURN_DOUBLE(static_cast<double>(val.value()));
    }
    RETURN_NULL();
}

PHP_METHOD(ZVecDoc, getDouble) {
    char *field; size_t field_len;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING(field, field_len)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    auto val = intern->doc->get<double>(std::string(field, field_len));
    if (val.has_value()) {
        RETURN_DOUBLE(val.value());
    }
    RETURN_NULL();
}

PHP_METHOD(ZVecDoc, getBool) {
    char *field; size_t field_len;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING(field, field_len)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    auto val = intern->doc->get<bool>(std::string(field, field_len));
    if (val.has_value()) {
        RETURN_BOOL(val.value());
    }
    RETURN_NULL();
}

PHP_METHOD(ZVecDoc, getInt32) {
    char *field; size_t field_len;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING(field, field_len)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    auto val = intern->doc->get<int32_t>(std::string(field, field_len));
    if (val.has_value()) {
        RETURN_LONG(static_cast<zend_long>(val.value()));
    }
    RETURN_NULL();
}

PHP_METHOD(ZVecDoc, getUint32) {
    char *field; size_t field_len;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING(field, field_len)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    auto val = intern->doc->get<uint32_t>(std::string(field, field_len));
    if (val.has_value()) {
        RETURN_LONG(static_cast<zend_long>(val.value()));
    }
    RETURN_NULL();
}

PHP_METHOD(ZVecDoc, getUint64) {
    char *field; size_t field_len;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING(field, field_len)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    auto val = intern->doc->get<uint64_t>(std::string(field, field_len));
    if (val.has_value()) {
        RETURN_LONG(static_cast<zend_long>(val.value()));
    }
    RETURN_NULL();
}

PHP_METHOD(ZVecDoc, getVectorFp32) {
    char *field; size_t field_len;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING(field, field_len)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    auto result = intern->doc->get_field<std::vector<float>>(std::string(field, field_len));
    if (result.ok()) {
        const auto &vec = result.value();
        array_init_size(return_value, vec.size());
        for (size_t i = 0; i < vec.size(); i++) {
            add_next_index_double(return_value, static_cast<double>(vec[i]));
        }
        return;
    }
    RETURN_NULL();
}

PHP_METHOD(ZVecDoc, getVectorInt8) {
    char *field; size_t field_len;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING(field, field_len)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    auto result = intern->doc->get_field<std::vector<int8_t>>(std::string(field, field_len));
    if (result.ok()) {
        const auto &vec = result.value();
        array_init_size(return_value, vec.size());
        for (size_t i = 0; i < vec.size(); i++) {
            add_next_index_long(return_value, static_cast<zend_long>(vec[i]));
        }
        return;
    }
    RETURN_NULL();
}

PHP_METHOD(ZVecDoc, getVectorFp16) {
    char *field; size_t field_len;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING(field, field_len)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    auto result = intern->doc->get_field<std::vector<ailego::Float16>>(std::string(field, field_len));
    if (result.ok()) {
        const auto &vec = result.value();
        array_init_size(return_value, vec.size());
        for (size_t i = 0; i < vec.size(); i++) {
            add_next_index_long(return_value,
                static_cast<zend_long>(ailego::FloatHelper::ToFP16(static_cast<float>(vec[i]))));
        }
        return;
    }
    RETURN_NULL();
}

PHP_METHOD(ZVecDoc, getSparseVectorFp32) {
    char *field; size_t field_len;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING(field, field_len)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    auto result = intern->doc->get_field<std::pair<std::vector<uint32_t>, std::vector<float>>>(
        std::string(field, field_len));
    if (result.ok()) {
        const auto &pair = result.value();
        array_init(return_value);

        zval indices_arr, values_arr;
        array_init_size(&indices_arr, pair.first.size());
        array_init_size(&values_arr, pair.second.size());
        for (size_t i = 0; i < pair.first.size(); i++) {
            add_next_index_long(&indices_arr, static_cast<zend_long>(pair.first[i]));
        }
        for (size_t i = 0; i < pair.second.size(); i++) {
            add_next_index_double(&values_arr, static_cast<double>(pair.second[i]));
        }
        add_assoc_zval(return_value, "indices", &indices_arr);
        add_assoc_zval(return_value, "values", &values_arr);
        return;
    }
    RETURN_NULL();
}

PHP_METHOD(ZVecDoc, hasField) {
    char *field; size_t field_len;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING(field, field_len)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    RETURN_BOOL(intern->doc->has(std::string(field, field_len)));
}

PHP_METHOD(ZVecDoc, hasVector) {
    char *field; size_t field_len;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING(field, field_len)
    ZEND_PARSE_PARAMETERS_END();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    std::string fname(field, field_len);
    if (!intern->doc->has(fname)) {
        RETURN_FALSE;
    }
    auto result = intern->doc->get_field<std::vector<float>>(fname);
    RETURN_BOOL(result.ok());
}

PHP_METHOD(ZVecDoc, fieldNames) {
    ZEND_PARSE_PARAMETERS_NONE();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    auto names = intern->doc->field_names();
    std::vector<std::string> result;
    for (const auto &name : names) {
        if (intern->doc->get_field<std::vector<float>>(name).ok()) continue;
        result.push_back(name);
    }
    std::sort(result.begin(), result.end());
    array_init(return_value);
    for (const auto &name : result) {
        add_next_index_stringl(return_value, name.c_str(), name.length());
    }
}

PHP_METHOD(ZVecDoc, vectorNames) {
    ZEND_PARSE_PARAMETERS_NONE();
    auto *intern = Z_ZVEC_DOC_P(ZEND_THIS);
    auto names = intern->doc->field_names();
    std::vector<std::string> result;
    for (const auto &name : names) {
        if (!intern->doc->get_field<std::vector<float>>(name).ok()) continue;
        result.push_back(name);
    }
    std::sort(result.begin(), result.end());
    array_init(return_value);
    for (const auto &name : result) {
        add_next_index_stringl(return_value, name.c_str(), name.length());
    }
}

ZEND_BEGIN_ARG_INFO_EX(arginfo_zvec_doc___construct, 0, 0, 1)
    ZEND_ARG_TYPE_INFO(0, pk, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_zvec_doc_set_scalar, 0, 2, ZVecDoc, 0)
    ZEND_ARG_TYPE_INFO(0, field, IS_STRING, 0)
    ZEND_ARG_INFO(0, value)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_zvec_doc_set_vector, 0, 2, ZVecDoc, 0)
    ZEND_ARG_TYPE_INFO(0, field, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO(0, vector, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_zvec_doc_set_sparse, 0, 3, ZVecDoc, 0)
    ZEND_ARG_TYPE_INFO(0, field, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO(0, indices, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, values, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_zvec_doc_get_pk, 0, 0, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_zvec_doc_get_score, 0, 0, IS_DOUBLE, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_zvec_doc_get_field, 0, 0, 1)
    ZEND_ARG_TYPE_INFO(0, field, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_zvec_doc_has_field, 0, 1, _IS_BOOL, 0)
    ZEND_ARG_TYPE_INFO(0, field, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_zvec_doc_names, 0, 0, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

static const zend_function_entry zvec_doc_methods[] = {
    PHP_ME(ZVecDoc, __construct, arginfo_zvec_doc___construct, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, setInt64, arginfo_zvec_doc_set_scalar, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, setString, arginfo_zvec_doc_set_scalar, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, setFloat, arginfo_zvec_doc_set_scalar, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, setDouble, arginfo_zvec_doc_set_scalar, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, setBool, arginfo_zvec_doc_set_scalar, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, setInt32, arginfo_zvec_doc_set_scalar, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, setUint32, arginfo_zvec_doc_set_scalar, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, setUint64, arginfo_zvec_doc_set_scalar, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, setVectorFp32, arginfo_zvec_doc_set_vector, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, setVectorInt8, arginfo_zvec_doc_set_vector, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, setVectorFp16, arginfo_zvec_doc_set_vector, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, setSparseVectorFp32, arginfo_zvec_doc_set_sparse, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, getPk, arginfo_zvec_doc_get_pk, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, getScore, arginfo_zvec_doc_get_score, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, getInt64, arginfo_zvec_doc_get_field, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, getString, arginfo_zvec_doc_get_field, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, getFloat, arginfo_zvec_doc_get_field, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, getDouble, arginfo_zvec_doc_get_field, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, getBool, arginfo_zvec_doc_get_field, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, getInt32, arginfo_zvec_doc_get_field, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, getUint32, arginfo_zvec_doc_get_field, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, getUint64, arginfo_zvec_doc_get_field, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, getVectorFp32, arginfo_zvec_doc_get_field, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, getVectorInt8, arginfo_zvec_doc_get_field, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, getVectorFp16, arginfo_zvec_doc_get_field, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, getSparseVectorFp32, arginfo_zvec_doc_get_field, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, hasField, arginfo_zvec_doc_has_field, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, hasVector, arginfo_zvec_doc_has_field, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, fieldNames, arginfo_zvec_doc_names, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecDoc, vectorNames, arginfo_zvec_doc_names, ZEND_ACC_PUBLIC)
    PHP_FE_END
};

void zvec_register_doc(INIT_FUNC_ARGS) {
    zend_class_entry ce;
    INIT_CLASS_ENTRY(ce, "ZVecDoc", zvec_doc_methods);
    zvec_doc_ce = zend_register_internal_class(&ce);
    zvec_doc_ce->create_object = zvec_doc_create_object_handler;

    memcpy(&zvec_doc_handlers, &std_object_handlers, sizeof(zend_object_handlers));
    zvec_doc_handlers.offset = XtOffsetOf(zvec_doc_object, std);
    zvec_doc_handlers.free_obj = zvec_doc_free_object;
}
