#include "zvec_openai_embedding.h"
#include "zvec_exception.h"

zend_class_entry *zvec_openai_embedding_ce = nullptr;

PHP_METHOD(OpenAIDenseEmbedding, __construct) {
    char *api_key; size_t api_key_len;
    char *model = nullptr; size_t model_len = 0;
    zval *dimensions = nullptr;
    char *base_url = nullptr; size_t base_url_len = 0;
    zend_long timeout = 30;
    char *proxy = nullptr; size_t proxy_len = 0;
    ZEND_PARSE_PARAMETERS_START(1, 6)
        Z_PARAM_STRING(api_key, api_key_len)
        Z_PARAM_OPTIONAL
        Z_PARAM_STRING(model, model_len)
        Z_PARAM_ZVAL_OR_NULL(dimensions)
        Z_PARAM_STRING_OR_NULL(base_url, base_url_len)
        Z_PARAM_LONG(timeout)
        Z_PARAM_STRING_OR_NULL(proxy, proxy_len)
    ZEND_PARSE_PARAMETERS_END();

    zval parent_args[4];
    ZVAL_STRINGL(&parent_args[0], api_key, api_key_len);
    if (base_url) {
        ZVAL_STRINGL(&parent_args[1], base_url, base_url_len);
    } else {
        ZVAL_NULL(&parent_args[1]);
    }
    ZVAL_LONG(&parent_args[2], timeout);
    if (proxy) {
        ZVAL_STRINGL(&parent_args[3], proxy, proxy_len);
    } else {
        ZVAL_NULL(&parent_args[3]);
    }
    zend_call_method_with_0_params(Z_OBJ_P(ZEND_THIS), zvec_api_embedding_ce, nullptr, "__construct", nullptr);

    zend_update_property_stringl(zvec_api_embedding_ce, Z_OBJ_P(ZEND_THIS), "apiKey", sizeof("apiKey") - 1, api_key, api_key_len);
    if (base_url) {
        zend_update_property_stringl(zvec_api_embedding_ce, Z_OBJ_P(ZEND_THIS), "baseUrl", sizeof("baseUrl") - 1, base_url, base_url_len);
    } else {
        zend_update_property_string(zvec_api_embedding_ce, Z_OBJ_P(ZEND_THIS), "baseUrl", sizeof("baseUrl") - 1, "https://api.openai.com/v1");
    }
    zend_update_property_long(zvec_api_embedding_ce, Z_OBJ_P(ZEND_THIS), "timeout", sizeof("timeout") - 1, timeout);
    if (proxy) {
        zend_update_property_stringl(zvec_api_embedding_ce, Z_OBJ_P(ZEND_THIS), "proxy", sizeof("proxy") - 1, proxy, proxy_len);
    } else {
        zend_update_property_null(zvec_api_embedding_ce, Z_OBJ_P(ZEND_THIS), "proxy", sizeof("proxy") - 1);
    }

    for (int i = 0; i < 4; i++) zval_ptr_dtor(&parent_args[i]);

    if (model && model_len > 0) {
        zend_update_property_stringl(zvec_openai_embedding_ce, Z_OBJ_P(ZEND_THIS), "model", sizeof("model") - 1, model, model_len);
    } else {
        zend_update_property_string(zvec_openai_embedding_ce, Z_OBJ_P(ZEND_THIS), "model", sizeof("model") - 1, "text-embedding-3-small");
    }

    if (dimensions && Z_TYPE_P(dimensions) == IS_LONG) {
        zend_update_property_long(zvec_openai_embedding_ce, Z_OBJ_P(ZEND_THIS), "dimensions", sizeof("dimensions") - 1, Z_LVAL_P(dimensions));
    } else {
        zend_update_property_null(zvec_openai_embedding_ce, Z_OBJ_P(ZEND_THIS), "dimensions", sizeof("dimensions") - 1);
    }
}

PHP_METHOD(OpenAIDenseEmbedding, getDefaultBaseUrl) {
    ZEND_PARSE_PARAMETERS_NONE();
    RETURN_STRING("https://api.openai.com/v1");
}

PHP_METHOD(OpenAIDenseEmbedding, getHeaders) {
    ZEND_PARSE_PARAMETERS_NONE();

    zval *api_key = zend_read_property(zvec_api_embedding_ce, Z_OBJ_P(ZEND_THIS), "apiKey", sizeof("apiKey") - 1, 1, nullptr);

    array_init(return_value);
    zend_string *auth = zend_strpprintf(0, "Authorization: Bearer %s", Z_STRVAL_P(api_key));
    add_next_index_str(return_value, auth);
    add_next_index_string(return_value, "Content-Type: application/json");
}

PHP_METHOD(OpenAIDenseEmbedding, getDimension) {
    ZEND_PARSE_PARAMETERS_NONE();

    zval *dims = zend_read_property(zvec_openai_embedding_ce, Z_OBJ_P(ZEND_THIS), "dimensions", sizeof("dimensions") - 1, 1, nullptr);
    if (Z_TYPE_P(dims) == IS_LONG) {
        RETURN_LONG(Z_LVAL_P(dims));
    }

    zval *model = zend_read_property(zvec_openai_embedding_ce, Z_OBJ_P(ZEND_THIS), "model", sizeof("model") - 1, 1, nullptr);
    if (Z_TYPE_P(model) == IS_STRING) {
        if (strcmp(Z_STRVAL_P(model), "text-embedding-3-large") == 0) {
            RETURN_LONG(3072);
        }
    }
    RETURN_LONG(1536);
}

