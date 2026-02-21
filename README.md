# zvec-php

PHP FFI bindings for [Alibaba's zvec](https://github.com/alibaba/zvec) vector database.

## Overview

zvec-php provides PHP bindings for the zvec vector database through FFI (Foreign Function Interface). It allows you to create collections, insert documents with vectors, perform similarity searches, and manage indexes - all from PHP.

## Features

- **Collections**: Create, open, close, and destroy vector collections
- **Schema Definition**: Define fields with various data types (INT64, STRING, FLOAT, DOUBLE, VECTOR_FP32)
- **Document Operations**: Insert, upsert, update, fetch, and delete documents
- **Vector Search**: Perform similarity searches with HNSW, IVF, or Flat indexes
- **Filtering**: Pre-filter candidates with boolean expressions
- **Schema Evolution**: Add, drop, and rename columns dynamically
- **Index Management**: Create and drop inverted, HNSW, and Flat indexes
- **Doc Introspection**: Check field existence and enumerate scalar/vector fields

## Requirements

- PHP 8.1+ with FFI extension enabled
- macOS (currently macOS only)
- CMake 3.14+ (for building)

## Installation

### 1. Clone with submodules

```bash
git clone --recursive <repository-url>
cd zvec-php
```

### 2. Build the native library

```bash
./build_zvec.sh
```

This will:
- Clone zvec repository (if not present)
- Download CMake 3.28 locally
- Build zvec C++ library
- Build FFI wrapper (`ffi/build/libzvec_ffi.dylib`)

### 3. Verify installation

```bash
php php/example.php
```

## Quick Start

```php
<?php
require_once 'php/ZVec.php';

// Initialize zvec
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

// Define schema
$schema = new ZVecSchema('my_collection');
$schema->addInt64('id', nullable: false, withInvertIndex: true)
    ->addString('title', nullable: false)
    ->addVectorFp32('embedding', dimension: 128, metricType: ZVecSchema::METRIC_IP);

// Create collection
$collection = ZVec::create('./my_collection', $schema);

// Insert documents
$doc = new ZVecDoc('doc_1');
$doc->setInt64('id', 1)
    ->setString('title', 'Hello World')
    ->setVectorFp32('embedding', [0.1, 0.2, 0.3, /* ... 128 dims */]);
$collection->insert($doc);

// Search
$results = $collection->query(
    'embedding',
    [0.1, 0.2, 0.3, /* ... query vector */],
    topk: 10
);

foreach ($results as $doc) {
    echo $doc->getPk() . ': ' . $doc->getScore() . "\n";
}

// Cleanup
$collection->close();
```

## API Reference

### ZVec (Collection)

```php
// Lifecycle
ZVec::create(string $path, ZVecSchema $schema, bool $readOnly = false, bool $enableMmap = true): self
ZVec::open(string $path, bool $readOnly = false, bool $enableMmap = true): self
$collection->close(): void
$collection->destroy(): void

// Schema operations
$collection->addColumnInt64(string $name, bool $nullable = true, string $defaultExpr = '0'): void
$collection->addColumnFloat(string $name, bool $nullable = true, string $defaultExpr = '0'): void
$collection->addColumnDouble(string $name, bool $nullable = true, string $defaultExpr = '0'): void
$collection->dropColumn(string $name): void
$collection->renameColumn(string $oldName, string $newName): void

// Index management
$collection->createInvertIndex(string $fieldName, bool $enableRange = true, bool $enableWildcard = false): void
$collection->createHnswIndex(string $fieldName, int $metricType = ZVecSchema::METRIC_IP, int $m = 50, int $efConstruction = 500): void
$collection->createFlatIndex(string $fieldName, int $metricType = ZVecSchema::METRIC_IP): void
$collection->dropIndex(string $fieldName): void

// Document operations
$collection->insert(ZVecDoc ...$docs): void
$collection->upsert(ZVecDoc ...$docs): void
$collection->update(ZVecDoc ...$docs): void
$collection->delete(string ...$pks): void
$collection->deleteByFilter(string $filter): void
$collection->fetch(string ...$pks): ZVecDoc[]

// Search
$collection->query(string $fieldName, array $queryVector, int $topk = 10, ...): ZVecDoc[]
$collection->queryByFilter(string $filter, int $topk = 100): ZVecDoc[]
$collection->groupByQuery(...): array  // Note: GroupBy is not fully functional in upstream zvec

// Maintenance
$collection->optimize(): void
$collection->flush(): void
```

### ZVecSchema

```php
$schema = new ZVecSchema('collection_name');
$schema->setMaxDocCountPerSegment(int $count): self
$schema->addInt64(string $name, bool $nullable = false, bool $withInvertIndex = false): self
$schema->addString(string $name, bool $nullable = false, bool $withInvertIndex = false): self
$schema->addFloat(string $name, bool $nullable = true): self
$schema->addDouble(string $name, bool $nullable = true): self
$schema->addVectorFp32(string $name, int $dimension, int $metricType = self::METRIC_IP): self
$schema->addSparseVectorFp32(string $name, int $metricType = self::METRIC_IP): self
```

### ZVecDoc

```php
$doc = new ZVecDoc('primary_key');

// Setters
$doc->setInt64(string $field, int $value): self
$doc->setString(string $field, string $value): self
$doc->setFloat(string $field, float $value): self
$doc->setDouble(string $field, float $value): self
$doc->setVectorFp32(string $field, array $vector): self

// Getters
$doc->getPk(): string
$doc->getScore(): float
$doc->getInt64(string $field): ?int
$doc->getString(string $field): ?string
$doc->getFloat(string $field): ?float
$doc->getDouble(string $field): ?float
$doc->getVectorFp32(string $field): ?array

// Introspection
$doc->hasField(string $name): bool
$doc->hasVector(string $name): bool
$doc->fieldNames(): array
$doc->vectorNames(): array
```

## Running Tests

### Main integration test
```bash
php php/example.php
```

### Individual tests
```bash
php tests/bug_0001.php
php tests/bug_0002.php
php tests/test_doc_introspection.php
```

### All tests
```bash
php php/example.php && php tests/bug_0001.php && php tests/bug_0002.php && php tests/test_doc_introspection.php
```

## Project Structure

```
zvec-php/
├── php/ZVec.php          # Main library (ZVec, ZVecSchema, ZVecDoc, ZVecException)
├── php/example.php       # Integration test / usage examples (21 scenarios)
├── ffi/                  # C++ FFI bridge
│   ├── zvec_ffi.h        # C header with FFI declarations
│   ├── zvec_ffi.cc       # C++ implementation
│   └── CMakeLists.txt    # Build configuration
├── tests/                # Test scripts
├── todo/                 # Feature planning documents (17 items)
├── build_zvec.sh         # Build script for zvec + FFI
└── zvec/                 # Git-cloned upstream zvec C++ library (not committed)
```

## Todo / Planned Features

See `todo/` directory for detailed planning documents. Overview:

### Completed ✅
- [x] Doc introspection methods (hasField, hasVector, fieldNames, vectorNames)

### High Priority
- [ ] IVF index creation support
- [ ] QuantizeType support (FP16, INT8, INT4) on index creation
- [ ] Add column STRING and BOOL types
- [ ] Vector query by document ID

### Medium Priority
- [ ] Concurrency options for optimize/index/create ops
- [ ] Additional scalar data types (BOOL, INT32, UINT32, UINT64)
- [ ] Multi-vector query with reranking
- [ ] Sparse vector data operations (set/get/query)
- [ ] Extended HNSW query parameters

### Low Priority
- [ ] Alter column field schema
- [ ] Doc introspection (field_names, vector_names) - **DONE**
- [ ] Extensions: rerankers (RRF, Weighted)
- [ ] Extensions: embeddings (OpenAI, Qwen)
- [ ] Vector query object interface
- [ ] Per-doc status on batch operations
- [ ] Max doc count per segment options

## Known Limitations

- **GroupByQuery**: The C++ API has this method but it returns all documents in a single group with empty group value. This is a known issue in upstream zvec (marked as "Coming Soon" in zvec docs).
- **Platform**: Currently macOS only (builds `.dylib`)
- **Sparse Vectors**: Schema definition exists but data operations are not yet implemented

## License

Same as upstream zvec project (Apache License 2.0)
