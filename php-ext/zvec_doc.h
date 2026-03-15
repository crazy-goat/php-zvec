#ifndef ZVEC_DOC_H
#define ZVEC_DOC_H

#include "php_zvec.h"

#pragma push_macro("IS_NULL")
#undef IS_NULL
#include <zvec/db/doc.h>
#pragma pop_macro("IS_NULL")

struct zvec_doc_object {
    zvec::Doc *doc;
    bool owns_handle;
    zend_object std;
};

static inline zvec_doc_object *zvec_doc_from_obj(zend_object *obj) {
    return reinterpret_cast<zvec_doc_object *>(
        reinterpret_cast<char *>(obj) - XtOffsetOf(zvec_doc_object, std));
}

#define Z_ZVEC_DOC_P(zv) zvec_doc_from_obj(Z_OBJ_P(zv))

zend_object *zvec_doc_create_from_native(zvec::Doc *doc, bool owns);

#endif
