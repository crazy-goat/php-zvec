#include "zvec_qwen_embedding.h"
#include "zvec_exception.h"

zend_class_entry *zvec_qwen_embedding_ce = nullptr;

PHP_METHOD(QwenDenseEmbedding, __construct) {
    char *api_key; size_t api_key_len;
    char *model = nullptr; size_t model_len = 0;
    char *base_url = nullptr; size_t base_url_len = 0;
    zend_long timeout = 30;
    char *proxy = nullptr; size_t proxy_len = 0;
    ZEND_PARSE_PARAMETERS_START(1, 5)
        Z_PARAM_STRING(api_key, api_key_len)
        Z_PARAM_OPTIONAL
        Z_PARAM_STRING(model, model_len)
        Z_PARAM_STRING_OR_NULL(base_url, base_url_len)
        Z_PARAM_LONG(timeout)
        Z_PARAM_STRING_OR_NULL(proxy, proxy_len)
    ZEND_PARSE_PARAMETERS_END();

    zend_update_property_stringl(zvec_api_embedding_ce, Z_OBJ_P(ZEND_THIS), "apiKey", sizeof("apiKey") - 1, api_key, api_key_len);
    if (base_url) {
        zend_update_property_stringl(zvec_api_embedding_ce, Z_OBJ_P(ZEND_THIS), "baseUrl", sizeof("baseUrl") - 1, base_url, base_url_len);
    } else {
        zend_update_property_string(zvec_api_embedding_ce, Z_OBJ_P(ZEND_THIS), "baseUrl", sizeof("baseUrl") - 1, "https://dashscope.aliyuncs.com/api/v1");
    }
    zend_update_property_long(zvec_api_embedding_ce, Z_OBJ_P(ZEND_THIS), "timeout", sizeof("timeout") - 1, timeout);
    if (proxy) {
        zend_update_property_stringl(zvec_api_embedding_ce, Z_OBJ_P(ZEND_THIS), "proxy", sizeof("proxy") - 1, proxy, proxy_len);
    } else {
        zend_update_property_null(zvec_api_embedding_ce, Z_OBJ_P(ZEND_THIS), "proxy", sizeof("proxy") - 1);
    }

    if (model && model_len > 0) {
        zend_update_property_stringl(zvec_qwen_embedding_ce, Z_OBJ_P(ZEND_THIS), "model", sizeof("model") - 1, model, model_len);
    } else {
        zend_update_property_string(zvec_qwen_embedding_ce, Z_OBJ_P(ZEND_THIS), "model", sizeof("model") - 1, "text-embedding-v4");
    }
}

PHP_METHOD(QwenDenseEmbedding, getDefaultBaseUrl) {
    ZEND_PARSE_PARAMETERS_NONE();
    RETURN_STRING("https://dashscope.aliyuncs.com/api/v1");
}

PHP_METHOD(QwenDenseEmbedding, getHeaders) {
    ZEND_PARSE_PARAMETERS_NONE();

    zval *api_key = zend_read_property(zvec_api_embedding_ce, Z_OBJ_P(ZEND_THIS), "apiKey", sizeof("apiKey") - 1, 1, nullptr);

    array_init(return_value);
    zend_string *auth = zend_strpprintf(0, "Authorization: Bearer %s", Z_STRVAL_P(api_key));
    add_next_index_str(return_value, auth);
    add_next_index_string(return_value, "Content-Type: application/json");
}

PHP_METHOD(QwenDenseEmbedding, getDimension) {
    ZEND_PARSE_PARAMETERS_NONE();

    zval *model = zend_read_property(zvec_qwen_embedding_ce, Z_OBJ_P(ZEND_THIS), "model", sizeof("model") - 1, 1, nullptr);
    if (Z_TYPE_P(model) == IS_STRING) {
        if (strcmp(Z_STRVAL_P(model), "text-embedding-v4") == 0) RETURN_LONG(1792);
        if (strcmp(Z_STRVAL_P(model), "text-embedding-v3") == 0) RETURN_LONG(1024);
    }
    RETURN_LONG(1536);
}

