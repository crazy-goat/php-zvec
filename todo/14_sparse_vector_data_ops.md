# Sparse Vector Data Operations

## Priority: MEDIUM

## Status: TODO

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

## Changes needed

### Research first
- Check C++ `Doc` class — how are sparse vectors set/get?
- Check `VectorQuery` — can `query_vector_` hold sparse data?
- Check zvec source for sparse vector representation (likely `SparseVector` type or similar)

### ffi/zvec_ffi.h
- Add `zvec_doc_set_sparse_vector_fp32(doc, field, indices, values, count)`
- Add `zvec_doc_get_sparse_vector_fp32(doc, field, indices_out, values_out, count_out)`
- Add sparse vector query support (separate function or extend existing)

### ffi/zvec_ffi.cc
- Map sparse data to C++ SparseVector type

### php/ZVec.php (ZVecDoc)
- Add `setSparseVectorFp32(string $field, array $sparseVector)` — takes `[index => weight]`
- Add `getSparseVectorFp32(string $field): ?array` — returns `[index => weight]`

### php/ZVec.php (ZVec)
- Support sparse vectors in `query()` method
