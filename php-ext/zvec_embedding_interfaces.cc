#include "zvec_embedding_interfaces.h"
#include "zvec_exception.h"

extern "C" {
#include "zend_smart_str.h"
#include "ext/json/php_json.h"
}

zend_class_entry *zvec_dense_embedding_ce = nullptr;
zend_class_entry *zvec_sparse_embedding_ce = nullptr;
zend_class_entry *zvec_api_embedding_ce = nullptr;

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_dense_embed, 0, 1, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, input, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_dense_embed_batch, 0, 1, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, inputs, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_dense_get_dimension, 0, 0, IS_LONG, 0)
ZEND_END_ARG_INFO()

static const zend_function_entry zvec_dense_embedding_methods[] = {
    PHP_ABSTRACT_ME(DenseEmbeddingFunction, embed, arginfo_dense_embed)
    PHP_ABSTRACT_ME(DenseEmbeddingFunction, embedBatch, arginfo_dense_embed_batch)
    PHP_ABSTRACT_ME(DenseEmbeddingFunction, getDimension, arginfo_dense_get_dimension)
    PHP_FE_END
};

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_sparse_embed, 0, 1, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, input, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_sparse_embed_batch, 0, 1, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, inputs, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

static const zend_function_entry zvec_sparse_embedding_methods[] = {
    PHP_ABSTRACT_ME(SparseEmbeddingFunction, embed, arginfo_sparse_embed)
    PHP_ABSTRACT_ME(SparseEmbeddingFunction, embedBatch, arginfo_sparse_embed_batch)
    PHP_FE_END
};

PHP_METHOD(ApiEmbeddingFunction, __construct) {
    char *api_key; size_t api_key_len;
    char *base_url = nullptr; size_t base_url_len = 0;
    zend_long timeout = 30;
    char *proxy = nullptr; size_t proxy_len = 0;
    ZEND_PARSE_PARAMETERS_START(1, 4)
        Z_PARAM_STRING(api_key, api_key_len)
        Z_PARAM_OPTIONAL
        Z_PARAM_STRING_OR_NULL(base_url, base_url_len)
        Z_PARAM_LONG(timeout)
        Z_PARAM_STRING_OR_NULL(proxy, proxy_len)
    ZEND_PARSE_PARAMETERS_END();

    zend_update_property_stringl(zvec_api_embedding_ce, Z_OBJ_P(ZEND_THIS), "apiKey", sizeof("apiKey") - 1, api_key, api_key_len);

    if (base_url) {
        zend_update_property_stringl(zvec_api_embedding_ce, Z_OBJ_P(ZEND_THIS), "baseUrl", sizeof("baseUrl") - 1, base_url, base_url_len);
    } else {
        zval retval;
        zend_call_method_with_0_params(Z_OBJ_P(ZEND_THIS), Z_OBJCE_P(ZEND_THIS), nullptr, "getdefaultbaseurl", &retval);
        if (Z_TYPE(retval) == IS_STRING) {
            zend_update_property(zvec_api_embedding_ce, Z_OBJ_P(ZEND_THIS), "baseUrl", sizeof("baseUrl") - 1, &retval);
        }
        zval_ptr_dtor(&retval);
    }

    zend_update_property_long(zvec_api_embedding_ce, Z_OBJ_P(ZEND_THIS), "timeout", sizeof("timeout") - 1, timeout);

    if (proxy) {
        zend_update_property_stringl(zvec_api_embedding_ce, Z_OBJ_P(ZEND_THIS), "proxy", sizeof("proxy") - 1, proxy, proxy_len);
    } else {
        zend_update_property_null(zvec_api_embedding_ce, Z_OBJ_P(ZEND_THIS), "proxy", sizeof("proxy") - 1);
    }
}

