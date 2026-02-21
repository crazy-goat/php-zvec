# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
