#include "zvec_vector_query.h"

zend_class_entry *zvec_vector_query_ce = nullptr;

PHP_METHOD(ZVecVectorQuery, __construct) {
    char *field; size_t field_len;
    zval *vector;
    ZEND_PARSE_PARAMETERS_START(2, 2)
        Z_PARAM_STRING(field, field_len)
        Z_PARAM_ARRAY(vector)
    ZEND_PARSE_PARAMETERS_END();

    zend_update_property_stringl(zvec_vector_query_ce, Z_OBJ_P(ZEND_THIS), "fieldName", sizeof("fieldName") - 1, field, field_len);
    zend_update_property(zvec_vector_query_ce, Z_OBJ_P(ZEND_THIS), "vector", sizeof("vector") - 1, vector);
    zend_update_property_null(zvec_vector_query_ce, Z_OBJ_P(ZEND_THIS), "docId", sizeof("docId") - 1);
    zend_update_property_long(zvec_vector_query_ce, Z_OBJ_P(ZEND_THIS), "queryParamType", sizeof("queryParamType") - 1, 0);
    zend_update_property_long(zvec_vector_query_ce, Z_OBJ_P(ZEND_THIS), "hnswEf", sizeof("hnswEf") - 1, 200);
    zend_update_property_long(zvec_vector_query_ce, Z_OBJ_P(ZEND_THIS), "ivfNprobe", sizeof("ivfNprobe") - 1, 10);
    zend_update_property_double(zvec_vector_query_ce, Z_OBJ_P(ZEND_THIS), "radius", sizeof("radius") - 1, 0.0);
    zend_update_property_bool(zvec_vector_query_ce, Z_OBJ_P(ZEND_THIS), "isLinear", sizeof("isLinear") - 1, 0);
    zend_update_property_bool(zvec_vector_query_ce, Z_OBJ_P(ZEND_THIS), "isUsingRefiner", sizeof("isUsingRefiner") - 1, 0);
}

PHP_METHOD(ZVecVectorQuery, fromId) {
    char *field; size_t field_len;
    char *doc_id; size_t doc_id_len;
    ZEND_PARSE_PARAMETERS_START(2, 2)
        Z_PARAM_STRING(field, field_len)
        Z_PARAM_STRING(doc_id, doc_id_len)
    ZEND_PARSE_PARAMETERS_END();

    object_init_ex(return_value, zvec_vector_query_ce);

    zval empty_arr;
    array_init(&empty_arr);
    zend_update_property_stringl(zvec_vector_query_ce, Z_OBJ_P(return_value), "fieldName", sizeof("fieldName") - 1, field, field_len);
    zend_update_property(zvec_vector_query_ce, Z_OBJ_P(return_value), "vector", sizeof("vector") - 1, &empty_arr);
    zval_ptr_dtor(&empty_arr);
    zend_update_property_stringl(zvec_vector_query_ce, Z_OBJ_P(return_value), "docId", sizeof("docId") - 1, doc_id, doc_id_len);
    zend_update_property_long(zvec_vector_query_ce, Z_OBJ_P(return_value), "queryParamType", sizeof("queryParamType") - 1, 0);
    zend_update_property_long(zvec_vector_query_ce, Z_OBJ_P(return_value), "hnswEf", sizeof("hnswEf") - 1, 200);
    zend_update_property_long(zvec_vector_query_ce, Z_OBJ_P(return_value), "ivfNprobe", sizeof("ivfNprobe") - 1, 10);
    zend_update_property_double(zvec_vector_query_ce, Z_OBJ_P(return_value), "radius", sizeof("radius") - 1, 0.0);
    zend_update_property_bool(zvec_vector_query_ce, Z_OBJ_P(return_value), "isLinear", sizeof("isLinear") - 1, 0);
    zend_update_property_bool(zvec_vector_query_ce, Z_OBJ_P(return_value), "isUsingRefiner", sizeof("isUsingRefiner") - 1, 0);
}

