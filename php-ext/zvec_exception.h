#ifndef ZVEC_EXCEPTION_H
#define ZVEC_EXCEPTION_H

#include "php_zvec.h"

void zvec_throw_exception(int code, const char *format, ...);

#endif
