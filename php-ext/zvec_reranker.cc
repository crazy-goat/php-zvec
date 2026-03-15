#include "zvec_reranker.h"

zend_class_entry *zvec_reranker_ce = nullptr;

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_zvec_reranker_rerank, 0, 1, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, queryResults, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

static const zend_function_entry zvec_reranker_methods[] = {
    PHP_ABSTRACT_ME(ZVecReRanker, rerank, arginfo_zvec_reranker_rerank)
    PHP_FE_END
};

void zvec_register_reranker(INIT_FUNC_ARGS) {
    zend_class_entry ce;
    INIT_CLASS_ENTRY(ce, "ZVecReRanker", zvec_reranker_methods);
    zvec_reranker_ce = zend_register_internal_interface(&ce);
}
