#include "zvec_rrf_reranker.h"

zend_class_entry *zvec_rrf_reranker_ce = nullptr;

PHP_METHOD(ZVecRrfReRanker, __construct) {
    zend_long topn = 10;
    zend_long rank_constant = 60;
    ZEND_PARSE_PARAMETERS_START(0, 2)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(topn)
        Z_PARAM_LONG(rank_constant)
    ZEND_PARSE_PARAMETERS_END();

    zend_update_property_long(zvec_rrf_reranker_ce, Z_OBJ_P(ZEND_THIS), "topn", sizeof("topn") - 1, topn);
    zend_update_property_long(zvec_rrf_reranker_ce, Z_OBJ_P(ZEND_THIS), "rankConstant", sizeof("rankConstant") - 1, rank_constant);
}

PHP_METHOD(ZVecRrfReRanker, rerank) {
    zval *query_results;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_ARRAY(query_results)
    ZEND_PARSE_PARAMETERS_END();

    HashTable *ht = Z_ARRVAL_P(query_results);
    if (zend_hash_num_elements(ht) == 0) {
        array_init(return_value);
        return;
    }

    zval *topn_zv = zend_read_property(zvec_rrf_reranker_ce, Z_OBJ_P(ZEND_THIS), "topn", sizeof("topn") - 1, 1, nullptr);
    zval *rc_zv = zend_read_property(zvec_rrf_reranker_ce, Z_OBJ_P(ZEND_THIS), "rankConstant", sizeof("rankConstant") - 1, 1, nullptr);
    zend_long topn = Z_LVAL_P(topn_zv);
    zend_long rank_constant = Z_LVAL_P(rc_zv);

    HashTable doc_scores;
    zend_hash_init(&doc_scores, 32, nullptr, ZVAL_PTR_DTOR, 0);

    zend_string *field_name;
    zval *docs_arr;
    ZEND_HASH_FOREACH_STR_KEY_VAL(ht, field_name, docs_arr) {
        if (Z_TYPE_P(docs_arr) != IS_ARRAY || !field_name) continue;

        HashTable *docs_ht = Z_ARRVAL_P(docs_arr);
        zend_long rank_idx = 0;
        zval *doc_zv;
        ZEND_HASH_FOREACH_VAL(docs_ht, doc_zv) {
            if (Z_TYPE_P(doc_zv) != IS_OBJECT || !instanceof_function(Z_OBJCE_P(doc_zv), zvec_doc_ce)) {
                rank_idx++;
                continue;
            }

            zval pk_zv;
            zend_call_method_with_0_params(Z_OBJ_P(doc_zv), Z_OBJCE_P(doc_zv), nullptr, "getpk", &pk_zv);
            if (Z_TYPE(pk_zv) != IS_STRING) {
                zval_ptr_dtor(&pk_zv);
                rank_idx++;
                continue;
            }

            zend_long rank = rank_idx + 1;

            zval score_zv;
            zend_call_method_with_0_params(Z_OBJ_P(doc_zv), Z_OBJCE_P(doc_zv), nullptr, "getscore", &score_zv);
            double score = (Z_TYPE(score_zv) == IS_DOUBLE) ? Z_DVAL(score_zv) : 0.0;
            zval_ptr_dtor(&score_zv);

            zval *existing = zend_hash_find(&doc_scores, Z_STR(pk_zv));
            if (existing) {
                HashTable *entry = Z_ARRVAL_P(existing);
                zval *ranks_zv = zend_hash_str_find(entry, "ranks", sizeof("ranks") - 1);
                zval *scores_zv = zend_hash_str_find(entry, "scores", sizeof("scores") - 1);
                zval rank_val;
                ZVAL_LONG(&rank_val, rank);
                zend_hash_update(Z_ARRVAL_P(ranks_zv), field_name, &rank_val);
                zval score_val;
                ZVAL_DOUBLE(&score_val, score);
                zend_hash_update(Z_ARRVAL_P(scores_zv), field_name, &score_val);
            } else {
                zval entry;
                array_init(&entry);

                zval ranks;
                array_init(&ranks);
                zval rank_val;
                ZVAL_LONG(&rank_val, rank);
                zend_hash_update(Z_ARRVAL(ranks), field_name, &rank_val);
                zend_hash_str_update(Z_ARRVAL(entry), "ranks", sizeof("ranks") - 1, &ranks);

                zval scores;
                array_init(&scores);
                zval score_val;
                ZVAL_DOUBLE(&score_val, score);
                zend_hash_update(Z_ARRVAL(scores), field_name, &score_val);
                zend_hash_str_update(Z_ARRVAL(entry), "scores", sizeof("scores") - 1, &scores);

                Z_ADDREF_P(doc_zv);
                zend_hash_str_update(Z_ARRVAL(entry), "doc", sizeof("doc") - 1, doc_zv);

                zend_hash_update(&doc_scores, Z_STR(pk_zv), &entry);
            }

            zval_ptr_dtor(&pk_zv);
            rank_idx++;
        } ZEND_HASH_FOREACH_END();
    } ZEND_HASH_FOREACH_END();

    zval reranked;
    array_init(&reranked);

    zval *entry;
    ZEND_HASH_FOREACH_VAL(&doc_scores, entry) {
        HashTable *e = Z_ARRVAL_P(entry);
        zval *ranks_zv = zend_hash_str_find(e, "ranks", sizeof("ranks") - 1);
        zval *scores_zv = zend_hash_str_find(e, "scores", sizeof("scores") - 1);
        zval *doc_zv = zend_hash_str_find(e, "doc", sizeof("doc") - 1);

        double rrf_score = 0.0;
        zval *rank_val;
        ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(ranks_zv), rank_val) {
            rrf_score += 1.0 / (rank_constant + Z_LVAL_P(rank_val));
        } ZEND_HASH_FOREACH_END();

        zval rd_obj;
        object_init_ex(&rd_obj, zvec_reranked_doc_ce);

        zend_update_property(zvec_reranked_doc_ce, Z_OBJ(rd_obj), "doc", sizeof("doc") - 1, doc_zv);
        zend_update_property_double(zvec_reranked_doc_ce, Z_OBJ(rd_obj), "combinedScore", sizeof("combinedScore") - 1, rrf_score);
        zend_update_property(zvec_reranked_doc_ce, Z_OBJ(rd_obj), "sourceRanks", sizeof("sourceRanks") - 1, ranks_zv);
        zend_update_property(zvec_reranked_doc_ce, Z_OBJ(rd_obj), "sourceScores", sizeof("sourceScores") - 1, scores_zv);

        add_next_index_zval(&reranked, &rd_obj);
    } ZEND_HASH_FOREACH_END();

    zend_hash_destroy(&doc_scores);

    HashTable *result_ht = Z_ARRVAL(reranked);
    zend_hash_sort(result_ht, [](Bucket *a, Bucket *b) -> int {
        zval *za = &a->val;
        zval *zb = &b->val;
        zval *sa = zend_read_property(zvec_reranked_doc_ce, Z_OBJ_P(za), "combinedScore", sizeof("combinedScore") - 1, 1, nullptr);
        zval *sb = zend_read_property(zvec_reranked_doc_ce, Z_OBJ_P(zb), "combinedScore", sizeof("combinedScore") - 1, 1, nullptr);
        double da = Z_DVAL_P(sa);
        double db = Z_DVAL_P(sb);
        if (db > da) return 1;
        if (db < da) return -1;
        return 0;
    }, 1);

    array_init(return_value);
    zend_long count = 0;
    zval *item;
    ZEND_HASH_FOREACH_VAL(result_ht, item) {
        if (count >= topn) break;
        Z_ADDREF_P(item);
        add_next_index_zval(return_value, item);
        count++;
    } ZEND_HASH_FOREACH_END();

    zval_ptr_dtor(&reranked);
}