PHP_METHOD(QwenDenseEmbedding, embed) {
    char *input; size_t input_len;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING(input, input_len)
    ZEND_PARSE_PARAMETERS_END();

    zval inputs;
    array_init(&inputs);
    add_next_index_stringl(&inputs, input, input_len);

    zval batch_result;
    zend_call_method_with_1_params(Z_OBJ_P(ZEND_THIS), Z_OBJCE_P(ZEND_THIS), nullptr, "embedbatch", &batch_result, &inputs);
    zval_ptr_dtor(&inputs);

    if (EG(exception)) {
        zval_ptr_dtor(&batch_result);
        return;
    }

    if (Z_TYPE(batch_result) == IS_ARRAY) {
        zval *first = zend_hash_index_find(Z_ARRVAL(batch_result), 0);
        if (first) {
            RETVAL_ZVAL(first, 1, 0);
            zval_ptr_dtor(&batch_result);
            return;
        }
    }
    zval_ptr_dtor(&batch_result);
    array_init(return_value);
}

PHP_METHOD(QwenDenseEmbedding, embedBatch) {
    zval *inputs;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_ARRAY(inputs)
    ZEND_PARSE_PARAMETERS_END();

    if (zend_hash_num_elements(Z_ARRVAL_P(inputs)) == 0) {
        array_init(return_value);
        return;
    }

    if (zend_hash_num_elements(Z_ARRVAL_P(inputs)) > 25) {
        zend_throw_exception(zvec_exception_ce, "Maximum batch size is 25 inputs for DashScope", 0);
        return;
    }

    zval *model = zend_read_property(zvec_qwen_embedding_ce, Z_OBJ_P(ZEND_THIS), "model", sizeof("model") - 1, 1, nullptr);

    zval texts;
    array_init(&texts);
    zval *input_str;
    ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(inputs), input_str) {
        zval text_obj;
        array_init(&text_obj);
        zval text_copy;
        ZVAL_COPY(&text_copy, input_str);
        zend_hash_str_update(Z_ARRVAL(text_obj), "text", sizeof("text") - 1, &text_copy);
        add_next_index_zval(&texts, &text_obj);
    } ZEND_HASH_FOREACH_END();

    zval input_wrapper;
    array_init(&input_wrapper);
    zend_hash_str_update(Z_ARRVAL(input_wrapper), "texts", sizeof("texts") - 1, &texts);

    zval payload;
    array_init(&payload);
    Z_ADDREF_P(model);
    zend_hash_str_update(Z_ARRVAL(payload), "model", sizeof("model") - 1, model);
    zend_hash_str_update(Z_ARRVAL(payload), "input", sizeof("input") - 1, &input_wrapper);

    zval endpoint;
    ZVAL_STRING(&endpoint, "/services/embeddings/text-embedding");
    zval response;
    zend_call_method_with_2_params(Z_OBJ_P(ZEND_THIS), Z_OBJCE_P(ZEND_THIS), nullptr, "post", &response, &endpoint, &payload);
    zval_ptr_dtor(&endpoint);
    zval_ptr_dtor(&payload);

    if (EG(exception)) {
        zval_ptr_dtor(&response);
        return;
    }

    if (Z_TYPE(response) != IS_ARRAY) {
        zend_throw_exception(zvec_exception_ce, "Invalid response format", 0);
        zval_ptr_dtor(&response);
        return;
    }

    zval *output = zend_hash_str_find(Z_ARRVAL(response), "output", sizeof("output") - 1);
    if (!output || Z_TYPE_P(output) != IS_ARRAY) {
        zend_throw_exception(zvec_exception_ce, "Invalid response format: missing embeddings array", 0);
        zval_ptr_dtor(&response);
        return;
    }

    zval *embeddings = zend_hash_str_find(Z_ARRVAL_P(output), "embeddings", sizeof("embeddings") - 1);
    if (!embeddings || Z_TYPE_P(embeddings) != IS_ARRAY) {
        zend_throw_exception(zvec_exception_ce, "Invalid response format: missing embeddings array", 0);
        zval_ptr_dtor(&response);
        return;
    }

    array_init(return_value);
    zval *item;
    ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(embeddings), item) {
        if (Z_TYPE_P(item) != IS_ARRAY) continue;
        zval *embedding = zend_hash_str_find(Z_ARRVAL_P(item), "embedding", sizeof("embedding") - 1);
        if (!embedding || Z_TYPE_P(embedding) != IS_ARRAY) {
            zend_throw_exception(zvec_exception_ce, "Invalid response format: missing embedding array", 0);
            zval_ptr_dtor(&response);
            return;
        }

        zval float_arr;
        array_init(&float_arr);
        zval *v;
        ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(embedding), v) {
            zval fv;
            ZVAL_DOUBLE(&fv, zval_get_double(v));
            add_next_index_zval(&float_arr, &fv);
        } ZEND_HASH_FOREACH_END();
        add_next_index_zval(return_value, &float_arr);
    } ZEND_HASH_FOREACH_END();

    zval_ptr_dtor(&response);
}

