# Sparse Vector Data Operations

## Priority: MEDIUM

## Status: DONE

## Description

We have `addSparseVectorFp32()` in schema, but no way to actually set/get sparse vector data on documents, or query with sparse vectors.

## Python API

```python
# Setting sparse vector on doc
doc = zvec.Doc(id="doc1", vectors={"sparse_emb": {1: 0.5, 37: 0.43, 100: 1.2}})

# Querying with sparse vector
VectorQuery(field_name="sparse_emb", vector={1: 0.5, 37: 0.43})
```

Sparse vectors are represented as `dict[int, float]` — mapping dimension index to weight.

## Implementation

### ffi/zvec_ffi.h
- Added `zvec_doc_set_sparse_vector_fp32(doc, field, indices, values, count)`
- Added `zvec_doc_get_sparse_vector_fp32(doc, field, indices_out, values_out, count_out)`

### ffi/zvec_ffi.cc
- Implemented sparse vector set/get using `std::pair<std::vector<uint32_t>, std::vector<float>>`
- Used circular buffer pattern (16 slots) for get function to handle multiple document access
- Sparse vectors in C++ are stored as pair of (indices vector, values vector)

### php/ZVec.php (ZVecDoc)
- Added `setSparseVectorFp32(string $field, array $indices, array $values): self`
  - Takes two arrays: indices (int[]) and values (float[])
  - Validates that both arrays have same length
- Added `getSparseVectorFp32(string $field): ?array`
  - Returns `['indices' => [...], 'values' => [...]]` format
  - Returns null if field doesn't exist

### Tests
- Created `tests/test_sparse_vectors.phpt`
  - Tests setting and getting sparse vectors on documents
  - Tests error handling for mismatched array lengths
  - Handles unordered fetch results (zvec returns docs in arbitrary order)

## API Usage

```php
// Schema definition
$schema->addSparseVectorFp32('embedding', metricType: ZVecSchema::METRIC_IP);

// Setting sparse vector on document
$doc->setSparseVectorFp32('embedding', [1, 5, 10], [0.5, 0.3, 0.8]);
// indices:  [1, 5, 10]
// values:   [0.5, 0.3, 0.8]

// Getting sparse vector from document
$sparse = $doc->getSparseVectorFp32('embedding');
// Returns: ['indices' => [1, 5, 10], 'values' => [0.5, 0.3, 0.8]]
```

## Notes

- Query support for sparse vectors is NOT implemented yet (would require extending query functions)
- The FFI uses circular buffer pattern to avoid data mixing when accessing multiple documents
- Fetch returns documents in arbitrary order (not request order), so tests must match by PK
- Implementation follows the same patterns as dense vectors (FP32, INT8)

## References

- C++ Doc class: `zvec/src/include/zvec/db/doc.h` - sparse vectors stored as `std::pair<std::vector<uint32_t>, std::vector<float>>`
- DataType: `SPARSE_VECTOR_FP32 = 31` (from `type.h`)
- Test file: `tests/test_sparse_vectors.phpt`
