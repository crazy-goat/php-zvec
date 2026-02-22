# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.4.1] - 2026-02-22

### Added

- **VectorQuery Object** (#12) - MEDIUM priority task
  - Added `ZVecVectorQuery` class for structured vector queries
  - Encapsulates field name, vector data, and query parameters (HNSW, IVF, Flat)
  - Fluent interface: `setHnswParams()`, `setIvfParams()`, `setFlatParams()`, `setRadius()`, `setLinear()`, `setUsingRefiner()`
  - Factory method: `ZVecVectorQuery::fromId()` for query-by-document-ID (preparation for task #11)
  - Both `query()` and `groupByQuery()` now accept `ZVecVectorQuery` as first parameter
  - Backward compatible - old positional arguments API still works
  - Prerequisite for multi-vector queries (task #05)
  - New test: `tests/test_vector_query_object.phpt` - comprehensive tests for all scenarios
  - New example: `php/example_vector_query.php` - standalone demo with 6 usage scenarios
  - Task moved from `tasks/todo/` to `tasks/done/`

### Fixed

- **ZVec::init() parameter** - Restored missing `$logType` parameter that was accidentally removed
  - Named arguments like `ZVec::init(logType: ZVec::LOG_CONSOLE)` now work correctly

## [0.4.0] - 2026-02-22

### Added

- **Concurrency Options** (#03) - MEDIUM priority task
  - Added `concurrency` parameter to `optimize()`, `createHnswIndex()`, `createFlatIndex()`, `createIvfIndex()`
  - Added `concurrency` parameter to all `addColumn*()` methods (Int64, Float, Double, String, etc.)
  - Added `concurrency` parameter to `renameColumn()` and `alterColumn()`
  - FFI layer: Updated all affected functions to accept `uint32_t concurrency` parameter
  - C++ bridge: Passes `OptimizeOptions`, `CreateIndexOptions`, `AddColumnOptions`, `AlterColumnOptions` to zvec C++ API
  - PHP API: New optional `int $concurrency = 0` parameter (0 = auto-detect, uses system default thread pool)
  - Allows fine-grained control over parallel processing for performance tuning
  - New test: `tests/test_concurrency_options.phpt` - comprehensive tests with concurrency=2
  - Task moved from `tasks/todo/` to `tasks/done/`

### Changed

- **API Consistency** - All DDL operations now support concurrency control
  - Index creation, column DDL, and optimization can now run with custom thread counts
  - Backward compatible - default value of 0 preserves previous behavior

## [0.3.22] - 2026-02-22

### Added

- **Per-Document Batch Operations** (#16) - LOW priority task
  - Added `insertBatch()`, `upsertBatch()`, and `updateBatch()` methods returning per-document status
  - Returns detailed results: `[['pk' => 'doc1', 'ok' => true, 'error' => null], ...]`
  - FFI layer: New `zvec_batch_result_t` struct and `*_batch()` functions
  - C++ bridge: Functions return arrays of status codes, messages, and document PKs
  - PHP API: New methods that don't throw on first error - allows partial batch success handling
  - Original `insert()`, `upsert()`, `update()` unchanged (backward compatible)
  - New test: `tests/test_batch_operations.phpt` - comprehensive coverage of success/failure scenarios
  - New example: `php/example_batch_operations.php` - standalone demo of batch operations
  - Task moved from `tasks/todo/` to `tasks/done/`

### Removed

- Deleted `php/example.php` - replaced by standalone examples in `examples/` directory

## [0.3.21] - 2026-02-22

### Added

- **Simple Code Examples** - 5 new standalone example files in `examples/`
  - `examples/01_basics.php` - Collection creation, schema definition, adding documents (single and batch), index optimization
  - `examples/02_search.php` - Vector similarity search (kNN), search with filters, filter-only search, output field selection
  - `examples/03_documents.php` - Document update, upsert operations, single and batch delete, delete by filter
  - `examples/04_schema.php` - Adding columns, renaming columns, changing column types, HNSW and Flat index management
  - `examples/05_persistence.php` - Close/open collections, read-only mode, flush to disk, destroy collections
  - All examples are self-contained with try-finally cleanup and use unique temp directories in `test_dbs/`

### Changed

- **Examples README** - Completely rewritten in English with clear descriptions of each example

## [0.3.20] - 2026-02-22

### Fixed

- **Query Parameter Type Validation** (#29) - HIGH priority task
  - Fixed segfault when using `QUERY_PARAM_FLAT` on HNSW index (or mismatched types)
  - Added C++ FFI layer validation in `zvec_collection_query_ex()` and `zvec_collection_group_by_query()`
  - New `validate_query_param_type()` function checks if requested query_param_type matches actual index type
  - Returns `StatusCode::INVALID_ARGUMENT` error with descriptive message instead of crashing
  - New test: `tests/bug_0029_query_param_validation.phpt` - verifies exception is thrown

### Changed

- **Task Status** (#29)
  - Marked Fix Segfault When Using QUERY_PARAM_FLAT on HNSW Index as DONE
  - Moved task file from `tasks/todo/` to `tasks/done/`

## [0.3.19] - 2026-02-22

### Added

- **Extended Query Parameters** (#06) - MEDIUM priority task
  - Added `radius`, `isLinear`, `isUsingRefiner` parameters to `query()` and `groupByQuery()` methods
  - FFI layer: Extended `zvec_collection_query_ex` and `zvec_collection_group_by_query` with new parameters
  - C++ bridge: Updated `apply_query_params()` to handle HNSW, IVF, and Flat query params
  - PHP API: New optional parameters for advanced vector search control
  - New test: `tests/test_extended_query_params.phpt` - comprehensive test coverage

### Fixed

- **Default max_buffer_size** (#15 follow-up)
  - Fixed default value from 0 to 67108864 (64MB) to prevent collection corruption errors
  - Previously caused "Read record batch failed" and RocksDB flush errors

### Changed

- **Test Optimization**
  - Optimized `test_large_dataset.phpt`: reduced from 1500 to 300 docs, 128 to 32 dimensions
  - Execution time improved from ~120s to ~20s
  - All 42 tests now pass (43 total, 1 expected XFAIL)

- **Task Status** (#06, #26)
  - Marked Extended HNSW/IVF Query Parameters as DONE
  - Marked Improve cleanup safety as DONE
  - Moved task files from `tasks/todo/` to `tasks/done/`
  - Deleted obsolete `.php` test files (fully migrated to `.phpt` format)

## [0.3.18] - 2026-02-22

### Added

- **Embedding Functions** (#10) - LOW priority task
  - Added support for converting text to vector representations via external APIs
  - `DenseEmbeddingFunction` interface with `embed()`, `embedBatch()`, `getDimension()` methods
  - `OpenAIDenseEmbedding` - OpenAI API implementation supporting text-embedding-3-small, text-embedding-3-large, text-embedding-ada-002
  - `QwenDenseEmbedding` - DashScope/Qwen API implementation supporting text-embedding-v4, v3, v2, v1
  - Base class `ApiEmbeddingFunction` with HTTP client functionality (curl-based)
  - Batch embedding support (up to 2048 inputs for OpenAI, 25 for Qwen)
  - Configurable dimensions for OpenAI v3 models
  - New examples:
    - `examples/embeddings_basic.php` - Basic embedding usage with mock implementation
    - `examples/embeddings_with_zvec.php` - Full ZVec integration (store/query embeddings)
  - New tests:
    - `tests/test_embeddings_interfaces.phpt` - Interface validation
    - `tests/test_embeddings_integration.phpt` - Integration tests with mock embedding
  - Total test count: 42 tests (41 PASS + 1 expected XFAIL)

### Changed

- **Task Status** (#10)
  - Marked Extensions: Embedding Functions task as DONE
  - Moved task file from `tasks/todo/` to `tasks/done/`

## [0.3.17] - 2026-02-21

### Added

- **CollectionOptions: max_buffer_size** (#15) - LOW priority task
  - Added `max_buffer_size` parameter to `CollectionOptions`
  - FFI layer: Updated `zvec_collection_create()`, `zvec_collection_open()`, and `zvec_collection_options()`
  - PHP API: Added optional `$maxBufferSize` parameter to `ZVec::create()` and `ZVec::open()`
  - Returns max_buffer_size in `options()` method
  - Default: 64MB (67,108,864 bytes)
  - New test: `tests/test_max_buffer_size.phpt` - comprehensive test for buffer size options

### Changed

- **Optimized test_collection_optimize.phpt**
  - Reduced test documents from 1500 to 50 (still tests optimize functionality)
  - Changed to single batch insert (instead of multiple small batches)
  - Reduced test execution time from ~57s to ~2.6s
  - Removed unnecessary `set_time_limit()` calls

- **Task Status** (#15)
  - Marked SegmentOption / max_buffer_size task as DONE
  - Moved task file from `tasks/todo/` to `tasks/done/`

## [0.3.16] - 2026-02-21

### Added

- **Add Column: STRING type** (#13) - MEDIUM priority task
  - Added `addColumnString()` method for future STRING column support
  - FFI layer: `zvec_collection_add_column_string()` function
  - PHP API: `addColumnString(string $name, bool $nullable = true, string $defaultExpr = '')`
  - Note: C++ API currently only supports numeric types for AddColumn - STRING support "coming soon"
  - Implementation ready - will automatically work when zvec adds upstream support

### Changed

- **Task Status** (#13)
  - Marked Add Column STRING/BOOL task as DONE
  - Moved task file from `tasks/todo/` to `tasks/done/`

## [0.3.15] - 2026-02-21

### Added

- **IVF Index Creation** (#01) - HIGH priority task
  - Added `createIvfIndex()` method for creating IVF (Inverted File) indexes
  - Parameters: nList (clusters), nIters (k-means iterations), useSoar (optimization), quantizeType
  - FFI layer: `zvec_collection_create_ivf_index()` function
  - PHP API: `createIvfIndex(string $fieldName, int $metricType, int $nList = 1024, int $nIters = 10, bool $useSoar = false, int $quantizeType = 0)`
  - Query support: `queryParamType: ZVec::QUERY_PARAM_IVF` with `ivfNprobe` parameter
  - New test: `tests/test_ivf_index.phpt` - comprehensive test suite for IVF operations
  - Total test count: 39 tests (38 PASS + 1 expected XFAIL)

### Changed

- **Task Status** (#01)
  - Marked IVF Index Creation task as DONE
  - Moved task file from `tasks/todo/` to `tasks/done/`

## [0.3.14] - 2026-02-21

### Added

- **Query Operations Tests** (#21) - MEDIUM priority task
  - New test: `test_query_basic.phpt` - single vector search, topk parameter, includeVector flag
  - New test: `test_query_filtered.phpt` - vector + scalar filter, filter-only queries (queryByFilter)
  - New test: `test_query_output_fields.phpt` - outputFields parameter for field selection
  - New test: `test_query_hnsw_params.phpt` - hnswEf parameter, QUERY_PARAM_HNSW/NONE
  - New test: `test_groupby_query.phpt` - API verification for future GroupBy feature
  - Total test count: 38 tests (37 PASS + 1 expected XFAIL)

### Changed

- **AGENTS.md Documentation**
  - Added "After Implementation Checklist" section
  - Documented requirement to move completed tasks from `tasks/todo/` to `tasks/done/`

- **Test Maintenance**
  - Fixed `tests/bug_0002.php` to use `test_dbs/` directory pattern
  - Task #21 moved from `tasks/todo/` to `tasks/done/`

## [0.3.13] - 2026-02-21

### Changed

- **Task Status Updates**
  - Marked #04 (Extra Data Types) as DONE - moved to tasks/done/
  - Marked #20 (Data Operations Tests) as DONE - moved to tasks/done/
  - Marked #22 (Document Operations Tests) as DONE - moved to tasks/done/
  - Marked #24 (Test Framework Migration to .phpt) as DONE - moved to tasks/done/
  - Verified all corresponding tests exist and pass (100% success rate)

## [0.3.12] - 2026-02-21

### Changed

- **Test Database Directory** (#27) - LOW priority task
  - Updated all 27 test files to use `test_dbs/` directory pattern
  - Test paths changed from `test_*_` to `test_dbs/*_`
  - Prevents clutter in repository root when tests fail
  - All tests now consistently create databases in `test_dbs/` directory

### Changed

- **Task Status** (#27)
  - Marked Test Database Directory task as DONE
  - Moved task file from `tasks/todo/` to `tasks/done/`

## [0.3.11] - 2026-02-21

### Added

- **Data Operations Tests** (#20) - MEDIUM priority task
  - New test: `test_insert.phpt` - insert single and batch documents, duplicate handling, validation
  - New test: `test_upsert.phpt` - upsert new/existing documents, batch mix operations
  - New test: `test_update.phpt` - partial field updates, field preservation verification
  - New test: `test_delete_by_id.phpt` - delete by primary key (single/multiple IDs)
  - New test: `test_delete_by_filter.phpt` - delete by filter conditions (category, score)
  - New test: `test_fetch.phpt` - fetch by primary key (single/multiple, error handling)
  - Total test count: 33 tests (32 PASS + 1 expected XFAIL)

### Changed

- **Task Status Updates**
  - Marked #20 (Data Operations Tests) as DONE
  - Marked #24 (Migrate to .phpt format) as DONE - all tests now use .phpt format

## [0.3.10] - 2026-02-21

### Added

- **Document Field Access Tests** (#22) - LOW priority task
  - New test: `test_doc_field_access.phpt` - validates all doc getter/setter methods
  - Coverage: setInt64, setFloat, setDouble, setString, setVectorFp32
  - Coverage: getInt64, getFloat, getDouble, getString, getVectorFp32
  - Tests non-existent field access (returns null)
  - Tests type coercion (wrong getter returns null)
  - Total test count: 27 tests (26 PASS + 1 expected XFAIL)

### Changed

- **Task Status** (#22)
  - Marked Document Operations Tests as DONE
  - Both introspection and field access tests now complete

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

[Unreleased]: https://github.com/alibaba/zvec-php/compare/v0.3.22...HEAD
[0.3.22]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.22
[0.3.21]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.21
[0.3.20]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.20
[0.3.19]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.19
[0.3.18]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.18
[0.3.17]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.17
[0.3.15]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.15
[0.3.14]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.14
[0.3.13]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.13
[0.3.12]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.12
[0.3.11]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.11
[0.3.10]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.10
[0.3.9]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.9
[0.3.8]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.8
[0.3.7]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.7
[0.3.6]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.6
[0.3.5]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.5
[0.3.4]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.4
[0.3.3]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.3
[0.3.2]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.2
[0.3.1]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.1
[0.3.0]: https://github.com/alibaba/zvec-php/releases/tag/v0.3.0
[0.2.0]: https://github.com/alibaba/zvec-php/releases/tag/v0.2.0
[0.1.0]: https://github.com/alibaba/zvec-php/releases/tag/v0.1.0
