# ZVec PHP Examples

This directory contains simple, thematic examples of using the ZVec library (vector database via FFI).

## Before Running

Build the FFI library:
```bash
./build_zvec.sh
```

## Basic Examples

### 1. Basics - Creating and Adding
```bash
php examples/01_basics.php
```
Demonstrates:
- ZVec initialization
- Collection schema creation
- Adding documents (single and batch)
- Index optimization
- Data retrieval

### 2. Vector Search
```bash
php examples/02_search.php
```
Demonstrates:
- Finding similar documents (kNN)
- Search with filter (vector + scalar)
- Filter-only search (no vector)
- Limiting returned fields

### 3. Document Management
```bash
php examples/03_documents.php
```
Demonstrates:
- Document update (update)
- Upsert (update or insert)
- Deleting single documents
- Delete by filter

### 4. Schema and Index Management
```bash
php examples/04_schema.php
```
Demonstrates:
- Adding new columns
- Renaming columns
- Changing column data types
- Creating indexes (HNSW, Flat)
- Dropping indexes

### 5. Opening, Closing and Persistence
```bash
php examples/05_persistence.php
```
Demonstrates:
- Closing collection (close)
- Reopening collection (open)
- Opening in read-only mode
- Flush data to disk
- Destroying collection (destroy)

### Run All Basic Examples
```bash
for f in examples/0*.php; do echo "=== $f ==="; php "$f"; echo; done
```

## Embedding Examples

### `embeddings_basic.php`
Basic usage of embedding functions without ZVec collection.

### `embeddings_with_zvec.php`
Integration of embeddings with ZVec vector database.

## Comprehensive Integration Example

```bash
php php/example.php
```

Comprehensive test showing 21 different scenarios.

## Example Structure

Each file is self-contained:
1. ZVec initialization
2. Creating temporary collection (unique name)
3. Feature demonstration
4. Cleanup (destroy + rm -rf)
5. try-finally block for safety

Each example uses a directory in `test_dbs/` with a unique ID (uniqid()).
