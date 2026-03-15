#include "zvec_weighted_reranker.h"
#include <cfloat>

zend_class_entry *zvec_weighted_reranker_ce = nullptr;

PHP_METHOD(ZVecWeightedReRanker, __construct) {
    zend_long topn = 10;
    zend_long metric_type = 2;
    zval *weights = nullptr;
    ZEND_PARSE_PARAMETERS_START(0, 3)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(topn)
        Z_PARAM_LONG(metric_type)
        Z_PARAM_ARRAY(weights)
    ZEND_PARSE_PARAMETERS_END();

    zend_update_property_long(zvec_weighted_reranker_ce, Z_OBJ_P(ZEND_THIS), "topn", sizeof("topn") - 1, topn);
    zend_update_property_long(zvec_weighted_reranker_ce, Z_OBJ_P(ZEND_THIS), "metricType", sizeof("metricType") - 1, metric_type);

    if (weights) {
        zend_update_property(zvec_weighted_reranker_ce, Z_OBJ_P(ZEND_THIS), "weights", sizeof("weights") - 1, weights);
    } else {
        zval empty;
        array_init(&empty);
        zend_update_property(zvec_weighted_reranker_ce, Z_OBJ_P(ZEND_THIS), "weights", sizeof("weights") - 1, &empty);
        zval_ptr_dtor(&empty);
    }
}

