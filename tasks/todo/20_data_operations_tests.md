# Data Operations Tests

## Priority: MEDIUM

## Status: DONE

## Difficulty: 1/5 ⭐

## Description

Test CRUD operations: insert, upsert, update, delete, fetch.

Part of: Test Suite Migration (split from task #18)

Based on: https://zvec.org/en/docs/data-operations/

**Tests created:**
- `test_insert.phpt` - insert single/batch, duplicates, validation
- `test_upsert.phpt` - upsert new/existing, batch mix
- `test_update.phpt` - partial update, field preservation
- `test_delete_by_id.phpt` - delete by primary key
- `test_delete_by_filter.phpt` - delete by filter conditions
- `test_fetch.phpt` - fetch by primary key

---

## Test: test_insert.php

### Coverage
- Insert single document
- Insert multiple documents (batch)
- Insert duplicate (should fail with exception)
- Insert with missing required fields (should fail)
- Insert with wrong data types (should fail)

---

## Test: test_upsert.php

### Coverage
- Upsert new document (acts as insert)
- Upsert existing document (acts as update)
- Upsert batch mix of new and existing
- Verify replace vs merge behavior

---

## Test: test_update.php

### Coverage
- Update partial fields (scalar only)
- Update preserving other fields
- Update non-existent document (should fail silently or error?)
- Update with invalid data type (should fail)

---

## Test: test_delete_by_id.php

### Coverage
- Delete single document by ID
- Delete multiple documents by IDs
- Delete non-existent ID (should not error)
- Verify document actually removed

---

## Test: test_delete_by_filter.php

### Coverage
- Delete using scalar filter
- Delete with complex filter conditions
- Delete all matching filter
- Verify documents removed

---

## Test: test_fetch.php

### Coverage
- Fetch single document by ID
- Fetch multiple documents by IDs
- Fetch non-existent ID (should return empty or null)
- Fetch with missing/invalid fields (should handle gracefully)

---

## Notes

- All write operations are immediately visible for querying (real-time)
- Batch operations should be tested with various batch sizes
- Verify behavior when mixing existing and non-existent IDs