PHP_METHOD(ZVecVectorQuery, setHnswParams) {
    zend_long ef;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_LONG(ef)
    ZEND_PARSE_PARAMETERS_END();
    zend_update_property_long(zvec_vector_query_ce, Z_OBJ_P(ZEND_THIS), "queryParamType", sizeof("queryParamType") - 1, 1);
    zend_update_property_long(zvec_vector_query_ce, Z_OBJ_P(ZEND_THIS), "hnswEf", sizeof("hnswEf") - 1, ef);
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecVectorQuery, setIvfParams) {
    zend_long nprobe;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_LONG(nprobe)
    ZEND_PARSE_PARAMETERS_END();
    zend_update_property_long(zvec_vector_query_ce, Z_OBJ_P(ZEND_THIS), "queryParamType", sizeof("queryParamType") - 1, 2);
    zend_update_property_long(zvec_vector_query_ce, Z_OBJ_P(ZEND_THIS), "ivfNprobe", sizeof("ivfNprobe") - 1, nprobe);
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecVectorQuery, setFlatParams) {
    ZEND_PARSE_PARAMETERS_NONE();
    zend_update_property_long(zvec_vector_query_ce, Z_OBJ_P(ZEND_THIS), "queryParamType", sizeof("queryParamType") - 1, 3);
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecVectorQuery, setRadius) {
    double radius;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_DOUBLE(radius)
    ZEND_PARSE_PARAMETERS_END();
    zend_update_property_double(zvec_vector_query_ce, Z_OBJ_P(ZEND_THIS), "radius", sizeof("radius") - 1, radius);
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecVectorQuery, setLinear) {
    zend_bool linear;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_BOOL(linear)
    ZEND_PARSE_PARAMETERS_END();
    zend_update_property_bool(zvec_vector_query_ce, Z_OBJ_P(ZEND_THIS), "isLinear", sizeof("isLinear") - 1, linear);
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

PHP_METHOD(ZVecVectorQuery, setUsingRefiner) {
    zend_bool refiner;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_BOOL(refiner)
    ZEND_PARSE_PARAMETERS_END();
    zend_update_property_bool(zvec_vector_query_ce, Z_OBJ_P(ZEND_THIS), "isUsingRefiner", sizeof("isUsingRefiner") - 1, refiner);
    RETURN_ZVAL(ZEND_THIS, 1, 0);
}

ZEND_BEGIN_ARG_INFO_EX(arginfo_zvec_vq___construct, 0, 0, 2)
    ZEND_ARG_TYPE_INFO(0, fieldName, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO(0, vector, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_zvec_vq_from_id, 0, 2, ZVecVectorQuery, 0)
    ZEND_ARG_TYPE_INFO(0, fieldName, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO(0, docId, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_zvec_vq_set_hnsw, 0, 1, ZVecVectorQuery, 0)
    ZEND_ARG_TYPE_INFO(0, ef, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_zvec_vq_set_ivf, 0, 1, ZVecVectorQuery, 0)
    ZEND_ARG_TYPE_INFO(0, nprobe, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_zvec_vq_set_none, 0, 0, ZVecVectorQuery, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_zvec_vq_set_radius, 0, 1, ZVecVectorQuery, 0)
    ZEND_ARG_TYPE_INFO(0, radius, IS_DOUBLE, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_zvec_vq_set_linear, 0, 1, ZVecVectorQuery, 0)
    ZEND_ARG_TYPE_INFO(0, linear, _IS_BOOL, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_zvec_vq_set_refiner, 0, 1, ZVecVectorQuery, 0)
    ZEND_ARG_TYPE_INFO(0, refiner, _IS_BOOL, 0)
ZEND_END_ARG_INFO()

static const zend_function_entry zvec_vector_query_methods[] = {
    PHP_ME(ZVecVectorQuery, __construct, arginfo_zvec_vq___construct, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecVectorQuery, fromId, arginfo_zvec_vq_from_id, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
    PHP_ME(ZVecVectorQuery, setHnswParams, arginfo_zvec_vq_set_hnsw, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecVectorQuery, setIvfParams, arginfo_zvec_vq_set_ivf, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecVectorQuery, setFlatParams, arginfo_zvec_vq_set_none, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecVectorQuery, setRadius, arginfo_zvec_vq_set_radius, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecVectorQuery, setLinear, arginfo_zvec_vq_set_linear, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecVectorQuery, setUsingRefiner, arginfo_zvec_vq_set_refiner, ZEND_ACC_PUBLIC)
    PHP_FE_END
};

void zvec_register_vector_query(INIT_FUNC_ARGS) {
    zend_class_entry ce;
    INIT_CLASS_ENTRY(ce, "ZVecVectorQuery", zvec_vector_query_methods);
    zvec_vector_query_ce = zend_register_internal_class(&ce);

    zend_declare_property_null(zvec_vector_query_ce, "fieldName", sizeof("fieldName") - 1, ZEND_ACC_PUBLIC);
    zend_declare_property_null(zvec_vector_query_ce, "vector", sizeof("vector") - 1, ZEND_ACC_PUBLIC);
    zend_declare_property_null(zvec_vector_query_ce, "docId", sizeof("docId") - 1, ZEND_ACC_PUBLIC);
    zend_declare_property_long(zvec_vector_query_ce, "queryParamType", sizeof("queryParamType") - 1, 0, ZEND_ACC_PUBLIC);
    zend_declare_property_long(zvec_vector_query_ce, "hnswEf", sizeof("hnswEf") - 1, 200, ZEND_ACC_PUBLIC);
    zend_declare_property_long(zvec_vector_query_ce, "ivfNprobe", sizeof("ivfNprobe") - 1, 10, ZEND_ACC_PUBLIC);
    zend_declare_property_double(zvec_vector_query_ce, "radius", sizeof("radius") - 1, 0.0, ZEND_ACC_PUBLIC);
    zend_declare_property_bool(zvec_vector_query_ce, "isLinear", sizeof("isLinear") - 1, 0, ZEND_ACC_PUBLIC);
    zend_declare_property_bool(zvec_vector_query_ce, "isUsingRefiner", sizeof("isUsingRefiner") - 1, 0, ZEND_ACC_PUBLIC);
}