PHP_METHOD(QwenDenseEmbedding, getModel) {
    ZEND_PARSE_PARAMETERS_NONE();
    zval *model = zend_read_property(zvec_qwen_embedding_ce, Z_OBJ_P(ZEND_THIS), "model", sizeof("model") - 1, 1, nullptr);
    RETURN_ZVAL(model, 1, 0);
}

ZEND_BEGIN_ARG_INFO_EX(arginfo_qwen___construct, 0, 0, 1)
    ZEND_ARG_TYPE_INFO(0, apiKey, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, model, IS_STRING, 0, "\"text-embedding-v4\"")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, baseUrl, IS_STRING, 1, "null")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, timeout, IS_LONG, 0, "30")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, proxy, IS_STRING, 1, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_qwen_embed, 0, 1, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, input, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_qwen_embed_batch, 0, 1, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, inputs, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_qwen_get_dimension, 0, 0, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_qwen_get_default_base_url, 0, 0, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_qwen_get_headers, 0, 0, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_qwen_get_model, 0, 0, IS_STRING, 0)
ZEND_END_ARG_INFO()

static const zend_function_entry zvec_qwen_embedding_methods[] = {
    PHP_ME(QwenDenseEmbedding, __construct, arginfo_qwen___construct, ZEND_ACC_PUBLIC)
    PHP_ME(QwenDenseEmbedding, getDefaultBaseUrl, arginfo_qwen_get_default_base_url, ZEND_ACC_PROTECTED)
    PHP_ME(QwenDenseEmbedding, getHeaders, arginfo_qwen_get_headers, ZEND_ACC_PROTECTED)
    PHP_ME(QwenDenseEmbedding, getDimension, arginfo_qwen_get_dimension, ZEND_ACC_PUBLIC)
    PHP_ME(QwenDenseEmbedding, embed, arginfo_qwen_embed, ZEND_ACC_PUBLIC)
    PHP_ME(QwenDenseEmbedding, embedBatch, arginfo_qwen_embed_batch, ZEND_ACC_PUBLIC)
    PHP_ME(QwenDenseEmbedding, getModel, arginfo_qwen_get_model, ZEND_ACC_PUBLIC)
    PHP_FE_END
};

void zvec_register_qwen_embedding(INIT_FUNC_ARGS) {
    zend_class_entry ce;
    INIT_CLASS_ENTRY(ce, "QwenDenseEmbedding", zvec_qwen_embedding_methods);
    zvec_qwen_embedding_ce = zend_register_internal_class_ex(&ce, zvec_api_embedding_ce);
    zend_class_implements(zvec_qwen_embedding_ce, 1, zvec_dense_embedding_ce);

    zend_declare_class_constant_string(zvec_qwen_embedding_ce, "MODEL_V4", sizeof("MODEL_V4") - 1, "text-embedding-v4");
    zend_declare_class_constant_string(zvec_qwen_embedding_ce, "MODEL_V3", sizeof("MODEL_V3") - 1, "text-embedding-v3");
    zend_declare_class_constant_string(zvec_qwen_embedding_ce, "MODEL_V2", sizeof("MODEL_V2") - 1, "text-embedding-v2");
    zend_declare_class_constant_string(zvec_qwen_embedding_ce, "MODEL_V1", sizeof("MODEL_V1") - 1, "text-embedding-v1");

    zend_declare_property_string(zvec_qwen_embedding_ce, "model", sizeof("model") - 1, "text-embedding-v4", ZEND_ACC_PRIVATE);
}
