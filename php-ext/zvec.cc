#include "php_zvec.h"

extern "C" {
#include "ext/standard/info.h"
}

PHP_MINIT_FUNCTION(zvec) {
    zvec_register_exception(INIT_FUNC_ARGS_PASSTHRU);
    zvec_register_schema(INIT_FUNC_ARGS_PASSTHRU);
    zvec_register_doc(INIT_FUNC_ARGS_PASSTHRU);
    zvec_register_vector_query(INIT_FUNC_ARGS_PASSTHRU);
    zvec_register_collection(INIT_FUNC_ARGS_PASSTHRU);
    zvec_register_reranker(INIT_FUNC_ARGS_PASSTHRU);
    zvec_register_reranked_doc(INIT_FUNC_ARGS_PASSTHRU);
    zvec_register_rrf_reranker(INIT_FUNC_ARGS_PASSTHRU);
    zvec_register_weighted_reranker(INIT_FUNC_ARGS_PASSTHRU);
    zvec_register_embedding_interfaces(INIT_FUNC_ARGS_PASSTHRU);
    zvec_register_openai_embedding(INIT_FUNC_ARGS_PASSTHRU);
    zvec_register_qwen_embedding(INIT_FUNC_ARGS_PASSTHRU);
    return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(zvec) {
    return SUCCESS;
}

PHP_MINFO_FUNCTION(zvec) {
    php_info_print_table_start();
    php_info_print_table_header(2, "zvec support", "enabled");
    php_info_print_table_row(2, "zvec extension version", PHP_ZVEC_VERSION);
    php_info_print_table_end();
}

zend_module_entry zvec_module_entry = {
    STANDARD_MODULE_HEADER,
    PHP_ZVEC_EXTNAME,
    nullptr,
    PHP_MINIT(zvec),
    PHP_MSHUTDOWN(zvec),
    nullptr,
    nullptr,
    PHP_MINFO(zvec),
    PHP_ZVEC_VERSION,
    STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_ZVEC
extern "C" {
    ZEND_GET_MODULE(zvec)
}
#endif
