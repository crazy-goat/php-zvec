#include "zvec_exception.h"
#include <cstdarg>

extern "C" {
#include "ext/spl/spl_exceptions.h"
}

zend_class_entry *zvec_exception_ce = nullptr;

void zvec_throw_exception(int code, const char *format, ...) {
    char buf[512];
    va_list args;
    va_start(args, format);
    vsnprintf(buf, sizeof(buf), format, args);
    va_end(args);
    zend_throw_exception_ex(zvec_exception_ce, code, "%s", buf);
}

void zvec_register_exception(INIT_FUNC_ARGS) {
    zend_class_entry ce;
    INIT_CLASS_ENTRY(ce, "ZVecException", nullptr);
    zvec_exception_ce = zend_register_internal_class_ex(&ce, spl_ce_RuntimeException);
}
