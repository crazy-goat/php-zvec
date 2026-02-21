# Schema Evolution Tests

## Priority: MEDIUM

## Status: DONE

## Difficulty: 2/5 ⭐⭐

## Description

Test DDL operations: column add, rename, drop, alter, and index management.

Part of: Test Suite Migration (split from task #18)

Based on: https://zvec.org/en/docs/collections/schema-evolution/

---

## Test: test_column_add.phpt ✅

### Coverage
- addColumnInt64 with default value
- addColumnFloat with default value
- addColumnDouble with default value
- Verify default values applied to existing docs
- Schema verification after add

---

## Test: test_column_rename.phpt ✅

### Coverage
- renameColumn existing field
- Verify data accessible under new name
- Try rename non-existent column (should fail)
- Try rename to existing name (should fail)

---

## Test: test_column_drop.phpt ✅

### Coverage
- dropColumn existing field
- Verify column removed from schema
- Try drop non-existent column (should fail)
- Test re-adding dropped column with same name

---

## Test: test_alter_column.phpt ✅

### Status: DONE (exists as tests/test_alter_column.phpt)

### Coverage
- alterColumn rename only
- alterColumn type change only (INT64 -> FLOAT)
- alterColumn combined (type + rename in steps)
- Try invalid type changes (should fail)

---

## Test: test_index_invert.phpt ✅

### Coverage
- createInvertIndex on string field
- createInvertIndex with range optimization
- dropIndex on inverted index
- Query using filter after index creation

---

## Test: test_index_vector.phpt ✅

### Coverage
- createFlatIndex on vector field
- Switch to HNSW index
- Switch back to Flat
- dropIndex and recreate
- Query works with all index types

---

## Test: test_index_quantized.phpt ✅

### Coverage
- createHnswIndex with QUANTIZE_INT8
- createHnswIndex with QUANTIZE_FP16
- createFlatIndex with QUANTIZE_INT8
- Verify query accuracy with quantization
- Compare results between quantized and non-quantized

### Dependencies
- Task #02 (quantizeType support) - DONE

---

## Notes

- All 6 test files created in tests/ directory
- DDL operations often require flush() before they take effect
- Vector indexes require optimize() after creation
- Test directories use uniqid() for uniqueness and cleanup with try-finally
- All tests passing: 6/6 PASS
