#include "zvec_reranked_doc.h"

zend_class_entry *zvec_reranked_doc_ce = nullptr;

PHP_METHOD(ZVecRerankedDoc, __construct) {
    zval *doc;
    double combined_score;
    zval *source_ranks = nullptr;
    zval *source_scores = nullptr;
    ZEND_PARSE_PARAMETERS_START(2, 4)
        Z_PARAM_OBJECT_OF_CLASS(doc, zvec_doc_ce)
        Z_PARAM_DOUBLE(combined_score)
        Z_PARAM_OPTIONAL
        Z_PARAM_ARRAY(source_ranks)
        Z_PARAM_ARRAY(source_scores)
    ZEND_PARSE_PARAMETERS_END();

    zend_update_property(zvec_reranked_doc_ce, Z_OBJ_P(ZEND_THIS), "doc", sizeof("doc") - 1, doc);
    zend_update_property_double(zvec_reranked_doc_ce, Z_OBJ_P(ZEND_THIS), "combinedScore", sizeof("combinedScore") - 1, combined_score);

    if (source_ranks) {
        zend_update_property(zvec_reranked_doc_ce, Z_OBJ_P(ZEND_THIS), "sourceRanks", sizeof("sourceRanks") - 1, source_ranks);
    } else {
        zval empty;
        array_init(&empty);
        zend_update_property(zvec_reranked_doc_ce, Z_OBJ_P(ZEND_THIS), "sourceRanks", sizeof("sourceRanks") - 1, &empty);
        zval_ptr_dtor(&empty);
    }

    if (source_scores) {
        zend_update_property(zvec_reranked_doc_ce, Z_OBJ_P(ZEND_THIS), "sourceScores", sizeof("sourceScores") - 1, source_scores);
    } else {
        zval empty;
        array_init(&empty);
        zend_update_property(zvec_reranked_doc_ce, Z_OBJ_P(ZEND_THIS), "sourceScores", sizeof("sourceScores") - 1, &empty);
        zval_ptr_dtor(&empty);
    }
}

PHP_METHOD(ZVecRerankedDoc, getPk) {
    ZEND_PARSE_PARAMETERS_NONE();

    zval *doc = zend_read_property(zvec_reranked_doc_ce, Z_OBJ_P(ZEND_THIS), "doc", sizeof("doc") - 1, 1, nullptr);
    if (!doc || Z_TYPE_P(doc) != IS_OBJECT) {
        RETURN_EMPTY_STRING();
    }

    zend_call_method_with_0_params(Z_OBJ_P(doc), Z_OBJCE_P(doc), nullptr, "getpk", return_value);
}

PHP_METHOD(ZVecRerankedDoc, getOriginalScore) {
    ZEND_PARSE_PARAMETERS_NONE();

    zval *doc = zend_read_property(zvec_reranked_doc_ce, Z_OBJ_P(ZEND_THIS), "doc", sizeof("doc") - 1, 1, nullptr);
    if (!doc || Z_TYPE_P(doc) != IS_OBJECT) {
        RETURN_DOUBLE(0.0);
    }

    zend_call_method_with_0_params(Z_OBJ_P(doc), Z_OBJCE_P(doc), nullptr, "getscore", return_value);
}

ZEND_BEGIN_ARG_INFO_EX(arginfo_zvec_rd___construct, 0, 0, 2)
    ZEND_ARG_OBJ_INFO(0, doc, ZVecDoc, 0)
    ZEND_ARG_TYPE_INFO(0, combinedScore, IS_DOUBLE, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, sourceRanks, IS_ARRAY, 0, "[]")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, sourceScores, IS_ARRAY, 0, "[]")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_zvec_rd_get_pk, 0, 0, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_zvec_rd_get_original_score, 0, 0, IS_DOUBLE, 0)
ZEND_END_ARG_INFO()

static const zend_function_entry zvec_reranked_doc_methods[] = {
    PHP_ME(ZVecRerankedDoc, __construct, arginfo_zvec_rd___construct, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecRerankedDoc, getPk, arginfo_zvec_rd_get_pk, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecRerankedDoc, getOriginalScore, arginfo_zvec_rd_get_original_score, ZEND_ACC_PUBLIC)
    PHP_FE_END
};

void zvec_register_reranked_doc(INIT_FUNC_ARGS) {
    zend_class_entry ce;
    INIT_CLASS_ENTRY(ce, "ZVecRerankedDoc", zvec_reranked_doc_methods);
    zvec_reranked_doc_ce = zend_register_internal_class(&ce);

    zend_declare_property_null(zvec_reranked_doc_ce, "doc", sizeof("doc") - 1, ZEND_ACC_PUBLIC);
    zend_declare_property_double(zvec_reranked_doc_ce, "combinedScore", sizeof("combinedScore") - 1, 0.0, ZEND_ACC_PUBLIC);
    zend_declare_property_null(zvec_reranked_doc_ce, "sourceRanks", sizeof("sourceRanks") - 1, ZEND_ACC_PUBLIC);
    zend_declare_property_null(zvec_reranked_doc_ce, "sourceScores", sizeof("sourceScores") - 1, ZEND_ACC_PUBLIC);
}
