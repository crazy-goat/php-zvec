# Doc Introspection Methods

## Priority: LOW

## Status: ✅ DONE

## Implementation

All methods implemented using C++ API (not PHP try/catch):

### FFI Layer (ffi/zvec_ffi.h/.cc)
- `zvec_doc_has_field(doc, field)` - Check if field exists
- `zvec_doc_has_vector(doc, field)` - Check if field is FP32 vector
- `zvec_doc_field_names(doc, buf, buf_size)` - Get scalar field names
- `zvec_doc_vector_names(doc, buf, buf_size)` - Get vector field names

### PHP Layer (php/ZVec.php)
- `ZVecDoc::hasField(string $name): bool`
- `ZVecDoc::hasVector(string $name): bool`
- `ZVecDoc::fieldNames(): string[]`
- `ZVecDoc::vectorNames(): string[]`

## Test

`tests/bug_0003_doc_introspection.php` - Full test coverage including:
- Testing on newly created docs
- Testing on retrieved docs from collection
- Verification of scalar vs vector field detection

## Notes

C++ API `Doc` class provides:
- `field_names()` - returns all field names
- `has(field)` - checks if field exists
- `get_field<T>(field)` - template getter for type checking

Vector detection is done by checking if field returns `std::vector<float>` using `get_field<std::vector<float>>()`. This means only FP32 vectors are recognized (consistent with current FFI limitations).
