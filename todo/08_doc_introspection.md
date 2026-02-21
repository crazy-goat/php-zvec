# Doc Introspection Methods

## Priority: LOW

## Status: TODO

## Description

Python SDK's `Doc` class has introspection methods to check which fields/vectors are present.

## Missing methods

| Method | Returns | Description |
|--------|---------|-------------|
| `has_field(name)` | bool | Check if scalar field exists |
| `has_vector(name)` | bool | Check if vector field exists |
| `field_names()` | list[str] | All scalar field names |
| `vector_names()` | list[str] | All vector field names |

## C++ API

Check `zvec/src/include/zvec/db/doc.h`:
- Does `Doc` have methods to enumerate fields?
- Or is this Python SDK convenience built on top of try/catch get()?

## Changes needed

### Option A: C++ API supports field enumeration
- Add `zvec_doc_has_field`, `zvec_doc_has_vector`, `zvec_doc_field_names`, `zvec_doc_vector_names` to C wrapper
- Implement in PHP

### Option B: Pure PHP implementation
- `has_field()` → try `get*()`, return true if not null
- `field_names()` / `vector_names()` → would need C++ support to enumerate

### php/ZVec.php (ZVecDoc)
- Add `hasField(string $name): bool`
- Add `hasVector(string $name): bool`
- Add `fieldNames(): array`
- Add `vectorNames(): array`

### Notes
- `has_field` / `has_vector` can be implemented in PHP with try/catch on existing getters
- `field_names` / `vector_names` likely need C++ API support to enumerate stored fields
