# Collections Tests

## Priority: MEDIUM

## Status: ✅ DONE

## Difficulty: 1/5 ⭐

## Description

Test collection lifecycle operations: create, open, optimize, destroy, persistence.

Part of: Test Suite Migration (split from task #18)

Based on: https://zvec.org/en/docs/collections/

---

## Test: test_collection_create.php

### Coverage
- Create collection with schema
- Verify schema, stats, path, options
- Test invalid paths (should fail)

### Dependencies
- ZVec::init()
- ZVecSchema
- ZVec::create()

---

## Test: test_collection_open.php

### Coverage
- Create collection, close it
- Reopen existing collection (readOnly=false)
- Reopen existing collection (readOnly=true)
- Open non-existent collection (should fail)

### Dependencies
- ZVec::create()
- ZVec::open()
- Collection::close()

---

## Test: test_collection_destroy.php

### Coverage
- Create, insert docs, destroy
- Verify directory removed
- Try operations on destroyed collection (should fail)

### Dependencies
- ZVec::create()
- Collection::insert()
- Collection::destroy()

---

## Test: test_collection_optimize.php

### Coverage
- Insert multiple docs across segments
- Call optimize()
- Verify stats show fewer segments
- Test optimize on read-only collection (should fail)

### Dependencies
- ZVec::create()
- Collection::insert()
- Collection::optimize()
- Collection::stats()

---

## Test: test_collection_persistence.php

### Coverage
- Create + insert
- Close collection
- Reopen and verify data persisted
- Test flush before close

### Dependencies
- ZVec::create()
- ZVec::open()
- Collection::close()
- Collection::flush()
- Collection::fetch()

---

## Notes

- Tests should use unique temp directories (based on test name)
- Always cleanup with `exec("rm -rf ...")` at end
- Use `assert()` for validation within tests
- Output format: `PASS: test_name - description` or `FAIL: test_name - reason`
- Exit with code 1 on failure
