# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.3.9] - 2026-02-21

### Added

- **Additional Data Types** (#4) - MEDIUM priority task
  - New scalar types: BOOL, INT32, UINT32, UINT64
  - New vector type: VECTOR_INT8 (INT8 quantized vectors)
  - FFI layer: 20 new functions in `ffi/zvec_ffi.cc` and `ffi/zvec_ffi.h`
  - PHP bindings: New methods in `ZVecSchema`, `ZVecDoc`, and `ZVec` classes
  - New test: `test_extra_data_types.phpt` - validates all new types
  - Total test count: 26 tests (25 PASS + 1 expected XFAIL)

### Notes

- VECTOR_FP16 and VECTOR_FP64 require custom Float16 handling - see task #28
- BOOL columns supported in schema but not via column DDL (zvec limitation)

## [0.3.8] - 2026-02-21

### Added

- **Schema Evolution Tests** (#19) - MEDIUM priority task
  - 6 new `.phpt` test files for DDL operations:
    - `test_column_add.phpt` - addColumnInt64/Float/Double with defaults
    - `test_column_rename.phpt` - renameColumn with error cases
    - `test_column_drop.phpt` - dropColumn with schema verification
    - `test_index_invert.phpt` - createInvertIndex, dropIndex, filter queries
    - `test_index_vector.phpt` - Flat/HNSW index switching
    - `test_index_quantized.phpt` - QUANTIZE_INT8/FP16 support
  - All 6 tests pass consistently
  - Test suite now totals 25 tests (24 PASS + 1 expected XFAIL)

### Changed

- **Project Structure**
  - Added `test_dbs/` directory for test databases (committed but contents ignored)
  - Updated `AGENTS.md` with new test database path pattern
  - Tests now use `$path = __DIR__ . '/../test_dbs/test_name_' . uniqid()`
  - Prevents cluttering project root when tests fail

## [0.3.7] - 2026-02-21

### Changed

- **Task Directory Restructure**
  - Moved all task files from `todo/` to new `tasks/` structure
  - `tasks/todo/` - 20 pending tasks (TODO status)
  - `tasks/done/` - 7 completed tasks (DONE status)
  - Improved organization and clarity of project planning

## [0.3.6] - 2026-02-21

### Changed

- **Bug Test Migration** (#27 extension)
  - Migrated 6 bug reproduction files from `.php` to `.phpt` format
  - New files: `bug_0001.phpt` through `bug_0006.phpt`
  - Removed old `bug_*.php` files (6 files deleted)
  - Updated `.gitignore` to ignore PHP test runner artifacts (*.diff, *.exp, *.log, *.out, *.sh)
  - Marked 4 bugs as FIXED (removed XFAIL): bug_0001, bug_0003, bug_0005, bug_0006
  - 1 bug remains XFAIL (bug_0002): GroupByQuery "Coming Soon" in zvec
  - All 19 tests now pass consistently (18 PASS + 1 expected XFAIL)

## [0.3.5] - 2026-02-21

### Changed

- **Test Migration to .phpt Format** (#27) - Complete
  - Migrated all 7 legacy `.php` test files to standard `.phpt` format
  - New files: `test_alter_column.phpt`, `test_collection_create.phpt`, `test_collection_destroy.phpt`, `test_collection_open.phpt`, `test_collection_optimize.phpt`, `test_collection_persist.phpt`, `test_doc_introspection.phpt`
  - Removed old `.php` test files (7 files deleted)
  - All 13 `.phpt` tests now pass consistently
  - Benefits: standardized format, integration with `php run-tests.php`, better reporting

## [0.3.4] - 2026-02-21

### Added

- **Closed Collection Protection** (#25) - HIGH priority task
  - Added `checkClosed()` method to prevent segfaults on closed/destroyed collections
  - All public methods now throw `ZVecException` instead of causing segfault (exit 139)
  - Operations protected: insert, upsert, update, delete, query, fetch, flush, optimize, etc.
  - Added `test_closed_collection_protection.phpt` - verifies exception handling
  - Before: `$c->close(); $c->insert($doc);` → segfault
  - After: `$c->close(); $c->insert($doc);` → `ZVecException: Collection is closed or destroyed`

- **Cleanup Safety** (#26) - Defensive programming measures
  - Added `$dirty` flag for tracking potentially inconsistent state
  - Implemented `executeWithDirty()` helper for operation tracking
  - Made `close()` and destructor error-tolerant
  - Added bug reproduction tests: `bug_0005_cleanup_after_failed_ops.php`, `bug_0006_rocksdb_lock.php`

- **Test Migration Planning** (#27)
  - Created task for migrating 8 legacy `.php` tests to `.phpt` format
  - Documented migration plan and benefits

### Changed

- **AGENTS.md** - Added "Test First - Write Before Fix" rule
  - Always write failing test before attempting bug fixes
  - Ensures understanding of the bug and prevents regressions

- **Test Cleanup**
  - Removed test runner artifacts (`.diff`, `.exp`, `.log`, `.out`, `.sh` files)
  - Removed duplicate and temporary test files
  - Final structure: 6 `.phpt` tests + 8 `.php` tests (for migration) + 6 `bug_*.php`

## [0.3.3] - 2026-02-21

### Added

- **PHP Test Runner** - Added `run-tests.php` from php-src for running .phpt tests
- **Utility Tests Suite** (#23) - Migrated 5 tests to .phpt format
  - `test_error_handling.phpt` - Exception catching, invalid parameters, type validation
  - `test_concurrent_ops.phpt` - Sequence of inserts and interleaved queries  
  - `test_large_dataset.phpt` - 1500+ documents insertion and performance checks
  - `test_schema_edge_cases.phpt` - Empty collections, unicode handling, long field names
  - `test_filter_edge_cases.phpt` - Filter operators, case sensitivity, special characters
  - All tests use try-finally cleanup and unique temp directories

### Changed

- **Documentation** - Updated AGENTS.md with Testing Requirements section
  - Pre-commit Test Checklist (build, run tests, verify cleanup)
  - Test Requirements for New Features
  - Example .phpt test template
  - Updated test commands for .phpt format

## [0.3.2] - 2026-02-21

### Added

- **Collection Tests Suite** (#18)
  - `test_collection_create.php` - Collection creation with schema validation
  - `test_collection_open.php` - Open/close with read-only and read-write modes
  - `test_collection_destroy.php` - Collection destruction and directory cleanup
  - `test_collection_optimize.php` - Segment optimization and read-only rejection
  - `test_collection_persist.php` - Data persistence with and without flush()
  - Task #18 status updated to DONE

- **Bug Reproduction Tests**
  - `bug_0003.php` - Documents segfault after destroy() (known C++ limitation)
  - `bug_0004.php` - Documents max_doc_count_per_segment minimum threshold (1000)

- **Test Framework Planning** (#24)
  - Created task for migrating to .phpt test format
  - Documented benefits and migration plan

### Changed

- **Documentation**
  - AGENTS.md: Added Bug Reproduction Tests section with mandatory test requirement
  - AGENTS.md: Added Memory Management, Collection Lifecycle, Debug & Logging sections
  - AGENTS.md: Added Common Pitfalls section with important warnings
  - Test Conventions section updated to reflect current and future (.phpt) formats

## [0.3.1] - 2026-02-21

### Changed

- **Documentation**
  - Updated AGENTS.md with Release Workflow section
  - Added Semantic Versioning guidelines (patch/minor/major releases)
  - Added descriptive commit message requirements for releases
  - Improved API Consistency section with better examples

## [0.3.0] - 2026-02-21

### Added

- **QuantizeType Support** (#02)
  - Added `$quantizeType` parameter to `createHnswIndex()` and `createFlatIndex()` methods
  - New constants: `QUANTIZE_UNDEFINED=0`, `QUANTIZE_FP16=1`, `QUANTIZE_INT8=2`, `QUANTIZE_INT4=3`
  - Enables vector compression for smaller indexes (FP16=2x smaller, INT8=4x, INT4=8x)
  - FFI functions updated: `zvec_collection_create_hnsw_index()`, `zvec_collection_create_flat_index()`
  - Test scenario added in `php/example.php` (scenario 6b)

- **AGENTS.md Documentation** 
  - Added API Consistency section with reference implementations (Node.js, Python, C++)
  - Added Task Planning & Documentation section
  - Added workflow guidelines for test migration tasks

- **Test Suite Planning** (Tasks #18-23)
  - Created 6 new task files for test migration from `example.php`
  - Organized by documentation categories: Collections, Schema Evolution, Data Operations, Query Operations, Document Operations, Utility

### Changed

- Updated `todo/02_quantize_type.md` status to DONE

## [0.2.0] - 2026-02-21

### Added

- **Alter Column with Field Schema** (#07)
  - `ZVec::alterColumn(string $columnName, ?string $newName = null, ?int $newDataType = null, ?bool $nullable = null): void` - Change column type (scalar numeric only) and/or rename
  - Data type constants: `TYPE_INT32=4`, `TYPE_INT64=5`, `TYPE_UINT32=6`, `TYPE_UINT64=7`, `TYPE_FLOAT=8`, `TYPE_DOUBLE=9`
  - FFI function: `zvec_collection_alter_column()`
  - Test: `tests/test_alter_column.php`
  - Limitations: Cannot rename and change type in one call; cannot change nullable: true → false

- **Doc Introspection Methods** (#08)
  - `ZVecDoc::hasField(string $name): bool` - Check if scalar field exists
  - `ZVecDoc::hasVector(string $name): bool` - Check if vector field exists  
  - `ZVecDoc::fieldNames(): array` - Get all scalar field names
  - `ZVecDoc::vectorNames(): array` - Get all vector field names
  - FFI functions: `zvec_doc_has_field`, `zvec_doc_has_vector`, `zvec_doc_field_names`, `zvec_doc_vector_names`
  - Test: `tests/test_doc_introspection.php`

## [0.1.0] - 2026-02-21

### Added

- Initial PHP FFI bindings for zvec vector database
- **Collection Management**
  - Create, open, close, and destroy collections
  - Collection schema inspection and statistics
- **Schema Definition**
  - Support for INT64, STRING, FLOAT, DOUBLE scalar types
  - Support for VECTOR_FP32 and SPARSE_VECTOR_FP32 vector types
  - Inverted index configuration for scalar fields
- **Document Operations**
  - Insert, upsert, update, fetch, and delete documents
  - Batch operations support
  - Document introspection (pk, score, field getters)
- **Vector Search**
  - Similarity search with HNSW, IVF, or Flat indexes
  - Support for IP, L2, and Cosine metrics
  - Query parameters (ef for HNSW, nprobe for IVF)
- **Filtering**
  - Boolean expression filtering (`category = 'tech'`, `id >= 10`, etc.)
  - Filtered vector search
  - Query by filter (no vector required)
- **Schema Evolution**
  - Add columns (INT64, FLOAT, DOUBLE) with default expressions
  - Drop columns
  - Rename columns
  - Create and drop indexes dynamically
- **Index Management**
  - Inverted indexes for scalar fields (with optional range/wildcard optimization)
  - HNSW vector indexes (customizable M and ef_construction)
  - Flat vector indexes
- **Testing**
  - Comprehensive integration test in `php/example.php` (21 scenarios)
  - Bug reproduction tests in `tests/`

### Known Issues

- **GroupByQuery** - Returns all documents in single group with empty group value. This is an upstream zvec issue (marked as "Coming Soon" in zvec documentation). Test: `tests/bug_0002.php`

### Limitations

- macOS only (builds `.dylib` shared library)
- PHP 8.1+ required
- Sparse vector data operations not yet implemented (schema definition only)

---

[Unreleased]: https://github.com/alibaba/zvec-php/compare/v0.3.1...HEAD
[0.3.1]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.1
[0.3.0]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.0
[0.2.0]: https://github.com/alibaba/zvec-php/releases/tag/v0.2.0
[0.1.0]: https://github.com/alibaba/zvec-php/releases/tag/v0.1.0