PHP_METHOD(OpenAIDenseEmbedding, embed) {
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

PHP_METHOD(OpenAIDenseEmbedding, embedBatch) {
    zval *inputs;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_ARRAY(inputs)
    ZEND_PARSE_PARAMETERS_END();

    if (zend_hash_num_elements(Z_ARRVAL_P(inputs)) == 0) {
        array_init(return_value);
        return;
    }

    if (zend_hash_num_elements(Z_ARRVAL_P(inputs)) > 2048) {
        zend_throw_exception(zvec_exception_ce, "Maximum batch size is 2048 inputs", 0);
        return;
    }

    zval *model = zend_read_property(zvec_openai_embedding_ce, Z_OBJ_P(ZEND_THIS), "model", sizeof("model") - 1, 1, nullptr);
    zval *dims = zend_read_property(zvec_openai_embedding_ce, Z_OBJ_P(ZEND_THIS), "dimensions", sizeof("dimensions") - 1, 1, nullptr);

    zval payload;
    array_init(&payload);
    Z_ADDREF_P(model);
    zend_hash_str_update(Z_ARRVAL(payload), "model", sizeof("model") - 1, model);
    Z_ADDREF_P(inputs);
    zend_hash_str_update(Z_ARRVAL(payload), "input", sizeof("input") - 1, inputs);

    if (Z_TYPE_P(dims) == IS_LONG) {
        zval d;
        ZVAL_LONG(&d, Z_LVAL_P(dims));
        zend_hash_str_update(Z_ARRVAL(payload), "dimensions", sizeof("dimensions") - 1, &d);
    }

    zval endpoint;
    ZVAL_STRING(&endpoint, "/embeddings");
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

    zval *data = zend_hash_str_find(Z_ARRVAL(response), "data", sizeof("data") - 1);
    if (!data || Z_TYPE_P(data) != IS_ARRAY) {
        zend_throw_exception(zvec_exception_ce, "Invalid response format: missing data array", 0);
        zval_ptr_dtor(&response);
        return;
    }

    array_init(return_value);
    zval *item;
    ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(data), item) {
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

PHP_METHOD(OpenAIDenseEmbedding, getModel) {
    ZEND_PARSE_PARAMETERS_NONE();
    zval *model = zend_read_property(zvec_openai_embedding_ce, Z_OBJ_P(ZEND_THIS), "model", sizeof("model") - 1, 1, nullptr);
    RETURN_ZVAL(model, 1, 0);
}

ZEND_BEGIN_ARG_INFO_EX(arginfo_openai___construct, 0, 0, 1)
    ZEND_ARG_TYPE_INFO(0, apiKey, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, model, IS_STRING, 0, "\"text-embedding-3-small\"")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, dimensions, IS_LONG, 1, "null")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, baseUrl, IS_STRING, 1, "null")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, timeout, IS_LONG, 0, "30")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, proxy, IS_STRING, 1, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_openai_embed, 0, 1, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, input, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_openai_embed_batch, 0, 1, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, inputs, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_openai_get_dimension, 0, 0, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_openai_get_default_base_url, 0, 0, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_openai_get_headers, 0, 0, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_openai_get_model, 0, 0, IS_STRING, 0)
ZEND_END_ARG_INFO()

static const zend_function_entry zvec_openai_embedding_methods[] = {
    PHP_ME(OpenAIDenseEmbedding, __construct, arginfo_openai___construct, ZEND_ACC_PUBLIC)
    PHP_ME(OpenAIDenseEmbedding, getDefaultBaseUrl, arginfo_openai_get_default_base_url, ZEND_ACC_PROTECTED)
    PHP_ME(OpenAIDenseEmbedding, getHeaders, arginfo_openai_get_headers, ZEND_ACC_PROTECTED)
    PHP_ME(OpenAIDenseEmbedding, getDimension, arginfo_openai_get_dimension, ZEND_ACC_PUBLIC)
    PHP_ME(OpenAIDenseEmbedding, embed, arginfo_openai_embed, ZEND_ACC_PUBLIC)
    PHP_ME(OpenAIDenseEmbedding, embedBatch, arginfo_openai_embed_batch, ZEND_ACC_PUBLIC)
    PHP_ME(OpenAIDenseEmbedding, getModel, arginfo_openai_get_model, ZEND_ACC_PUBLIC)
    PHP_FE_END
};

void zvec_register_openai_embedding(INIT_FUNC_ARGS) {
    zend_class_entry ce;
    INIT_CLASS_ENTRY(ce, "OpenAIDenseEmbedding", zvec_openai_embedding_methods);
    zvec_openai_embedding_ce = zend_register_internal_class_ex(&ce, zvec_api_embedding_ce);
    zend_class_implements(zvec_openai_embedding_ce, 1, zvec_dense_embedding_ce);

    zend_declare_class_constant_string(zvec_openai_embedding_ce, "MODEL_SMALL", sizeof("MODEL_SMALL") - 1, "text-embedding-3-small");
    zend_declare_class_constant_string(zvec_openai_embedding_ce, "MODEL_LARGE", sizeof("MODEL_LARGE") - 1, "text-embedding-3-large");
    zend_declare_class_constant_string(zvec_openai_embedding_ce, "MODEL_ADA", sizeof("MODEL_ADA") - 1, "text-embedding-ada-002");

    zend_declare_property_string(zvec_openai_embedding_ce, "model", sizeof("model") - 1, "text-embedding-3-small", ZEND_ACC_PRIVATE);
    zend_declare_property_null(zvec_openai_embedding_ce, "dimensions", sizeof("dimensions") - 1, ZEND_ACC_PRIVATE);
}