ZEND_BEGIN_ARG_INFO_EX(arginfo_zvec_rrf___construct, 0, 0, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, topn, IS_LONG, 0, "10")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, rankConstant, IS_LONG, 0, "60")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_zvec_rrf_rerank, 0, 1, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, queryResults, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

static const zend_function_entry zvec_rrf_reranker_methods[] = {
    PHP_ME(ZVecRrfReRanker, __construct, arginfo_zvec_rrf___construct, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecRrfReRanker, rerank, arginfo_zvec_rrf_rerank, ZEND_ACC_PUBLIC)
    PHP_FE_END
};

void zvec_register_rrf_reranker(INIT_FUNC_ARGS) {
    zend_class_entry ce;
    INIT_CLASS_ENTRY(ce, "ZVecRrfReRanker", zvec_rrf_reranker_methods);
    zvec_rrf_reranker_ce = zend_register_internal_class(&ce);
    zend_class_implements(zvec_rrf_reranker_ce, 1, zvec_reranker_ce);

    zend_declare_property_long(zvec_rrf_reranker_ce, "topn", sizeof("topn") - 1, 10, ZEND_ACC_PUBLIC);
    zend_declare_property_long(zvec_rrf_reranker_ce, "rankConstant", sizeof("rankConstant") - 1, 60, ZEND_ACC_PUBLIC);
}
