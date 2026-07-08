# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **DOC-003: Missing `@throws ZVecException` on All FFI-Calling Methods** (#62)
  - Added `@throws ZVecException On FFI error` PHPDoc annotation to all public methods across 4 source files:
    - `src/ZVec.php` — 57 methods (addColumn*, create*/open*/query* methods, version API, etc.)
    - `src/ZVecSchema.php` — 38 methods (all add* methods including deprecated aliases)
    - `src/ZVecDoc.php` — 65 methods (all set*/get*/has* methods, constructor, serialize/deserialize, etc.)
    - `src/ZVecIndexParams.php` — 6 methods (forHnsw, forFlat, forIvf, forVamana, forInvert, forHnswRabitq)
  - Every public method that calls `self::ffi()`, `self::ffi()->`, or `self::checkStatus()` now documents the thrown exception
  - No methods without direct FFI calls (getHandle, createWith, openWith, getOptions, getFFI) were annotated
  - Destructors correctly left without `@throws` annotations

- **DOC-010: Missing Security Documentation** (#69)
  - Added Security section to README covering trust model, input validation, memory limits, file permissions, supply chain, and selected known security limitations (SEC-001, SEC-002, SEC-004, SEC-008, SEC-012)
  - Updated `SECURITY.md` with vulnerability disclosure response-time commitment, FFI security model, supply chain notes, and API key safety guidance
  - Updated `OpenAIDenseEmbedding` and `QwenDenseEmbedding` PHPDoc blocks to reference `SECURITY.md` for API key handling guidelines; code examples now use `getenv()` pattern
  - See `README.md#security` and `SECURITY.md` for full documentation

- **TEST-001: ZVecException isolation tests for error code strings, constructor, chaining, and error details** (#99)
  - Added 4 unit test files for `ZVecException` class:
    - `test_exception_error_code_string.phpt` — getErrorCodeString() mapping for codes 0-10, 99, and -1
    - `test_exception_constructor.phpt` — parameter combinations (default, message, code, all params, partial details)
    - `test_exception_chaining.phpt` — exception chaining with RuntimeException, ZVecException, custom Throwable, deep chaining
    - `test_exception_error_details.phpt` — getErrorFile/Line/Function with boundary values, empty strings, unicode, chaining preservation
  - Added 1 integration test:
    - `test_exception_integration.phpt` — real FFI round-trip errors (INVALID_ARGUMENT, ALREADY_EXISTS, invalid filter, chaining)
  - Added `examples/08_error_handling.php` — comprehensive error handling patterns demonstration
  - Each test uses `try-finally` with `uniqid()` temp directory and cleanup
  - Tests skip properly when native zvec extension is loaded (FFI-only methods)

- **DOC-001: Added class-level PHPDoc to all major classes for IDE tooling** (#60)
  - Added class-level PHPDoc blocks to all 15 source files: ZVec, ZVecException, ZVecCollectionOptions, ZVecCollectionStats, ZVecFieldSchema, ZVecIndexParams, ZVecQueryInterface, ZVecVectorQuery, ZVecGroupByVectorQuery, ZVecSchema, ZVecDoc, ZVecRerankedDoc, ZVecRrfReRanker, ZVecWeightedReRanker, ZVecReRanker
  - Each block follows a consistent format: one-line purpose, usage paragraph, and `@see` cross-references
  - All `@see` references verified against actual methods and classes

- **TEST-007: Memory leak regression tests for FFI memory safety** (#105)
  - Added 5 `.phpt` test files for memory leak regression testing:
    - `test_memory_collection_lifecycle.phpt` — 50x create/open/close/destroy cycle with memory growth tracking
    - `test_memory_deserialize_buffer.phpt` — 100x serialize/deserialize cycle with buffer leak detection
    - `test_memory_query_output_fields.phpt` — 100x query with/without output fields for C string leak detection
    - `test_memory_init_error_path.phpt` — 20x init error/recovery cycles for C allocation leak detection
    - `test_memory_delete_cstrings.phpt` — 30x insert/delete/fetch cycles for C string array leak detection
  - Added `examples/07_memory_management.php` — FFI memory cleanup patterns (C string free, try-finally guards, VmRSS monitoring)
  - Each test uses `try-finally` with `uniqid()` temp directory under `test_dbs/` and `exec("rm -rf ...")` cleanup
  - Tests verify both PHP heap (`memory_get_usage()`) and native memory (VmRSS) stability
  - 500KB threshold for memory growth detection across all test scenarios

- **TEST-004: Rerankers — Edge case coverage for RRF and Weighted re-rankers** (#102)
  - Added 7 `.phpt` test files for reranker edge cases:
    - `test_reranker_rrf_empty.phpt` — empty query results, null values, non-ZVecDoc elements
    - `test_reranker_weighted_zero_range.phpt` — zero-range normalization guard (all scores identical)
    - `test_reranker_weighted_float_min_bug.phpt` — PHP_FLOAT_MIN initialization bug detection
    - `test_reranker_weighted_l2_metric.phpt` — L2 metric normalization (lower distance = higher score)
    - `test_reranker_weighted_negative_scores.phpt` — negative IP score normalization
    - `test_reranker_rrf_custom_rank_constant.phpt` — custom rank constant effect on combined scores
    - `test_reranker_weighted_empty_weights.phpt` — empty weights edge cases (constructor + setter)
  - Added `test_reranker_in_query.phpt` — integration test for `queryWithReranker()` with RRF
  - Fixed PHP_FLOAT_MIN bug detection logic in `test_reranker_weighted_float_min_bug.phpt`
  - Each test uses `try-finally` with `uniqid()` temp directory and cleanup
  - All 9 reranker tests pass with 100% success rate

- **SMELL-013: Migrated all classes to `CrazyGoat\ZVec\` namespace with PSR-4 autoloading** (#94)
  - All library classes now live under `CrazyGoat\ZVec\` namespace
  - Global class names preserved via `class_alias()` for backward compatibility
  - Use `CrazyGoat\ZVec\ZVec` with `use` statement or keep using global `ZVec` — both work
  - Added `tests/test_namespace_bc.phpt` to verify both namespace styles
  - Removed `classmap` from `composer.json` — PSR-4 autoloading now handles everything
- **SMELL-009: Added `queryWithReranker()` method for type-safe reranked queries** (#90)
  - New `queryWithReranker()` method on `ZVec` returns `ZVecRerankedDoc[]` — static analysis tools can infer the correct return type
  - Supports `string|ZVecVectorQuery` as field name, with all existing query parameters
  - Routes FP64 queries via internal `queryWithRerankerFp64()` helper

### Deprecated

- **SMELL-013: Global namespace class names are deprecated** (#94)
  - Use `use CrazyGoat\ZVec\ZVec;` instead of global `ZVec`
  - Global aliases remain for backward compatibility but may be removed in a future major version
- **SMELL-009: The `$reranker` parameter on `query()` and `queryFp64()` is deprecated** (#90)
  - Passing `$reranker` to `query()` now triggers `E_USER_DEPRECATED` and delegates to `queryWithReranker()`
  - Existing code continues to work (backward compatible) but should migrate to `queryWithReranker()`

### Changed

- **SMELL-008: Made properties private with getters/setters on reranker data classes** (#89)
  - `ZVecRerankedDoc`: all properties (`$doc`, `$combinedScore`, `$sourceRanks`, `$sourceScores`) are now `private`
  - Added getters: `getDoc()`, `getCombinedScore()`, `getSourceRanks()`, `getSourceScores()`
  - `ZVecRrfReRanker`: `$topn` and `$rankConstant` are now `private` with `getTopn()`/`setTopn()` and `getRankConstant()`/`setRankConstant()`
  - `ZVecWeightedReRanker`: `$topn`, `$metricType`, `$weights` are now `private` with getters/setters
  - Setters return `self` for fluent interface: `$reranker->setTopn(5)->setRankConstant(30)`
  - `setWeights([])` throws `ZVecException` (same validation as constructor)
  - Updated all call sites in tests and examples to use getter methods
  - Added `tests/test_encapsulation.phpt` — verifies private properties and getter/setter functionality
  - Direct property access is no longer possible — use `getCombinedScore()`, `getSourceRanks()`, etc.

- **SMELL-014: Decomposed `query()` (116 lines, 4+ responsibilities) into focused helpers** (#95)
  - Extracted `resolveQueryParams()` — resolves parameters from explicit args or `ZVecVectorQuery` object, validates inputs
  - Extracted `executeQuery()` — performs FFI call with FP32 float vectors
  - Extracted `executeQueryFp64()` — performs FFI call with FP64 double vectors
  - `query()` body reduced to ~15 lines calling the three helpers
  - Refactored `queryFp64()`, `queryWithReranker()`, and `queryWithRerankerFp64()` to reuse helpers
  - Eliminated duplicated FFI parameter setup and output field handling code
  - Added `tests/test_query_decomposition.phpt` — 14 test cases covering all query paths

- **SMELL-017: Extracted `collection_batch_op()` template in `ffi/zvec_ffi.cc`** (#97)
  - Replaced ~130 lines of duplicated code across `zvec_collection_insert_batch`,
    `zvec_collection_upsert_batch`, and `zvec_collection_update_batch` with a shared
    `collection_batch_op()` helper that takes a callable
  - Each batch wrapper reduced to a 5-line lambda, making the operation difference
    immediately visible
  - Bug fixes to batch logic now need to be applied in only one place

- **SMELL-010: Added `$destroyed` boolean to distinguish closed vs destroyed collection state** (#91)
  - `checkClosed()` now throws distinct error messages for each state:
    - Closed: "Collection is closed. Open with ZVec::open() to continue."
    - Destroyed: "Collection has been destroyed and cannot be reused"
  - `close()` now checks `$this->destroyed` to prevent closing a destroyed collection
  - Updated `tests/test_closed_collection_protection.phpt` with new error messages
  - Added `tests/test_close_vs_destroy.phpt` — verifies three-state tracking

- **SMELL-016: Dropped `Field` prefix from schema field methods** (#107)
  - New unprefixed methods: `addBinary()`, `addArrayString()`, `addArrayBool()`,
    `addArrayInt32()`, `addArrayInt64()`, `addArrayUint32()`, `addArrayUint64()`,
    `addArrayFloat()`, `addArrayDouble()`
  - All existing `addField*()` methods remain as deprecated aliases
  - Updated all internal call sites to use new method names

- **DOC-005: Add PHPDoc for `ZVec::init()`, `isInitialized()`, and `shutdown()`** (#64)
  - Added comprehensive PHPDoc block to `init()` documenting all 13 parameters with descriptions, valid constant ranges, default values, and 0-value semantics
  - Added PHPDoc for `isInitialized()` with `@return bool` description
  - Added PHPDoc for `shutdown()` with `@throws ZVecException` documentation
  - Cover `$logType`, `$logLevel`, `$logDir`, `$logBasename`, `$logFileSize`, `$logOverdueDays`, `$queryThreads`, `$optimizeThreads`, `$invertToForwardScanRatio`, `$bruteForceByKeysRatio`, `$memoryLimitMb`, `$allowedBasePath`, and `$verboseErrors`

### Deprecated

- `addFieldBinary()` — use `addBinary()` instead
- `addFieldArrayString()` — use `addArrayString()` instead
- `addFieldArrayBool()` — use `addArrayBool()` instead
- `addFieldArrayInt32()` — use `addArrayInt32()` instead
- `addFieldArrayInt64()` — use `addArrayInt64()` instead
- `addFieldArrayUint32()` — use `addArrayUint32()` instead
- `addFieldArrayUint64()` — use `addArrayUint64()` instead
- `addFieldArrayFloat()` — use `addArrayFloat()` instead
- `addFieldArrayDouble()` — use `addArrayDouble()` instead

### Security

- **SEC-007: Validate version argument with semver regex to prevent URL injection in FFI library download** (#75)
  - Added `preg_match('/^v\d+\.\d+\.\d+(-[\w.-]*\w)?$/', $version)` validation in both `bin/zvec-install` and `Installer::install()`
  - Rejects path traversal (`../../etc/passwd`), incomplete semver (`v0.4`), empty strings, and URL-unsafe characters (`+`)
  - Accepts standard semver with optional pre-release suffixes (e.g. `v0.5.0-alpha1`, `v0.4.0-rc.2`)
  - CLI entry point writes to STDERR and exits with code 1; programmatic path throws `RuntimeException`
  - Added `tests/test_installer_version_validation.phpt` — 15 test cases covering valid versions, path traversal, null byte injection, and trailing special characters

- **SEC-002: Replace predictable tempnam() with cryptographically random temp directory to prevent symlink race** (#71)
  - Replaced `tempnam()` + `.tar.gz` suffix with `mkdir()` + `random_bytes(8)` + `bin2hex()` for secure temp directory creation
  - Temp directory created with 0700 permissions (only current user can access)
  - 128-bit cryptographic randomness makes path prediction infeasible
  - `finally` block does `rm -rf` on entire temp directory, eliminating orphaned files
  - Added `tests/test_installer_tempdir.phpt` — verifies 0700 permissions, file creation inside secure dir, cleanup, and unique name generation
  - Added `tests/test_installer_tempdir_cleanup.phpt` — verifies cleanup after success and failure, concurrent installation isolation, and missing-temp-file edge case
  - Added `SECURITY.md` — documents the TOCTOU/symlink mitigation

- **SEC-001: Add SHA-256 integrity verification for downloaded FFI shared library** (#70)
  - `Installer::install()` now verifies SHA-256 checksum before extracting the downloaded archive
  - Timing-safe comparison via `hash_equals()`
  - Added `Installer::verifyChecksum()` public method
  - Added `tests/test_installer_checksum.phpt` — 5 test scenarios covering valid checksum, mismatch, empty file, binary content, and recomputed hash

- **SEC-009: Add advisory file locking (flock) to prevent TOCTOU race condition in concurrent Installer::install()** (#77)
  - Acquires `LOCK_EX` on `lib/install.lock` before the `file_exists($libPath)` check (lock-check pattern)
  - Double-check inside the lock: re-verifies the library isn't already installed by another concurrent process
  - Lock file persists as a sentinel (never deleted) — ensures all concurrent processes share the same inode for `flock()` serialization
  - Lock is released in `finally` block — crash-safe (kernel auto-releases `flock` on process termination)
  - Added `tests/test_installer_flock.phpt` — 4 test scenarios covering basic flock, concurrent serialization, source code verification, and behavioral double-check test with `Installer::install()`

- **SEC-008: Mask API keys in debug output and clear from memory in destructor** (#76)
  - Added `__debugInfo()` method to `ApiEmbeddingFunction` — masks API key as `***xxxx` (last 4 chars visible) in `var_dump()` output
  - Added `__destruct()` method to `ApiEmbeddingFunction` — calls `sodium_memzero()` on the API key string buffer when `ext-sodium` is available
  - Constructor `$apiKey` parameter changed from `string` to `?string = null` — falls back to `OPENAI_API_KEY` or `DASHSCOPE_API_KEY` environment variables
  - Added `private __clone()` to prevent cloned-instance string buffer corruption
  - Updated `SECURITY.md` — marked "API keys in memory" as fixed
  - Added 4 test files: `test_embedding_apikey_mask.phpt` (source analysis), `test_embedding_apikey_env.phpt` (env var fallback), `test_embedding_apikey_destruct.phpt` (destructor), `test_embedding_apikey_runtime.phpt` (runtime validation)

- **SEC-012: Enforce explicit SSL certificate verification in embedding API requests** (#80)
  - Added `CURLOPT_SSL_VERIFYPEER => true` and `CURLOPT_SSL_VERIFYHOST => 2` to all embedding HTTP requests
  - Using `curl_setopt_array()` to ensure SSL options are always applied together
  - Proxy configuration remains as a separate `curl_setopt()` call (unchanged)
  - Added static analysis test `test_embedding_ssl_verify.phpt` to verify SSL options are present in source

### Changed

- **SMELL-004: Triplicated Insert/Upsert/Update Code** (#85)
  - Extracted shared `writeDocs()` and `writeDocsBatch()` private helpers from `insert()`, `upsert()`, `update()` and their batch variants
  - Each public method is now a 1-line wrapper around the shared helper
  - Eliminated ~74 lines of duplicated code — bug fixes to write logic now apply to all 6 operations at once
  - Added `tests/test_write_helpers.phpt` — comprehensive test covering all 6 operations, error paths (duplicate insert, non-existent update)

### Fixed

- **BUG-007: Memory Leak in `ZVecDoc::deserialize()` — Buffer Never Freed** (#54)
  - Wrapped C buffer allocation/cleanup in `try-finally` to guarantee `FFI::free($buf)` runs even on exception
  - Previously `$buf` was allocated with `owned=false` and never freed — leaked on every call
  - Added `tests/bug_0054.phpt` — serialize/deserialize round-trip, fetched doc round-trip, 20-cycle stress test, minimal PK-only deserialization

- **BUG-011: Clone-Safety Double-Free in Handle-Holding Classes** (#58)
  - Added `private function __clone()` to `ZVec`, `ZVecSchema`, `ZVecIndexParams`, `ZVecCollectionStats`, `ZVecFieldSchema`, `ZVecDoc`, and `ZVecVectorQuery`
  - Cloning any of these classes now throws `\Error` instead of creating a shallow copy that causes double-free on destruction
  - Added `tests/bug_0011.phpt` — 13 test scenarios covering clone prevention and normal use verification

- **BUG-012: `query()` with `ZVecVectorQuery` ignores query object's `topk`, `includeVector`, and `filter`** (#59)
  - Added `topk`, `includeVector`, `filter` public properties to `ZVecVectorQuery`
  - `setTopk()`, `setIncludeVector()`, `setFilter()` now store values in properties
  - `query()` extracts `topk`, `includeVector`, `filter` from query object

- **BUG-004: Memory Leak in `ZVec::init()` on Exception** (#51)
  - Wrapped `checkStatus($ffi->zvec_ffi_initialize($configData))` and the two `free()` calls in `try-finally` to guarantee `zvec_log_config_free()` and `zvec_config_data_free()` run even when `zvec_ffi_initialize()` returns a non-zero status
  - Previously C-allocated `$logConfig` and `$configData` leaked on every failed initialization attempt
  - Added `tests/bug_0051_init_memory_leak.phpt` — 5-cycle init/shutdown stress test with memory growth tracking

- **BUG-006: Memory Leak in `query()` and Related Methods on Exception** (#53)
  - Wrapped `query()`, `queryFp64()`, `queryByFilter()`, and `groupByQuery()` FFI calls in `try-finally` to guarantee C string cleanup on exception
  - `checkStatus()` moved inside `try` block before the `finally` cleanup loop
  - Unified output-fields C string allocation/cleanup across all four query methods
  - Added `tests/bug_0006.phpt` — 50-iteration stress test per method with bad field name / bad filter to trigger exceptions and verify no memory leak
  - `groupByQuery()` extracts `includeVector` and `filter` from query object (topk not applicable — use `groupTopk`)
  - Method signature parameters act as fallback when query object properties not set
  - Added `tests/bug_0012.phpt` — 5 test scenarios verifying ZVecVectorQuery topk/filter/includeVector

- **BUG-003: WeightedReRanker produces wrong normalization for negative IP scores** (#50)
  - `PHP_FLOAT_MIN` (smallest positive float) replaced with `-PHP_FLOAT_MAX` (most negative finite float) as initial `max` value in score normalization — fixes incorrect min-max normalization when all scores are negative (IP, COSINE metrics)
  - Magic number `1` replaced with `ZVecSchema::METRIC_L2` named constant for readability and maintainability
  - Added `tests/bug_0050.phpt` — regression test verifying normalized scores in [0, 1] range for negative IP scores and correct L2 metric normalization

## [0.4.11] - 2026-05-10

### Added

- **Composer package with FFI library auto-download** (#4)
  - Added `composer.json` with PSR-4 autoloading for `CrazyGoat\ZVec\` namespace
  - `src/Installer.php` — standalone CLI installer for FFI shared library
  - `bin/zvec-install` — `vendor/bin/zvec-install` entry point
  - Platform detection: Linux x86_64 (glibc) supported; musl/Alpine, macOS rejected with clear message
  - Version auto-detected from `vendor/composer/installed.json`; manual override via `vendor/bin/zvec-install v0.4.11`
  - Pre-built FFI library (`libzvec_ffi-ubuntu24-x86_64.tar.gz`) uploaded to releases
  - `ZVec.php` resolves FFI library from `lib/` (Composer) or `ffi/build/` (source build)

## [0.4.10] - 2026-05-10

### Changed

- **Upgrade zvec to v0.4.0** (#8)
  - Bumped upstream `alibaba/zvec` library from v0.2.0 to v0.4.0 (released 2026-05-09)
  - All build scripts, Docker workflows, and CI pinned to `v0.4.0` tag
  - Updated FFI link list: v0.4.0 merged 12 individual `libcore_*.a` libraries into aggregate `libzvec_core.a` and `libzvec_db.a` — linker now uses 4 libs instead of 14
  - Cross-platform `build_zvec.sh`: auto-detects macOS/Linux, CPU count, and GCC version
  - GCC 15 workaround: `-include stdint.h` for RocksDB 8.1.1 `uint64_t` compatibility
  - CI now builds zvec from source when pre-built tarball not available
  - All 54 tests pass (53 PASS + 1 XFAIL), all example scripts verified

## [0.4.8] - 2026-03-15

### Added

- **CI: FFI Test Job** - Added `build-ffi` job to GitHub Actions build workflow
  - Builds `libzvec_ffi.so` on Ubuntu 24.04 and runs all `.phpt` tests with PHP FFI backend
  - Both `ext` and `ffi` backends now tested in CI on every pull request

### Changed

- **Cross-platform FFI build** - `ffi/CMakeLists.txt` now supports Linux (`--whole-archive` + openssl) alongside macOS (`-force_load` + frameworks)
- **Cross-platform library detection** - `ZVec.php` auto-detects `.dylib` (macOS) vs `.so` (Linux) based on `PHP_OS_FAMILY`
- **Sorted field/vector names in FFI** - `zvec_doc_field_names()` and `zvec_doc_vector_names()` now return sorted results, matching ext behavior
- **CI: removed strip step** from build workflow (kept only in release workflow)

## [0.4.7] - 2026-02-22

### Added

- **FP16 Vector Support** (#28) - LOW priority task - Half-precision (16-bit) vector storage
  - Added `addVectorFp16()` method to ZVecSchema for FP16 vector fields (DataType::VECTOR_FP16 = 22)
  - Added `setVectorFp16()` and `getVectorFp16()` methods to ZVecDoc class
  - Added `queryFp16()` method to ZVec class for FP16 vector queries
  - FFI layer: conversion between uint16_t[] (PHP) and ailego::Float16 (C++)
  - Uses `zvec/ailego/utility/float_helper.h` for FP16 ↔ FP32 conversion
  - Storage efficiency: 2 bytes per dimension (vs 4 bytes for FP32)
  - API accepts/returns int[] (uint16 values in PHP)
  - Separate query method required: `queryFp16()` instead of `query()`
  - FFI functions:
    - `zvec_schema_add_field_vector_fp16()` - schema creation
    - `zvec_doc_set_vector_fp16()` - document insertion
    - `zvec_doc_get_vector_fp16()` - document retrieval
    - `zvec_collection_query_fp16()` - vector search
  - New test: `tests/test_fp16_vectors.phpt` - full workflow coverage
  - Task moved from `tasks/todo/` to `tasks/done/`
  - Note: FP64 (double-precision) not implemented - blocked by upstream zvec (std::vector<double> not in Doc::Value variant)

## [0.4.6] - 2026-02-22

### Added

- **Sparse Vector Data Operations** (#14) - MEDIUM priority task - Set/get sparse vector data on documents
  - Added `setSparseVectorFp32()` and `getSparseVectorFp32()` methods to ZVecDoc class
  - FFI layer: new functions `zvec_doc_set_sparse_vector_fp32()` and `zvec_doc_get_sparse_vector_fp32()`
  - Sparse vectors stored as `std::pair<std::vector<uint32_t>, std::vector<float>>` (indices + values)
  - Circular buffer pattern (16 slots) in FFI to handle concurrent document access
  - Supports empty sparse vectors (count=0)
  - API: `setSparseVectorFp32(string $field, array $indices, array $values): self`
  - Returns: `['indices' => [...], 'values' => [...]]` format from getter
  - Validates that indices and values arrays have matching lengths
  - New test: `tests/test_sparse_vectors.phpt` - comprehensive test coverage
  - New example: `php/example_sparse_vectors.php` - complete usage demo
  - Task moved from `tasks/todo/` to `tasks/done/`

## [0.4.5] - 2026-02-22

### Added

- **Multi-Vector Query** (#05) - MEDIUM priority task - Hybrid search with multiple vector fields
  - Added `queryMulti()` method to ZVec class for searching multiple vector fields simultaneously
  - Executes multiple single-vector queries and merges results using rerankers
  - Fetches `max(topk*2, 100)` candidates per field for better recall
  - Requires `ZVecReRanker` implementation (RRF or Weighted)
  - Returns `ZVecRerankedDoc[]` sorted by combined score
  - Supports filter expressions applied to all queries
  - Supports output fields selection
  - Each `ZVecVectorQuery` can have field-specific query parameters (HNSW ef, IVF nprobe)
  - Pure PHP implementation - no FFI changes needed
  - New tests: 
    - `tests/test_multivector_query.phpt` - RRF reranker integration
    - `tests/test_multivector_weighted.phpt` - Weighted reranker integration
  - New example: `php/example_multivector_query.php` - complete demo with 7 scenarios:
    - Single-field search baseline comparison
    - RRF reranker multi-vector fusion
    - Weighted reranker with field importance tuning
    - Filtered multi-vector search
    - Field-specific query parameters
  - Task moved from `tasks/todo/` to `tasks/done/`

## [0.4.4] - 2026-02-22

### Added

- **Reranker Parameter in query()** (#17) - LOW priority task
  - Added `ZVecReRanker` interface for all reranker implementations
  - Modified `query()` method to accept optional `$reranker` parameter
  - Implements two-stage retrieval: fetches max(topk*2, 100) candidates, then reranks
  - Returns `ZVecRerankedDoc[]` when reranker is provided, `ZVecDoc[]` otherwise
  - Works with both direct parameters and `ZVecVectorQuery` objects
  - Updated `ZVecRrfReRanker` and `ZVecWeightedReRanker` to implement interface
  - New test: `tests/test_reranker_in_query.phpt` - 4 comprehensive scenarios
  - New example: `php/example_query_with_reranker.php` - two-stage retrieval demo
  - Task moved from `tasks/todo/` to `tasks/done/`

## [0.4.3] - 2026-02-22

### Added

- **Query by Document ID** (#11) - MEDIUM priority task
  - Added `queryById()` method to ZVec class for finding similar documents
  - Queries by referencing an existing document's embedding instead of explicit vector
  - Implementation uses fetch(id) → get vector → query(vector) pattern
  - Full support for all query parameters: filter, outputFields, HNSW/IVF params
  - Throws ZVecException if document not found or vector field missing
  - New test: `tests/test_query_by_id.phpt` - 6 comprehensive test scenarios
  - New example: `php/example_query_by_id.php` - complete demo with 4 scenarios
    - Basic similarity search
    - Filtered search by category
    - Comparison with regular query method
    - Error handling demonstration
  - Task moved from `tasks/todo/` to `tasks/done/`

## [0.4.2] - 2026-02-22

### Added

- **Rerankers** (#09) - LOW priority task - Multi-vector search result fusion
  - Added `ZVecRrfReRanker` class for Reciprocal Rank Fusion
    - Formula: `Score = 1 / (rankConstant + rank)` with default k=60
    - No score normalization needed - works purely on rankings
    - Best default choice for combining multiple vector search results
  - Added `ZVecWeightedReRanker` class for weighted score combination
    - Score normalization per metric type (L2, IP, COSINE)
    - Weighted sum: `Σ(weight_i × normalized_score_i)`
    - Configurable field weights for fine-tuning result importance
  - Added `ZVecRerankedDoc` result wrapper class
    - Contains: original ZVecDoc, combined score, source ranks, source scores
    - Methods: `getPk()`, `getOriginalScore()`
  - Both rerankers accept `[fieldName => ZVecDoc[]]` format from multiple queries
  - Standalone PHP implementation - no FFI or C++ dependency
  - New test: `tests/test_rerankers.phpt` - comprehensive tests for RRF and Weighted
  - New example: `php/example_rerankers.php` - complete demo with 6 scenarios
    - RRF vs Weighted comparison
    - Single field reranking edge case
    - Source ranks and scores access
  - Task moved from `tasks/todo/` to `tasks/done/`

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

[Unreleased]: https://github.com/alibaba/zvec-php/compare/v0.4.11...HEAD
[0.4.11]: https://github.com/crazy-goat/php-zvec/compare/v0.4.10...v0.4.11
[0.4.10]: https://github.com/alibaba/zvec-php/compare/v0.4.9...v0.4.10
[0.4.9]: https://github.com/alibaba/zvec-php/compare/v0.4.8...v0.4.9
[0.4.8]: https://github.com/alibaba/zvec-php/compare/v0.4.7...v0.4.8
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