PHP_METHOD(ZVecWeightedReRanker, rerank) {
    zval *query_results;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_ARRAY(query_results)
    ZEND_PARSE_PARAMETERS_END();

    HashTable *ht = Z_ARRVAL_P(query_results);
    if (zend_hash_num_elements(ht) == 0) {
        array_init(return_value);
        return;
    }

    zval *topn_zv = zend_read_property(zvec_weighted_reranker_ce, Z_OBJ_P(ZEND_THIS), "topn", sizeof("topn") - 1, 1, nullptr);
    zval *mt_zv = zend_read_property(zvec_weighted_reranker_ce, Z_OBJ_P(ZEND_THIS), "metricType", sizeof("metricType") - 1, 1, nullptr);
    zval *weights_zv = zend_read_property(zvec_weighted_reranker_ce, Z_OBJ_P(ZEND_THIS), "weights", sizeof("weights") - 1, 1, nullptr);
    zend_long topn = Z_LVAL_P(topn_zv);
    zend_long metric_type = Z_LVAL_P(mt_zv);

    HashTable field_stats;
    zend_hash_init(&field_stats, 8, nullptr, ZVAL_PTR_DTOR, 0);

    HashTable all_docs;
    zend_hash_init(&all_docs, 8, nullptr, ZVAL_PTR_DTOR, 0);

    zend_string *field_name;
    zval *docs_arr;
    ZEND_HASH_FOREACH_STR_KEY_VAL(ht, field_name, docs_arr) {
        if (Z_TYPE_P(docs_arr) != IS_ARRAY || !field_name) continue;

        zval stats;
        array_init(&stats);
        zval min_val, max_val;
        ZVAL_DOUBLE(&min_val, DBL_MAX);
        ZVAL_DOUBLE(&max_val, -DBL_MAX);
        zend_hash_str_update(Z_ARRVAL(stats), "min", sizeof("min") - 1, &min_val);
        zend_hash_str_update(Z_ARRVAL(stats), "max", sizeof("max") - 1, &max_val);

        zval field_docs;
        array_init(&field_docs);

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

            zval score_zv;
            zend_call_method_with_0_params(Z_OBJ_P(doc_zv), Z_OBJCE_P(doc_zv), nullptr, "getscore", &score_zv);
            double score = (Z_TYPE(score_zv) == IS_DOUBLE) ? Z_DVAL(score_zv) : 0.0;
            zval_ptr_dtor(&score_zv);

            zval doc_entry;
            array_init(&doc_entry);
            Z_ADDREF_P(doc_zv);
            zend_hash_str_update(Z_ARRVAL(doc_entry), "doc", sizeof("doc") - 1, doc_zv);
            zval s;
            ZVAL_DOUBLE(&s, score);
            zend_hash_str_update(Z_ARRVAL(doc_entry), "score", sizeof("score") - 1, &s);
            zval r;
            ZVAL_LONG(&r, rank_idx + 1);
            zend_hash_str_update(Z_ARRVAL(doc_entry), "rank", sizeof("rank") - 1, &r);

            zend_hash_update(Z_ARRVAL(field_docs), Z_STR(pk_zv), &doc_entry);

            zval *cur_min = zend_hash_str_find(Z_ARRVAL(stats), "min", sizeof("min") - 1);
            zval *cur_max = zend_hash_str_find(Z_ARRVAL(stats), "max", sizeof("max") - 1);
            if (score < Z_DVAL_P(cur_min)) ZVAL_DOUBLE(cur_min, score);
            if (score > Z_DVAL_P(cur_max)) ZVAL_DOUBLE(cur_max, score);

            zval_ptr_dtor(&pk_zv);
            rank_idx++;
        } ZEND_HASH_FOREACH_END();

        zend_hash_update(&field_stats, field_name, &stats);
        zend_hash_update(&all_docs, field_name, &field_docs);
    } ZEND_HASH_FOREACH_END();

    HashTable combined;
    zend_hash_init(&combined, 32, nullptr, ZVAL_PTR_DTOR, 0);

    ZEND_HASH_FOREACH_STR_KEY_VAL(&all_docs, field_name, docs_arr) {
        if (!field_name) continue;

        double weight = 0.0;
        if (Z_TYPE_P(weights_zv) == IS_ARRAY) {
            zval *w = zend_hash_find(Z_ARRVAL_P(weights_zv), field_name);
            if (w) {
                weight = (Z_TYPE_P(w) == IS_DOUBLE) ? Z_DVAL_P(w) : (double)Z_LVAL_P(w);
            }
        }
        if (weight == 0.0) continue;

        zval *stats = zend_hash_find(&field_stats, field_name);
        double min_s = Z_DVAL_P(zend_hash_str_find(Z_ARRVAL_P(stats), "min", sizeof("min") - 1));
        double max_s = Z_DVAL_P(zend_hash_str_find(Z_ARRVAL_P(stats), "max", sizeof("max") - 1));
        double range = max_s - min_s;
        if (range == 0.0) range = 1.0;

        zend_string *pk;
        zval *doc_entry;
        ZEND_HASH_FOREACH_STR_KEY_VAL(Z_ARRVAL_P(docs_arr), pk, doc_entry) {
            if (!pk) continue;
            double score = Z_DVAL_P(zend_hash_str_find(Z_ARRVAL_P(doc_entry), "score", sizeof("score") - 1));
            zval *doc_zv = zend_hash_str_find(Z_ARRVAL_P(doc_entry), "doc", sizeof("doc") - 1);
            zend_long rank = Z_LVAL_P(zend_hash_str_find(Z_ARRVAL_P(doc_entry), "rank", sizeof("rank") - 1));

            double normalized;
            if (metric_type == 1) {
                normalized = (max_s - score) / range;
            } else {
                normalized = (score - min_s) / range;
            }

            zval *existing = zend_hash_find(&combined, pk);
            if (existing) {
                zval *c = zend_hash_str_find(Z_ARRVAL_P(existing), "combined", sizeof("combined") - 1);
                ZVAL_DOUBLE(c, Z_DVAL_P(c) + weight * normalized);
                zval *ranks = zend_hash_str_find(Z_ARRVAL_P(existing), "ranks", sizeof("ranks") - 1);
                zval rv;
                ZVAL_LONG(&rv, rank);
                zend_hash_update(Z_ARRVAL_P(ranks), field_name, &rv);
                zval *scores = zend_hash_str_find(Z_ARRVAL_P(existing), "scores", sizeof("scores") - 1);
                zval sv;
                ZVAL_DOUBLE(&sv, score);
                zend_hash_update(Z_ARRVAL_P(scores), field_name, &sv);
            } else {
                zval entry;
                array_init(&entry);

                zval c;
                ZVAL_DOUBLE(&c, weight * normalized);
                zend_hash_str_update(Z_ARRVAL(entry), "combined", sizeof("combined") - 1, &c);

                zval ranks;
                array_init(&ranks);
                zval rv;
                ZVAL_LONG(&rv, rank);
                zend_hash_update(Z_ARRVAL(ranks), field_name, &rv);
                zend_hash_str_update(Z_ARRVAL(entry), "ranks", sizeof("ranks") - 1, &ranks);

                zval scores;
                array_init(&scores);
                zval sv;
                ZVAL_DOUBLE(&sv, score);
                zend_hash_update(Z_ARRVAL(scores), field_name, &sv);
                zend_hash_str_update(Z_ARRVAL(entry), "scores", sizeof("scores") - 1, &scores);

                Z_ADDREF_P(doc_zv);
                zend_hash_str_update(Z_ARRVAL(entry), "doc", sizeof("doc") - 1, doc_zv);

                zend_hash_update(&combined, pk, &entry);
            }
        } ZEND_HASH_FOREACH_END();
    } ZEND_HASH_FOREACH_END();

    zval reranked;
    array_init(&reranked);

    zval *entry;
    ZEND_HASH_FOREACH_VAL(&combined, entry) {
        zval *doc_zv = zend_hash_str_find(Z_ARRVAL_P(entry), "doc", sizeof("doc") - 1);
        zval *c = zend_hash_str_find(Z_ARRVAL_P(entry), "combined", sizeof("combined") - 1);
        zval *ranks = zend_hash_str_find(Z_ARRVAL_P(entry), "ranks", sizeof("ranks") - 1);
        zval *scores = zend_hash_str_find(Z_ARRVAL_P(entry), "scores", sizeof("scores") - 1);

        zval rd_obj;
        object_init_ex(&rd_obj, zvec_reranked_doc_ce);
        zend_update_property(zvec_reranked_doc_ce, Z_OBJ(rd_obj), "doc", sizeof("doc") - 1, doc_zv);
        zend_update_property_double(zvec_reranked_doc_ce, Z_OBJ(rd_obj), "combinedScore", sizeof("combinedScore") - 1, Z_DVAL_P(c));
        zend_update_property(zvec_reranked_doc_ce, Z_OBJ(rd_obj), "sourceRanks", sizeof("sourceRanks") - 1, ranks);
        zend_update_property(zvec_reranked_doc_ce, Z_OBJ(rd_obj), "sourceScores", sizeof("sourceScores") - 1, scores);
        add_next_index_zval(&reranked, &rd_obj);
    } ZEND_HASH_FOREACH_END();

    zend_hash_destroy(&field_stats);
    zend_hash_destroy(&all_docs);
    zend_hash_destroy(&combined);

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

