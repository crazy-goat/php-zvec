# Utility and Edge Case Tests

## Priority: LOW

## Status: TODO

## Difficulty: 2/5 ⭐⭐

## Description

Test error handling, concurrent operations, and edge cases.

Part of: Test Suite Migration (split from task #18)

---

## Test: test_error_handling.php

### Coverage
- ZVecException catching
- Error message verification
- Exit code verification on test failure
- Invalid parameters (null, empty, wrong types)
- Operations on closed/destroyed collections

---

## Test: test_concurrent_ops.php

### Coverage
- Multiple inserts in sequence
- Insert + query interleaved
- Close during operations (should fail gracefully)

### Note
True concurrency not supported (single-threaded FFI), but sequence matters.

---

## Test: test_large_dataset.php

### Coverage (smoke test)
- Insert 1000+ documents
- Query performance sanity check
- Memory usage sanity check
- Collection size verification

---

## Test: test_schema_edge_cases.php

### Coverage
- Empty collection operations
- Collection with no vector fields
- Collection with no scalar fields (vectors only)
- Very long field names
- Unicode in field names/values

---

## Test: test_filter_edge_cases.php

### Coverage
- Empty filter string
- Malformed filter syntax
- Filter on non-indexed field
- Filter with special characters
- Case sensitivity in filters

---

## Notes

- Edge case tests often reveal memory leaks or segfaults
- Large dataset tests should complete in reasonable time (< 30s)
- Error handling tests verify graceful failures (no crashes)