PHP_METHOD(ApiEmbeddingFunction, post) {
    char *endpoint; size_t endpoint_len;
    zval *data;
    ZEND_PARSE_PARAMETERS_START(2, 2)
        Z_PARAM_STRING(endpoint, endpoint_len)
        Z_PARAM_ARRAY(data)
    ZEND_PARSE_PARAMETERS_END();

    zval *base_url_zv = zend_read_property(zvec_api_embedding_ce, Z_OBJ_P(ZEND_THIS), "baseUrl", sizeof("baseUrl") - 1, 1, nullptr);
    zval *timeout_zv = zend_read_property(zvec_api_embedding_ce, Z_OBJ_P(ZEND_THIS), "timeout", sizeof("timeout") - 1, 1, nullptr);
    zval *proxy_zv = zend_read_property(zvec_api_embedding_ce, Z_OBJ_P(ZEND_THIS), "proxy", sizeof("proxy") - 1, 1, nullptr);

    zend_string *base_url = Z_STR_P(base_url_zv);
    size_t bu_len = ZSTR_LEN(base_url);
    while (bu_len > 0 && ZSTR_VAL(base_url)[bu_len - 1] == '/') bu_len--;
    size_t ep_start = 0;
    while (ep_start < endpoint_len && endpoint[ep_start] == '/') ep_start++;

    zend_string *url = zend_string_alloc(bu_len + 1 + (endpoint_len - ep_start), 0);
    memcpy(ZSTR_VAL(url), ZSTR_VAL(base_url), bu_len);
    ZSTR_VAL(url)[bu_len] = '/';
    memcpy(ZSTR_VAL(url) + bu_len + 1, endpoint + ep_start, endpoint_len - ep_start);
    ZSTR_VAL(url)[bu_len + 1 + endpoint_len - ep_start] = '\0';
    ZSTR_LEN(url) = bu_len + 1 + (endpoint_len - ep_start);

    smart_str json_buf = {0};
    php_json_encode(&json_buf, data, PHP_JSON_UNESCAPED_UNICODE);
    smart_str_0(&json_buf);

    zval headers_retval;
    zend_call_method_with_0_params(Z_OBJ_P(ZEND_THIS), Z_OBJCE_P(ZEND_THIS), nullptr, "getheaders", &headers_retval);

    zval curl_fn;
    ZVAL_STRING(&curl_fn, "curl_init");
    zval curl_args[1];
    ZVAL_STR(&curl_args[0], url);
    zval ch;
    call_user_function(nullptr, nullptr, &curl_fn, &ch, 1, curl_args);
    zval_ptr_dtor(&curl_fn);

    zval setopt_fn;
    ZVAL_STRING(&setopt_fn, "curl_setopt");

    auto set_opt = [&](zend_long opt, zval *val) {
        zval args[3];
        ZVAL_COPY(&args[0], &ch);
        ZVAL_LONG(&args[1], opt);
        ZVAL_COPY(&args[2], val);
        zval ret;
        call_user_function(nullptr, nullptr, &setopt_fn, &ret, 3, args);
        zval_ptr_dtor(&ret);
        zval_ptr_dtor(&args[0]);
        zval_ptr_dtor(&args[2]);
    };

    zval opt_val;
    ZVAL_TRUE(&opt_val);
    set_opt(19913, &opt_val); // CURLOPT_RETURNTRANSFER

    ZVAL_TRUE(&opt_val);
    set_opt(47, &opt_val); // CURLOPT_POST

    zval postfields;
    ZVAL_STR(&postfields, json_buf.s ? json_buf.s : ZSTR_EMPTY_ALLOC());
    set_opt(10015, &postfields); // CURLOPT_POSTFIELDS
    zval_ptr_dtor(&postfields);

    ZVAL_LONG(&opt_val, Z_LVAL_P(timeout_zv));
    set_opt(13, &opt_val); // CURLOPT_TIMEOUT

    set_opt(10023, &headers_retval); // CURLOPT_HTTPHEADER
    zval_ptr_dtor(&headers_retval);

    if (Z_TYPE_P(proxy_zv) == IS_STRING) {
        set_opt(10004, proxy_zv); // CURLOPT_PROXY
    }

    zval exec_fn;
    ZVAL_STRING(&exec_fn, "curl_exec");
    zval exec_args[1];
    ZVAL_COPY(&exec_args[0], &ch);
    zval response;
    call_user_function(nullptr, nullptr, &exec_fn, &response, 1, exec_args);
    zval_ptr_dtor(&exec_fn);
    zval_ptr_dtor(&exec_args[0]);

    zval getinfo_fn;
    ZVAL_STRING(&getinfo_fn, "curl_getinfo");
    zval getinfo_args[2];
    ZVAL_COPY(&getinfo_args[0], &ch);
    ZVAL_LONG(&getinfo_args[1], 2097154); // CURLINFO_HTTP_CODE
    zval http_code;
    call_user_function(nullptr, nullptr, &getinfo_fn, &http_code, 2, getinfo_args);
    zval_ptr_dtor(&getinfo_fn);
    zval_ptr_dtor(&getinfo_args[0]);

    zval error_fn;
    ZVAL_STRING(&error_fn, "curl_error");
    zval error_args[1];
    ZVAL_COPY(&error_args[0], &ch);
    zval curl_error;
    call_user_function(nullptr, nullptr, &error_fn, &curl_error, 1, error_args);
    zval_ptr_dtor(&error_fn);
    zval_ptr_dtor(&error_args[0]);

    zval close_fn;
    ZVAL_STRING(&close_fn, "curl_close");
    zval close_args[1];
    ZVAL_COPY(&close_args[0], &ch);
    zval close_ret;
    call_user_function(nullptr, nullptr, &close_fn, &close_ret, 1, close_args);
    zval_ptr_dtor(&close_fn);
    zval_ptr_dtor(&close_ret);
    zval_ptr_dtor(&close_args[0]);

    zval_ptr_dtor(&setopt_fn);
    zval_ptr_dtor(&ch);
    zend_string_release(url);

    if (Z_TYPE(curl_error) == IS_STRING && Z_STRLEN(curl_error) > 0) {
        zend_throw_exception_ex(zvec_exception_ce, 0, "HTTP request failed: %s", Z_STRVAL(curl_error));
        zval_ptr_dtor(&curl_error);
        zval_ptr_dtor(&response);
        zval_ptr_dtor(&http_code);
        return;
    }
    zval_ptr_dtor(&curl_error);

    if (Z_TYPE(response) == IS_FALSE) {
        zend_throw_exception(zvec_exception_ce, "HTTP request returned false", 0);
        zval_ptr_dtor(&response);
        zval_ptr_dtor(&http_code);
        return;
    }

    zval decoded;
    php_json_decode(&decoded, Z_STRVAL(response), Z_STRLEN(response), 1, 512);

    zend_long code = Z_LVAL(http_code);
    if (code != 200) {
        const char *err_msg = "Unknown error";
        if (Z_TYPE(decoded) == IS_ARRAY) {
            zval *error = zend_hash_str_find(Z_ARRVAL(decoded), "error", sizeof("error") - 1);
            if (error && Z_TYPE_P(error) == IS_ARRAY) {
                zval *msg = zend_hash_str_find(Z_ARRVAL_P(error), "message", sizeof("message") - 1);
                if (msg && Z_TYPE_P(msg) == IS_STRING) {
                    err_msg = Z_STRVAL_P(msg);
                }
            }
        }
        zend_throw_exception_ex(zvec_exception_ce, (int)code, "API error: %s", err_msg);
        zval_ptr_dtor(&decoded);
        zval_ptr_dtor(&response);
        zval_ptr_dtor(&http_code);
        return;
    }

    zval_ptr_dtor(&response);
    zval_ptr_dtor(&http_code);

    if (Z_TYPE(decoded) != IS_ARRAY) {
        zend_throw_exception(zvec_exception_ce, "Invalid JSON response", 0);
        zval_ptr_dtor(&decoded);
        return;
    }

    RETURN_ZVAL(&decoded, 0, 0);
}

