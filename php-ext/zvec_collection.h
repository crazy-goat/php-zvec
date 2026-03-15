#ifndef ZVEC_COLLECTION_H
#define ZVEC_COLLECTION_H

#include "php_zvec.h"

#pragma push_macro("IS_NULL")
#undef IS_NULL
#include <zvec/db/collection.h>
#pragma pop_macro("IS_NULL")

struct zvec_collection_object {
    zvec::Collection::Ptr collection;
    bool closed;
    zend_object std;
};

static inline zvec_collection_object *zvec_collection_from_obj(zend_object *obj) {
    return reinterpret_cast<zvec_collection_object *>(
        reinterpret_cast<char *>(obj) - XtOffsetOf(zvec_collection_object, std));
}

#define Z_ZVEC_COLLECTION_P(zv) zvec_collection_from_obj(Z_OBJ_P(zv))

#endif
