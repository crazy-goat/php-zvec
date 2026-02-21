# Document Operations Tests

## Priority: LOW

## Status: DONE

## Difficulty: 1/5 ⭐

## Description

Test document field access and introspection methods.

Both tests now exist in `tests/` directory.

---

## Test: test_doc_field_access.phpt

### Status: ✅ DONE

### Coverage
- set/get Int64, Float, Double, String
- set/get Vector Fp32
- get non-existent field (should return null or false)
- Test field type coercion behavior

### Methods to test
- ZVecDoc::setInt64() / getInt64()
- ZVecDoc::setFloat() / getFloat()
- ZVecDoc::setDouble() / getDouble()
- ZVecDoc::setString() / getString()
- ZVecDoc::setVectorFp32() / getVectorFp32()

---

## Test: test_doc_introspection.php

### Status: ✅ DONE (exists as tests/test_doc_introspection.php)

### Coverage
- hasField() - exists and non-existent
- hasVector() - vector field vs scalar field
- fieldNames() - list all scalar fields
- vectorNames() - list all vector fields

### Methods to test
- ZVecDoc::hasField()
- ZVecDoc::hasVector()
- ZVecDoc::fieldNames()
- ZVecDoc::vectorNames()

---

## Notes

- Document fields are typed - wrong type access returns null/false
- Vector fields have special handling (float arrays)
- Introspection useful for debugging and generic document handling
