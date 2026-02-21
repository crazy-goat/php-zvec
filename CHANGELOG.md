# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

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

## Todo / Planned Features

See `todo/` directory for detailed feature planning:

- [x] Doc introspection methods (#08) ✅
- [ ] IVF index creation (#01)
- [ ] QuantizeType support on index creation (#02)
- [ ] Concurrency options (#03)
- [ ] Additional scalar data types (#04)
- [ ] Multi-vector query (#05)
- [ ] Extended HNSW query params (#06)
- [ ] Alter column field schema (#07)
- [ ] Extensions: rerankers (#09)
- [ ] Extensions: embeddings (#10)
- [ ] Vector query by ID (#11)
- [ ] Vector query object (#12)
- [ ] Add column STRING and BOOL (#13)
- [ ] Sparse vector data operations (#14)
- [ ] Segment options (#15)
- [ ] Per-doc status on batch ops (#16)
- [ ] Reranker in query (#17)

---

[Unreleased]: https://github.com/alibaba/zvec-php/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/alibaba/zvec-php/releases/tag/v0.1.0
