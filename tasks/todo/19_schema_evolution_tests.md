# Schema Evolution Tests

## Priority: MEDIUM

## Status: TODO

## Difficulty: 2/5 ⭐⭐

## Description

Test DDL operations: column add, rename, drop, alter, and index management.

Part of: Test Suite Migration (split from task #18)

Based on: https://zvec.org/en/docs/collections/schema-evolution/

---

## Test: test_column_add.php

### Coverage
- addColumnInt64 with default value
- addColumnFloat with default value
- addColumnDouble with default value
- Verify default values applied to existing docs
- Test add column to non-existent collection (should fail)

---

## Test: test_column_rename.php

### Coverage
- renameColumn existing field
- Verify data accessible under new name
- Try rename non-existent column (should fail)
- Try rename to existing name (should fail)

---

## Test: test_column_drop.php

### Coverage
- dropColumn existing field
- Verify data no longer accessible
- Try drop non-existent column (should fail)
- Test drop vector field (should fail - vectors have index)

---

## Test: test_alter_column.php

### Status: ✅ DONE (exists as tests/test_alter_column.php)

### Coverage
- alterColumn rename only
- alterColumn type change only (INT64 -> FLOAT)
- alterColumn combined (type + rename in steps)
- Try invalid type changes (should fail)

---

## Test: test_index_invert.php

### Coverage
- createInvertIndex on string field
- createInvertIndex with range optimization
- dropIndex on inverted index
- Query using filter after index creation

---

## Test: test_index_vector.php

### Coverage
- createFlatIndex on vector field
- Switch to HNSW index
- Switch back to Flat
- dropIndex and recreate
- Query performance comparison (smoke test)

---

## Test: test_index_quantized.php

### Coverage
- createHnswIndex with QUANTIZE_INT8
- createHnswIndex with QUANTIZE_FP16
- createFlatIndex with QUANTIZE_INT8
- Verify index size reduction (via stats)
- Query accuracy smoke test

### Dependencies
- Task #02 (quantizeType support) - DONE

---

## Notes

- DDL operations often require flush() before they take effect
- Vector indexes require optimize() after creation
- Test directories must be unique per test
