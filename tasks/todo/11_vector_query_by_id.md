# VectorQuery by Document ID

## Priority: MEDIUM

## Status: TODO

## Description

Python SDK's `VectorQuery` supports querying by document ID instead of providing an explicit vector. This lets you "find similar documents" by referencing an existing document's embedding.

## Python API

```python
import zvec

# Query by vector (what we support now)
VectorQuery(field_name="embedding", vector=[0.1, 0.2, ...])

# Query by document ID (NOT implemented)
VectorQuery(field_name="embedding", id="doc_42")
```

You must provide exactly one of `vector` or `id`. Specifying both raises an error.

## Changes needed

### Research first
- Check C++ `VectorQuery` struct in `doc.h` — does it have an `id` field?
- Or does the Python SDK implement this as fetch(id) → get vector → query(vector)?

### ffi/zvec_ffi.h
- Add `zvec_collection_query_by_id()` or extend existing query functions with `const char* doc_id` param

### ffi/zvec_ffi.cc
- Implement: either use C++ API's id-based query, or fetch doc → extract vector → query

### php/ZVec.php
- Add `queryById()` or add optional `$docId` param to `query()`
