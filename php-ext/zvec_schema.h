#ifndef ZVEC_SCHEMA_H
#define ZVEC_SCHEMA_H

#include "php_zvec.h"

#pragma push_macro("IS_NULL")
#undef IS_NULL
#include <zvec/db/schema.h>
#pragma pop_macro("IS_NULL")

struct zvec_schema_object {
    zvec::CollectionSchema *schema;
    zend_object std;
};

static inline zvec_schema_object *zvec_schema_from_obj(zend_object *obj) {
    return reinterpret_cast<zvec_schema_object *>(
        reinterpret_cast<char *>(obj) - XtOffsetOf(zvec_schema_object, std));
}

#define Z_ZVEC_SCHEMA_P(zv) zvec_schema_from_obj(Z_OBJ_P(zv))

zvec::CollectionSchema *zvec_schema_get_native(zval *zv);

#endif
