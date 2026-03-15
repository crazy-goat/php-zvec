#ifndef PHP_ZVEC_H
#define PHP_ZVEC_H

extern "C" {
#include "php.h"
#include "zend_exceptions.h"
#include "zend_interfaces.h"
}

#define PHP_ZVEC_VERSION "0.1.0"
#define PHP_ZVEC_EXTNAME "zvec"

extern zend_module_entry zvec_module_entry;
#define phpext_zvec_ptr &zvec_module_entry

PHP_MINIT_FUNCTION(zvec);
PHP_MSHUTDOWN_FUNCTION(zvec);
PHP_MINFO_FUNCTION(zvec);

void zvec_register_exception(INIT_FUNC_ARGS);
void zvec_register_schema(INIT_FUNC_ARGS);
void zvec_register_doc(INIT_FUNC_ARGS);
void zvec_register_vector_query(INIT_FUNC_ARGS);
void zvec_register_collection(INIT_FUNC_ARGS);
void zvec_register_reranker(INIT_FUNC_ARGS);
void zvec_register_reranked_doc(INIT_FUNC_ARGS);
void zvec_register_rrf_reranker(INIT_FUNC_ARGS);
void zvec_register_weighted_reranker(INIT_FUNC_ARGS);
void zvec_register_embedding_interfaces(INIT_FUNC_ARGS);
void zvec_register_openai_embedding(INIT_FUNC_ARGS);
void zvec_register_qwen_embedding(INIT_FUNC_ARGS);

extern zend_class_entry *zvec_exception_ce;
extern zend_class_entry *zvec_schema_ce;
extern zend_class_entry *zvec_doc_ce;
extern zend_class_entry *zvec_vector_query_ce;
extern zend_class_entry *zvec_collection_ce;
extern zend_class_entry *zvec_reranker_ce;
extern zend_class_entry *zvec_reranked_doc_ce;
extern zend_class_entry *zvec_rrf_reranker_ce;
extern zend_class_entry *zvec_weighted_reranker_ce;
extern zend_class_entry *zvec_dense_embedding_ce;
extern zend_class_entry *zvec_sparse_embedding_ce;
extern zend_class_entry *zvec_api_embedding_ce;
extern zend_class_entry *zvec_openai_embedding_ce;
extern zend_class_entry *zvec_qwen_embedding_ce;

#endif