ZEND_BEGIN_ARG_INFO_EX(arginfo_api___construct, 0, 0, 1)
    ZEND_ARG_TYPE_INFO(0, apiKey, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, baseUrl, IS_STRING, 1, "null")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, timeout, IS_LONG, 0, "30")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, proxy, IS_STRING, 1, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_api_post, 0, 2, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, endpoint, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO(0, data, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_api_get_default_base_url, 0, 0, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_api_get_headers, 0, 0, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

static const zend_function_entry zvec_api_embedding_methods[] = {
    PHP_ME(ApiEmbeddingFunction, __construct, arginfo_api___construct, ZEND_ACC_PUBLIC)
    PHP_ME(ApiEmbeddingFunction, post, arginfo_api_post, ZEND_ACC_PROTECTED)
    ZEND_ABSTRACT_ME_WITH_FLAGS(ApiEmbeddingFunction, getDefaultBaseUrl, arginfo_api_get_default_base_url, ZEND_ACC_ABSTRACT | ZEND_ACC_PROTECTED)
    ZEND_ABSTRACT_ME_WITH_FLAGS(ApiEmbeddingFunction, getHeaders, arginfo_api_get_headers, ZEND_ACC_ABSTRACT | ZEND_ACC_PROTECTED)
    PHP_FE_END
};

void zvec_register_embedding_interfaces(INIT_FUNC_ARGS) {
    zend_class_entry ce;

    INIT_CLASS_ENTRY(ce, "DenseEmbeddingFunction", zvec_dense_embedding_methods);
    zvec_dense_embedding_ce = zend_register_internal_interface(&ce);

    INIT_CLASS_ENTRY(ce, "SparseEmbeddingFunction", zvec_sparse_embedding_methods);
    zvec_sparse_embedding_ce = zend_register_internal_interface(&ce);

    INIT_CLASS_ENTRY(ce, "ApiEmbeddingFunction", zvec_api_embedding_methods);
    zvec_api_embedding_ce = zend_register_internal_class(&ce);
    zvec_api_embedding_ce->ce_flags |= ZEND_ACC_ABSTRACT;

    zend_declare_property_null(zvec_api_embedding_ce, "apiKey", sizeof("apiKey") - 1, ZEND_ACC_PROTECTED);
    zend_declare_property_null(zvec_api_embedding_ce, "baseUrl", sizeof("baseUrl") - 1, ZEND_ACC_PROTECTED);
    zend_declare_property_long(zvec_api_embedding_ce, "timeout", sizeof("timeout") - 1, 30, ZEND_ACC_PROTECTED);
    zend_declare_property_null(zvec_api_embedding_ce, "proxy", sizeof("proxy") - 1, ZEND_ACC_PROTECTED);
}
