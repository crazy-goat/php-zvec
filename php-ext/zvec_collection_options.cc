#include "zvec_collection_options.h"

zend_class_entry *zvec_collection_options_ce = nullptr;

PHP_METHOD(ZVecCollectionOptions, __construct) {
    zend_bool read_only = 0;
    zend_bool enable_mmap = 1;
    zend_long max_buffer_size = 67108864;

    ZEND_PARSE_PARAMETERS_START(0, 3)
        Z_PARAM_OPTIONAL
        Z_PARAM_BOOL(read_only)
        Z_PARAM_BOOL(enable_mmap)
        Z_PARAM_LONG(max_buffer_size)
    ZEND_PARSE_PARAMETERS_END();

    zend_update_property_bool(zvec_collection_options_ce, Z_OBJ_P(ZEND_THIS), "readOnly", sizeof("readOnly") - 1, read_only);
    zend_update_property_bool(zvec_collection_options_ce, Z_OBJ_P(ZEND_THIS), "enableMmap", sizeof("enableMmap") - 1, enable_mmap);
    zend_update_property_long(zvec_collection_options_ce, Z_OBJ_P(ZEND_THIS), "maxBufferSize", sizeof("maxBufferSize") - 1, max_buffer_size);
}

// Getters - direct property access
PHP_METHOD(ZVecCollectionOptions, getReadOnly) {
    zval rv;
    zval *prop = zend_read_property(zvec_collection_options_ce, Z_OBJ_P(ZEND_THIS), "readOnly", sizeof("readOnly") - 1, 0, &rv);
    RETURN_BOOL(zval_is_true(prop));
}

PHP_METHOD(ZVecCollectionOptions, getEnableMmap) {
    zval rv;
    zval *prop = zend_read_property(zvec_collection_options_ce, Z_OBJ_P(ZEND_THIS), "enableMmap", sizeof("enableMmap") - 1, 0, &rv);
    RETURN_BOOL(zval_is_true(prop));
}

PHP_METHOD(ZVecCollectionOptions, getMaxBufferSize) {
    zval rv;
    zval *prop = zend_read_property(zvec_collection_options_ce, Z_OBJ_P(ZEND_THIS), "maxBufferSize", sizeof("maxBufferSize") - 1, 0, &rv);
    RETURN_LONG(Z_LVAL_P(prop));
}

ZEND_BEGIN_ARG_INFO_EX(arginfo_collection_options_construct, 0, 0, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, readOnly, _IS_BOOL, 0, "false")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, enableMmap, _IS_BOOL, 0, "true")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, maxBufferSize, IS_LONG, 0, "67108864")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_collection_options_get_read_only, 0, 0, _IS_BOOL, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_collection_options_get_enable_mmap, 0, 0, _IS_BOOL, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_collection_options_get_max_buffer_size, 0, 0, IS_LONG, 0)
ZEND_END_ARG_INFO()

static const zend_function_entry zvec_collection_options_methods[] = {
    PHP_ME(ZVecCollectionOptions, __construct, arginfo_collection_options_construct, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecCollectionOptions, getReadOnly, arginfo_collection_options_get_read_only, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecCollectionOptions, getEnableMmap, arginfo_collection_options_get_enable_mmap, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecCollectionOptions, getMaxBufferSize, arginfo_collection_options_get_max_buffer_size, ZEND_ACC_PUBLIC)
    PHP_FE_END
};

void zvec_register_collection_options(INIT_FUNC_ARGS) {
    zend_class_entry ce;
    INIT_CLASS_ENTRY(ce, "ZVecCollectionOptions", zvec_collection_options_methods);
    zvec_collection_options_ce = zend_register_internal_class(&ce);

    zend_declare_property_bool(zvec_collection_options_ce, "readOnly", sizeof("readOnly") - 1, 0, ZEND_ACC_PUBLIC);
    zend_declare_property_bool(zvec_collection_options_ce, "enableMmap", sizeof("enableMmap") - 1, 1, ZEND_ACC_PUBLIC);
    zend_declare_property_long(zvec_collection_options_ce, "maxBufferSize", sizeof("maxBufferSize") - 1, 67108864, ZEND_ACC_PUBLIC);
}