ZEND_BEGIN_ARG_INFO_EX(arginfo_zvec_wr___construct, 0, 0, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, topn, IS_LONG, 0, "10")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, metricType, IS_LONG, 0, "2")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, weights, IS_ARRAY, 0, "[]")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_zvec_wr_rerank, 0, 1, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, queryResults, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

static const zend_function_entry zvec_weighted_reranker_methods[] = {
    PHP_ME(ZVecWeightedReRanker, __construct, arginfo_zvec_wr___construct, ZEND_ACC_PUBLIC)
    PHP_ME(ZVecWeightedReRanker, rerank, arginfo_zvec_wr_rerank, ZEND_ACC_PUBLIC)
    PHP_FE_END
};

void zvec_register_weighted_reranker(INIT_FUNC_ARGS) {
    zend_class_entry ce;
    INIT_CLASS_ENTRY(ce, "ZVecWeightedReRanker", zvec_weighted_reranker_methods);
    zvec_weighted_reranker_ce = zend_register_internal_class(&ce);
    zend_class_implements(zvec_weighted_reranker_ce, 1, zvec_reranker_ce);

    zend_declare_property_long(zvec_weighted_reranker_ce, "topn", sizeof("topn") - 1, 10, ZEND_ACC_PUBLIC);
    zend_declare_property_long(zvec_weighted_reranker_ce, "metricType", sizeof("metricType") - 1, 2, ZEND_ACC_PUBLIC);
    zend_declare_property_null(zvec_weighted_reranker_ce, "weights", sizeof("weights") - 1, ZEND_ACC_PUBLIC);
}
