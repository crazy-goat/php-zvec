# zvec-php

PHP bindings for [Alibaba's zvec](https://github.com/alibaba/zvec) vector database.

Two binding modes are available:
- **Native PHP extension** (`php-ext/`) — compiled C++ extension, no FFI overhead (recommended)
- **FFI bindings** (`php/` + `ffi/`) — pure PHP via FFI, no compilation needed beyond the shared library

## Overview

zvec-php provides PHP bindings for the zvec vector database through FFI (Foreign Function Interface). It allows you to create collections, insert documents with vectors, perform similarity searches, and manage indexes - all from PHP.

## Features

- **Collections**: Create, open, close, and destroy vector collections
- **Schema Definition**: Define fields with various data types (INT64, STRING, FLOAT, DOUBLE, BOOL, INT32, UINT32, UINT64, VECTOR_FP32, VECTOR_FP64, VECTOR_FP16, VECTOR_INT4, VECTOR_INT8, VECTOR_INT16, VECTOR_BINARY32, VECTOR_BINARY64, SPARSE_VECTOR_FP32, SPARSE_VECTOR_FP16, BINARY, array types)
- **Document Operations**: Insert, upsert, update, fetch, and delete documents
- **Vector Search**: Perform similarity searches with HNSW, HNSW-RaBitQ, IVF, Flat, or Vamana indexes
- **Filtering**: Pre-filter candidates with boolean expressions
- **Schema Evolution**: Add, drop, rename, and alter columns dynamically
- **Index Management**: Create and drop indexes via unified `createIndex()` + `ZVecIndexParams`
- **Doc Introspection**: Check field existence, nullability, and enumerate scalar/vector fields
- **Batch Operations**: Per-document status on batch insert/upsert/update
- **Reranking**: Two-stage retrieval with RRF or weighted score fusion
- **Multi-Vector Search**: Search across multiple vector fields simultaneously with hybrid fusion
- **Group-By Query**: Group results by field value (limited upstream support)
- **Version API**: Check zvec library version at runtime

## Requirements

- PHP 8.1+ (FFI mode) or PHP 8.5+ (native extension mode)
- Linux x86_64 (for pre-built FFI library) or build tools for other platforms
- CMake 3.14+ (if building from source)

## Installation

### Quick start via Composer (FFI mode, Linux x86_64)

```bash
composer require crazy-goat/zvec
vendor/bin/zvec-install
```

The `zvec-install` tool detects your OS and architecture, downloads the matching
pre-built FFI shared library from GitHub Releases, and places it in `lib/`.
The version is auto-detected from Composer's `installed.json`. If omitted,
specify it explicitly: `vendor/bin/zvec-install v0.4.10`.

Supported platforms:
- **Linux x86_64 (glibc)** — pre-built (`libzvec_ffi-ubuntu24-x86_64.tar.gz`)

### Manual build (all platforms)

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
- Build FFI wrapper (`ffi/build/libzvec_ffi.so`)

### 3. Verify installation (FFI mode)

```bash
php run-tests.php tests/
```

### Alternative: Build the native PHP extension

The native extension provides the same API without FFI overhead. It links directly with the zvec C++ library.

```bash
# 1. Build zvec first (if not already done)
./build_zvec.sh

# 2. Build the extension
cd php-ext
bash build_ext.sh

# 3. Enable the extension (add to php.ini)
echo "extension=$(pwd)/modules/zvec.so" >> $(php -r 'echo php_ini_loaded_file();')

# 4. Verify
php -r 'echo extension_loaded("zvec") ? "zvec extension loaded" : "not loaded";'
```

When the extension is loaded, `require_once 'src/ZVec.php'` is a no-op (guard clause skips the FFI implementation), so existing code works without changes.

## Upgrading from v0.4.x

See [MIGRATION.md](./MIGRATION.md) for a complete migration guide covering:

- **Index Creation** — replace `createHnswIndex()`, `createFlatIndex()`, etc. with `createIndex()` + `ZVecIndexParams`
- **Statistics** — replace `stats()` JSON parsing with typed `getStatsStruct()`
- **Schema Introspection** — new `getFieldSchema()` API
- **Collection Options** — use `ZVecCollectionOptions` with `createWith()`/`openWith()`
- **Query Object** — use `ZVecVectorQuery` builder with `queryVector()`
- **Reranker in Queries** — use `queryWithReranker()` instead of `$reranker` param on `query()`
- **Deprecated Schema Methods** — rename `addField*()` to `add*()` (e.g. `addFieldBinary()` → `addBinary()`)

## Quick Start

```php
<?php
require_once 'src/ZVec.php';

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
// Library lifecycle
ZVec::init(
    int $logType = ZVec::LOG_CONSOLE,
    int $logLevel = ZVec::LOG_WARN,
    ?string $logDir = null,
    ?string $logBasename = null,
    int $logFileSize = 0,
    int $logOverdueDays = 0,
    int $queryThreads = 0,
    int $optimizeThreads = 0,
    float $invertToForwardScanRatio = 0.0,
    float $bruteForceByKeysRatio = 0.0,
    int $memoryLimitMb = 0,
    ?string $allowedBasePath = null,
    bool $verboseErrors = false
): void
ZVec::isInitialized(): bool
ZVec::shutdown(): void
ZVec::getLastErrorDetails(): array
ZVec::clearError(): void
ZVec::getVersion(): string
ZVec::checkVersion(int $major, int $minor, int $patch): bool
ZVec::getVersionMajor(): int
ZVec::getVersionMinor(): int
ZVec::getVersionPatch(): int

// Collection lifecycle (static factories)
$collection = ZVec::create(string $path, ZVecSchema $schema, bool $readOnly = false, bool $enableMmap = true, int $maxBufferSize = 67108864): self
$collection = ZVec::open(string $path, bool $readOnly = false, bool $enableMmap = true, int $maxBufferSize = 67108864): self
$collection = ZVec::createWith(string $path, ZVecSchema $schema, ZVecCollectionOptions $options): self
$collection = ZVec::openWith(string $path, ZVecCollectionOptions $options): self

$collection->close(): void
$collection->destroy(): void
$collection->flush(): void
$collection->optimize(int $concurrency = 0): void

// Collection introspection
$collection->schema(): string              // JSON string
$collection->path(): string                // On-disk path
$collection->options(): array              // readOnly, enableMmap, maxBufferSize
$collection->getOptions(): ZVecCollectionOptions
$collection->stats(): string               // JSON string
$collection->getStatsStruct(): ZVecCollectionStats
$collection->getFieldSchema(string $fieldName): ZVecFieldSchema

// Schema operations (add columns at runtime)
$collection->addColumnInt64(string $name, bool $nullable = true, string $defaultExpr = '0', int $concurrency = 0): void
$collection->addColumnFloat(string $name, bool $nullable = true, string $defaultExpr = '0', int $concurrency = 0): void
$collection->addColumnDouble(string $name, bool $nullable = true, string $defaultExpr = '0', int $concurrency = 0): void
$collection->addColumnString(string $name, bool $nullable = true, string $defaultExpr = '', int $concurrency = 0): void
$collection->addColumnBool(string $name, bool $nullable = true, string $defaultExpr = 'false', int $concurrency = 0): void
$collection->addColumnInt32(string $name, bool $nullable = true, string $defaultExpr = '0', int $concurrency = 0): void
$collection->addColumnUint32(string $name, bool $nullable = true, string $defaultExpr = '0', int $concurrency = 0): void
$collection->addColumnUint64(string $name, bool $nullable = true, string $defaultExpr = '0', int $concurrency = 0): void
$collection->dropColumn(string $name): void
$collection->renameColumn(string $oldName, string $newName, int $concurrency = 0): void
$collection->alterColumn(string $columnName, ?string $newName = null, ?int $newDataType = null, ?bool $nullable = null, int $concurrency = 0): void

// Index management
$collection->createIndex(string $fieldName, ZVecIndexParams $params, int $concurrency = 0): void
$collection->dropIndex(string $fieldName): void

// Deprecated index methods (use createIndex() + ZVecIndexParams instead)
$collection->createHnswIndex(...): void       // @deprecated
$collection->createHnswRabitqIndex(...): void // @deprecated
$collection->createFlatIndex(...): void       // @deprecated
$collection->createIvfIndex(...): void        // @deprecated
$collection->createInvertIndex(...): void     // @deprecated

// Document operations
$collection->insert(ZVecDoc ...$docs): void
$collection->upsert(ZVecDoc ...$docs): void
$collection->update(ZVecDoc ...$docs): void
$collection->insertBatch(ZVecDoc ...$docs): array     // Returns per-doc status array
$collection->upsertBatch(ZVecDoc ...$docs): array     // Returns per-doc status array
$collection->updateBatch(ZVecDoc ...$docs): array     // Returns per-doc status array
$collection->delete(string ...$pks): void
$collection->deleteByFilter(string $filter): void
$collection->fetch(string ...$pks): ZVecDoc[]

// Search
$collection->query(string|ZVecVectorQuery $fieldName, array $queryVector = [], int $topk = 10, ...): ZVecDoc[]
$collection->queryFp16(string $fieldName, array $queryVector, int $topk = 10, ...): ZVecDoc[]
$collection->queryFp64(string $fieldName, array $queryVector, int $topk = 10, ...): ZVecDoc[]
$collection->queryVector(ZVecVectorQuery $query): ZVecDoc[]
$collection->queryMulti(array $vectorQueries, ZVecReRanker $reranker, int $topk = 10, ?string $filter = null, ?array $outputFields = null): ZVecRerankedDoc[]
$collection->queryByFilter(string $filter, int $topk = 100, ?array $outputFields = null): ZVecDoc[]
$collection->queryById(string $fieldName, string $docId, ...): ZVecDoc[]
$collection->queryWithReranker(string|ZVecVectorQuery $fieldName, ..., ?ZVecReRanker $reranker = null): ZVecRerankedDoc[]
$collection->groupByQuery(string|ZVecVectorQuery $fieldName, ...): array
$collection->groupByVectorQuery(ZVecGroupByVectorQuery $query): array
```

### ZVecSchema

```php
$schema = new ZVecSchema('collection_name');

// Segment configuration
$schema->setMaxDocCountPerSegment(int $count): self

// Scalar fields
$schema->addInt64(string $name, bool $nullable = false, bool $withInvertIndex = false): self
$schema->addString(string $name, bool $nullable = false, bool $withInvertIndex = false): self
$schema->addFloat(string $name, bool $nullable = true): self
$schema->addDouble(string $name, bool $nullable = true): self
$schema->addBool(string $name, bool $nullable = false, bool $withInvertIndex = false): self
$schema->addInt32(string $name, bool $nullable = false, bool $withInvertIndex = false): self
$schema->addUint32(string $name, bool $nullable = false, bool $withInvertIndex = false): self
$schema->addUint64(string $name, bool $nullable = false, bool $withInvertIndex = false): self

// Dense vector fields
$schema->addVectorFp32(string $name, int $dimension, int $metricType = METRIC_IP): self
$schema->addVectorFp64(string $name, int $dimension, int $metricType = METRIC_IP): self
$schema->addVectorFp16(string $name, int $dimension, int $metricType = METRIC_IP): self
$schema->addVectorInt8(string $name, int $dimension, int $metricType = METRIC_IP): self
$schema->addVectorInt4(string $name, int $dimension, int $metricType = METRIC_IP): self
$schema->addVectorInt16(string $name, int $dimension, int $metricType = METRIC_IP): self
$schema->addVectorBinary32(string $name, int $dimension, int $metricType = METRIC_IP): self
$schema->addVectorBinary64(string $name, int $dimension, int $metricType = METRIC_IP): self

// Sparse vector fields
$schema->addSparseVectorFp32(string $name, int $metricType = METRIC_IP): self
$schema->addSparseVectorFp16(string $name, int $metricType = METRIC_IP): self

// Binary field
$schema->addBinary(string $name, bool $nullable = true): self

// Array fields
$schema->addArrayString(string $name, bool $nullable = true): self
$schema->addArrayBool(string $name, bool $nullable = true): self
$schema->addArrayInt32(string $name, bool $nullable = true): self
$schema->addArrayInt64(string $name, bool $nullable = true): self
$schema->addArrayUint32(string $name, bool $nullable = true): self
$schema->addArrayUint64(string $name, bool $nullable = true): self
$schema->addArrayFloat(string $name, bool $nullable = true): self
$schema->addArrayDouble(string $name, bool $nullable = true): self

// Metric type constants
$schema::METRIC_L2     = 1   // Euclidean distance
$schema::METRIC_IP     = 2   // Inner Product (default)
$schema::METRIC_COSINE = 3   // Cosine similarity
$schema::METRIC_MIPSL2 = 4   // Modified Inner Product with L2

// Note: addFieldBinary(), addFieldArrayString(), etc. are deprecated aliases
// that emit E_USER_DEPRECATED warnings.
```

### ZVecDoc

```php
$doc = new ZVecDoc('primary_key');

// Scalar setters (each returns self)
$doc->setInt64(string $field, int $value): self
$doc->setString(string $field, string $value): self
$doc->setFloat(string $field, float $value): self
$doc->setDouble(string $field, float $value): self
$doc->setBool(string $field, bool $value): self
$doc->setInt32(string $field, int $value): self
$doc->setUint32(string $field, int $value): self
$doc->setUint64(string $field, int $value): self

// Vector setters
$doc->setVectorFp32(string $field, array $vector): self
$doc->setVectorFp64(string $field, array $vector): self
$doc->setVectorFp16(string $field, array $vector): self   // uint16[] raw values
$doc->setVectorInt8(string $field, array $vector): self
$doc->setVectorInt4(string $field, array $vector): self
$doc->setVectorInt16(string $field, array $vector): self
$doc->setVectorBinary32(string $field, array $data): self
$doc->setVectorBinary64(string $field, array $data): self

// Sparse vector setters
$doc->setSparseVectorFp32(string $field, array $indices, array $values): self
$doc->setSparseVectorFp16(string $field, array $indices, array $values): self

// Binary setter
$doc->setBinary(string $field, string $data): self

// Array setters
$doc->setArrayInt32(string $field, array $data): self
$doc->setArrayInt64(string $field, array $data): self
$doc->setArrayUint32(string $field, array $data): self
$doc->setArrayUint64(string $field, array $data): self
$doc->setArrayFloat(string $field, array $data): self
$doc->setArrayDouble(string $field, array $data): self
$doc->setArrayString(string $field, array $strings): self
$doc->setArrayBool(string $field, array $data): self

// Document control
$doc->setFieldNull(string $field): self
$doc->removeField(string $field): self
$doc->clear(): self

// Scalar getters (each returns ?type, null if field missing)
$doc->getPk(): string
$doc->getScore(): float
$doc->getInt64(string $field): ?int
$doc->getString(string $field): ?string
$doc->getFloat(string $field): ?float
$doc->getDouble(string $field): ?float
$doc->getBool(string $field): ?bool
$doc->getInt32(string $field): ?int
$doc->getUint32(string $field): ?int
$doc->getUint64(string $field): ?int

// Vector getters (each returns ?array)
$doc->getVectorFp32(string $field): ?array     // float[]
$doc->getVectorFp64(string $field): ?array     // float[]
$doc->getVectorFp16(string $field): ?array     // uint16[]
$doc->getVectorInt8(string $field): ?array     // int[]
$doc->getVectorInt4(string $field): ?array     // int[]
$doc->getVectorInt16(string $field): ?array    // int[]
$doc->getVectorBinary32(string $field): ?array // uint32[]
$doc->getVectorBinary64(string $field): ?array // uint64[]

// Sparse vector getters
$doc->getSparseVectorFp32(string $field): ?array  // {indices: int[], values: float[]}
$doc->getSparseVectorFp16(string $field): ?array  // {indices: int[], values: uint16[]}

// Binary getter
$doc->getBinary(string $field): ?string

// Array getters
$doc->getArrayInt32(string $field): ?array     // int[]
$doc->getArrayInt64(string $field): ?array     // int[]
$doc->getArrayUint32(string $field): ?array    // int[]
$doc->getArrayUint64(string $field): ?array    // int[]
$doc->getArrayFloat(string $field): ?array     // float[]
$doc->getArrayDouble(string $field): ?array    // float[]
$doc->getArrayString(string $field): ?array    // string[]
$doc->getArrayBool(string $field): ?array      // bool[]

// Introspection
$doc->hasField(string $name): bool
$doc->hasVector(string $name): bool
$doc->fieldNames(): string[]
$doc->vectorNames(): string[]
$doc->isFieldNull(string $field): bool
$doc->isEmpty(): bool

// Serialization
$doc->serialize(): string
$doc = ZVecDoc::deserialize(string $data): self
$doc->merge(ZVecDoc $other): self

// Operator control
$doc->setOperator(int $op): self
$doc->getOperator(): int
$doc::OP_INSERT = 0
$doc::OP_UPDATE = 1
$doc::OP_UPSERT = 2
$doc::OP_DELETE = 3

// Utility
$doc->getMemoryUsage(): int
```

### ZVecIndexParams

```php
// Factory methods for each index type:

// HNSW — graph-based index for high-dimensional vectors
$params = ZVecIndexParams::forHnsw(
    int $metricType = METRIC_IP,
    int $m = 50,
    int $efConstruction = 500,
    int $quantizeType = QUANTIZE_UNDEFINED,
    bool $useContiguousMemory = false
): self

// HNSW-RaBitQ — HNSW with Randomized Bit Quantization
$params = ZVecIndexParams::forHnswRabitq(
    int $metricType = METRIC_IP,
    int $totalBits = 7,
    int $numClusters = 16,
    int $m = 50,
    int $efConstruction = 500,
    int $sampleCount = 0
): self

// Flat — brute force exact search
$params = ZVecIndexParams::forFlat(
    int $metricType = METRIC_IP,
    int $quantizeType = QUANTIZE_UNDEFINED
): self

// IVF — inverted file index for large-scale search
$params = ZVecIndexParams::forIvf(
    int $metricType = METRIC_IP,
    int $nList = 1024,
    int $nIters = 10,
    bool $useSoar = false,
    int $quantizeType = QUANTIZE_UNDEFINED
): self

// Vamana (DiskANN) — disk-based graph for 10K+ documents
$params = ZVecIndexParams::forVamana(
    int $metricType = METRIC_IP,
    int $maxDegree = 64,
    int $searchListSize = 100,
    float $alpha = 1.2,
    bool $saturateGraph = false,
    bool $useContiguousMemory = false,
    bool $useIdMap = false,
    int $quantizeType = QUANTIZE_UNDEFINED
): self

// Invert — keyword-based inverted index
$params = ZVecIndexParams::forInvert(
    bool $enableRange = true,
    bool $enableWildcard = false
): self

// Usage:
$collection->createIndex('embedding', ZVecIndexParams::forHnsw(ZVecSchema::METRIC_IP, quantizeType: ZVec::QUANTIZE_FP16));
```

### ZVecCollectionOptions

```php
$options = new ZVecCollectionOptions();
$options->readOnly = false;        // bool
$options->enableMmap = true;       // bool
$options->maxBufferSize = 67108864; // int (64 MB default)

// Factory methods:
$options = ZVecCollectionOptions::readOnly();
$options = ZVecCollectionOptions::readWrite();
$options = ZVecCollectionOptions::defaults();

// Fluent setters:
$options->setReadOnly(bool $readOnly): self
$options->getReadOnly(): bool
$options->setEnableMmap(bool $enableMmap): self
$options->getEnableMmap(): bool
$options->setMaxBufferSize(int $maxBufferSize): self
$options->getMaxBufferSize(): int

// Use with createWith/openWith:
$collection = ZVec::createWith('/path/to/collection', $schema, $options);
$collection = ZVec::openWith('/path/to/collection', $options);
```

### ZVecFieldSchema

```php
$schema = $collection->getFieldSchema('embedding');

$schema->getName(): string
$schema->getDataType(): int               // e.g. ZVec::TYPE_VECTOR_FP32
$schema->getElementDataType(): int         // element type for arrays/vectors
$schema->getElementDataSize(): int         // bytes per element
$schema->getDimension(): int               // vector dimension (0 for scalars)
$schema->isVectorField(): bool
$schema->isDenseVector(): bool
$schema->isSparseVector(): bool
$schema->isArrayType(): bool
$schema->isNullable(): bool
$schema->hasInvertIndex(): bool
$schema->hasIndex(): bool
$schema->getIndexType(): int              // ZVec::INDEX_TYPE_HNSW, etc.
```

### ZVecCollectionStats

```php
$stats = $collection->getStatsStruct();

$stats->getDocCount(): int
$stats->getIndexCount(): int
$stats->getIndexName(int $index): string
$stats->getIndexCompleteness(int $index): float
$stats->getAllIndexCompleteness(): array  // ['field' => completeness, ...]
$stats->toArray(): array                  // ['doc_count' => N, 'index_completeness' => [...]]
```

### ZVecVectorQuery

```php
$query = new ZVecVectorQuery('embedding', [0.1, 0.2, 0.3, ...]);

// Also create from document ID:
$query = ZVecVectorQuery::fromId('embedding', 'doc_1');

// Query parameters
$query->setFp64(bool $fp64 = true): self            // Use FP64 precision
$query->setHnswParams(int $ef): self                 // HNSW ef_search
$query->setHnswRabitqParams(int $ef): self           // HNSW-RaBitQ ef_search
$query->setIvfParams(int $nprobe): self              // IVF nprobe
$query->setFlatParams(): self                        // Brute force mode
$query->setVamanaParams(int $efSearch): self         // Vamana ef_search
$query->setRadius(float $radius): self               // Range search radius
$query->setLinear(bool $linear): self                // Linear scan
$query->setUsingRefiner(bool $refiner): self         // Two-stage refine
$query->setTopk(int $topk): self
$query->setIncludeVector(bool $include): self
$query->setFilter(string $filter): self
$query->setOutputFields(array $fields): self

// Use with:
$results = $collection->queryVector($query);
$groups = $collection->groupByVectorQuery($query);
```

### ZVecGroupByVectorQuery

```php
$query = new ZVecGroupByVectorQuery(
    string $fieldName,       // Vector field to search
    array $queryVector,      // Query vector
    string $groupByField,    // Field to group results by
    int $groupCount = 2,     // Number of groups
    int $groupTopk = 3       // Results per group
);

// Setters
$query->setGroupByField(string $field): self
$query->setGroupCount(int $count): self
$query->setGroupTopk(int $topk): self
$query->setRadius(float $radius): self
$query->setLinear(bool $linear): self
$query->setUsingRefiner(bool $refiner): self
$query->setIncludeVector(bool $include): self
$query->setFilter(string $filter): self
$query->setOutputFields(array $fields): self

// Note: setTopk(), setHnswParams(), setHnswRabitqParams(), setIvfParams(),
// setFlatParams(), and setVamanaParams() throw ZVecException for group-by queries.

// Usage:
$groups = $collection->groupByVectorQuery($query);
// Returns: [['group_value' => '...', 'docs' => ZVecDoc[]], ...]
```

### ZVecReRanker (Interface)

```php
interface ZVecReRanker {
    public function rerank(array $queryResults): array;
    // $queryResults format: ['fieldName' => ZVecDoc[], ...]
    // Returns: ZVecRerankedDoc[]
}
```

### ZVecRrfReRanker

Reciprocal Rank Fusion (RRF) reranker. Combines results from multiple vector fields using the RRF scoring formula: `score = sum(1 / (rankConstant + rank))`.

```php
$reranker = new ZVecRrfReRanker(int $topn = 10, int $rankConstant = 60);

$reranker->getTopn(): int
$reranker->setTopn(int $topn): self
$reranker->getRankConstant(): int
$reranker->setRankConstant(int $rankConstant): self

// Usage:
$results = $collection->queryWithReranker('embedding', [...], reranker: $reranker);
```

### ZVecWeightedReRanker

Weighted score fusion reranker. Combines results using normalized weighted scores (min-max normalisation per field).

```php
$reranker = new ZVecWeightedReRanker(
    array $weights,                       // ['field_name' => weight, ...]
    int $topn = 10,
    int $metricType = ZVecSchema::METRIC_IP
);

$reranker->getTopn(): int
$reranker->setTopn(int $topn): self
$reranker->getMetricType(): int
$reranker->setMetricType(int $metricType): self
$reranker->getWeights(): array
$reranker->setWeights(array $weights): self

// Usage:
$reranker = new ZVecWeightedReRanker(['embedding' => 0.7, 'keywords' => 0.3]);
$results = $collection->queryMulti(
    [$query1, $query2],
    $reranker,
    topk: 10
);
```

### ZVecRerankedDoc

Returned by reranker operations (`queryWithReranker()`, `queryMulti()`).

```php
$rerankedDoc->getPk(): string
$rerankedDoc->getOriginalScore(): float      // Original score from first-pass query
$rerankedDoc->getCombinedScore(): float      // Reranked combined score
$rerankedDoc->getDoc(): ZVecDoc              // Access the underlying document
$rerankedDoc->getSourceRanks(): array        // ['fieldName' => rank, ...]
$rerankedDoc->getSourceScores(): array       // ['fieldName' => score, ...]
```

### Data Types

| Type Constant | Value | PHP Type | Description |
|---|---|---|---|
| `TYPE_INT32` | 4 | `int` | 32-bit signed integer |
| `TYPE_INT64` | 5 | `int` | 64-bit signed integer |
| `TYPE_UINT32` | 6 | `int` | 32-bit unsigned integer |
| `TYPE_UINT64` | 7 | `int` | 64-bit unsigned integer |
| `TYPE_FLOAT` | 8 | `float` | 32-bit float (IEEE 754) |
| `TYPE_DOUBLE` | 9 | `float` | 64-bit double (IEEE 754) |
| `TYPE_BOOL` | 3 | `bool` | Boolean |
| `TYPE_STRING` | 10 | `string` | UTF-8 string |
| `TYPE_BINARY` | 1 | `string` | Binary blob |
| `TYPE_VECTOR_FP32` | 23 | `float[]` | 32-bit float vector |
| `TYPE_VECTOR_FP64` | 24 | `float[]` | 64-bit float vector |
| `TYPE_VECTOR_FP16` | 22 | `int[]` | 16-bit float vector (stored as uint16) |
| `TYPE_VECTOR_INT4` | 25 | `int[]` | 4-bit integer vector |
| `TYPE_VECTOR_INT8` | 26 | `int[]` | 8-bit integer vector |
| `TYPE_VECTOR_INT16` | 27 | `int[]` | 16-bit integer vector |
| `TYPE_VECTOR_BINARY32` | 20 | `array` | 32-dim binary vector |
| `TYPE_VECTOR_BINARY64` | 21 | `array` | 64-dim binary vector |
| `TYPE_SPARSE_VECTOR_FP32` | 31 | `array` | Sparse float vector |
| `TYPE_SPARSE_VECTOR_FP16` | 30 | `array` | Sparse half-precision vector |
| `TYPE_ARRAY_STRING` | 41 | `string[]` | String array |
| `TYPE_ARRAY_BOOL` | 42 | `bool[]` | Boolean array |
| `TYPE_ARRAY_INT32` | 43 | `int[]` | Int32 array |
| `TYPE_ARRAY_INT64` | 44 | `int[]` | Int64 array |
| `TYPE_ARRAY_UINT32` | 45 | `int[]` | UInt32 array |
| `TYPE_ARRAY_UINT64` | 46 | `int[]` | UInt64 array |
| `TYPE_ARRAY_FLOAT` | 47 | `float[]` | Float array |
| `TYPE_ARRAY_DOUBLE` | 48 | `float[]` | Double array |

### Index Types

| Constant | Value | Description |
|---|---|---|
| `INDEX_TYPE_HNSW` | 1 | Hierarchical Navigable Small World — graph-based, best for high-dim |
| `INDEX_TYPE_IVF` | 2 | Inverted File — partition-based, good for large-scale |
| `INDEX_TYPE_FLAT` | 3 | Brute force exact search — no index structure |
| `INDEX_TYPE_HNSW_RABITQ` | 4 | HNSW + RaBitQ quantization — memory-efficient |
| `INDEX_TYPE_VAMANA` | 5 | DiskANN — disk-based graph for 10K+ documents |
| `INDEX_TYPE_INVERT` | 10 | Keyword-based inverted index |

### Metric Types

| Constant | Value | Description |
|---|---|---|
| `METRIC_L2` | 1 | Euclidean distance (L2 norm) |
| `METRIC_IP` | 2 | Inner Product (dot product) |
| `METRIC_COSINE` | 3 | Cosine similarity |
| `METRIC_MIPSL2` | 4 | Modified Inner Product with L2 |

### Quantize Types

| Constant | Value | Description |
|---|---|---|
| `QUANTIZE_UNDEFINED` | 0 | No quantization (full precision) |
| `QUANTIZE_FP16` | 1 | 16-bit float (2x memory reduction) |
| `QUANTIZE_INT8` | 2 | 8-bit integer (4x memory reduction) |
| `QUANTIZE_INT4` | 3 | 4-bit integer (8x memory reduction) |
| `QUANTIZE_RABITQ` | 4 | RaBitQ for HNSW-RaBitQ index |

## Running Tests

### .phpt test suite (works with both FFI and native extension)
```bash
php run-tests.php tests/
```

### Integration tests
```bash
php run-tests.php tests/
```

## Project Structure

```
zvec-php/
├── src/                          # FFI-based PHP implementation
│   ├── ZVec.php                  # Main library class (ZVec)
│   ├── ZVecException.php         # Custom exception
│   ├── ZVecCollectionOptions.php # Collection open/create options
│   ├── ZVecCollectionStats.php   # Collection statistics object
│   ├── ZVecFieldSchema.php       # Field schema introspection
│   ├── ZVecIndexParams.php       # Index creation parameters
│   ├── ZVecQueryInterface.php    # Query interface
│   ├── ZVecVectorQuery.php       # Vector query builder
│   ├── ZVecGroupByVectorQuery.php # Group-by vector query builder
│   ├── ZVecSchema.php            # Schema definition
│   ├── ZVecDoc.php               # Document handle
│   ├── ZVecReRanker.php          # Base re-ranker interface
│   ├── ZVecRerankedDoc.php       # Reranked document class
│   ├── ZVecRrfReRanker.php       # RRF re-ranker
│   ├── ZVecWeightedReRanker.php  # Weighted re-ranker
│   ├── embeddings/               # Embedding function interfaces
│   └── Installer.php             # Composer CLI installer
├── examples/                     # Usage examples
├── php-ext/                      # Native PHP extension (C++)
│   ├── zvec_collection.cc/h      # ZVec class
│   ├── zvec_schema.cc/h          # ZVecSchema class
│   ├── zvec_doc.cc/h             # ZVecDoc class
│   ├── zvec_vector_query.cc/h    # ZVecVectorQuery class
│   ├── zvec_reranker.cc/h        # ZVecReRanker interface
│   ├── zvec_rrf_reranker.cc/h    # ZVecRrfReRanker
│   ├── zvec_weighted_reranker.cc/h # ZVecWeightedReRanker
│   ├── config.m4                 # phpize build configuration
│   └── build_ext.sh              # Build script
├── ffi/                          # C FFI bridge (used by php/ implementation)
│   ├── zvec_ffi.h                # C header with FFI declarations
│   ├── zvec_ffi_php.h            # PHP-specific FFI header
│   ├── zvec_ffi.cc               # C++ implementation
│   └── CMakeLists.txt            # Build configuration
├── tests/                        # .phpt test suite
├── tasks/                        # Feature planning documents
├── build_zvec_lib.sh             # Builds zvec C++ library
├── build_ffi.sh                  # Builds FFI shared library
├── build_zvec.sh                 # Orchestrator: both builds
└── zvec/                         # Git-cloned upstream zvec C++ library (not committed)
```

## Implemented Features

See `tasks/done/` for detailed planning documents.

- [x] IVF index creation support
- [x] QuantizeType support (FP16, INT8, INT4) on index creation
- [x] Add column STRING and BOOL types
- [x] Vector query by document ID (`queryById`)
- [x] Concurrency options for optimize/index/create ops
- [x] Additional scalar data types (BOOL, INT32, UINT32, UINT64)
- [x] Multi-vector query with reranking (`queryMulti`)
- [x] Sparse vector data operations (set/get/query)
- [x] Extended HNSW/IVF query parameters (isLinear, radius, refiner)
- [x] Alter column field schema (rename, change type, nullable)
- [x] Doc introspection (hasField, hasVector, fieldNames, vectorNames)
- [x] Extensions: rerankers (RRF, Weighted)
- [x] Extensions: embeddings (OpenAI, Qwen interfaces)
- [x] Vector query object interface (`ZVecVectorQuery`)
- [x] Per-doc status on batch operations (`insertBatch`, `upsertBatch`, `updateBatch`)
- [x] Max doc count per segment / max buffer size options
- [x] Reranker parameter in `query()` (two-stage retrieval)
- [x] FP16 vector support
- [x] Closed collection protection (exception instead of segfault)
- [x] Native PHP extension (`php-ext/`)
- [x] Vamana (DiskANN) index support
- [x] HNSW-RaBitQ index support
- [x] Invert index via ZVecIndexParams
- [x] ZVecCollectionOptions with factory methods (readOnly, readWrite, defaults)
- [x] Unified `createIndex()` / `ZVecIndexParams` API
- [x] FP64 vector support (query, schema, doc)
- [x] Binary field type
- [x] Array field types (STRING, BOOL, INT32, INT64, UINT32, UINT64, FLOAT, DOUBLE)
- [x] Group-by vector query builder (`ZVecGroupByVectorQuery`)
- [x] Version API (`getVersion()`, `checkVersion()`)
- [x] `allowedBasePath` security restriction in `init()`
- [x] Verbose error details with file/line info
- [x] Collection lifecycle options via `getOptions()`
- [x] Doc serialization/deserialization, merge, operator tracking
- [x] Doc memory usage introspection

## Security

See [`SECURITY.md`](./SECURITY.md) for the full security policy, including how to report vulnerabilities
and which versions are supported.

### Trust Model

The FFI shared library (`libzvec_ffi.so`) is loaded into the PHP process address
space and runs with the **same system privileges** as the PHP process itself. Only
load `.so` files from trusted sources. Pre-built libraries are downloaded from
GitHub Releases with SHA-256 checksum verification.

### Input Validation

- **Collection paths** (`$path` in `create()`/`open()`): Should NOT come from
  untrusted user input. An attacker could create or access arbitrary directories.
  Use `ZVec::init(allowedBasePath: '/data/zvec')` to restrict paths.
- **Filter expressions** (`$filter` in `query()`/`deleteByFilter()`): Passed
  directly to the zvec C++ engine. Sanitize any user-controlled filter strings.
  The filter language does not support SQL injection, but malformed expressions
  may cause errors or unexpected behavior.
- **Document primary keys**: Passed directly to the C++ layer. Ensure PK values
  are validated if sourced from user input.

### Memory Limits

Use `ZVec::init(memoryLimitMb: 1024)` to cap collection cache memory. Without
a memory limit, collections may consume all available system memory under heavy
write or search load.

### File Permissions

Collection directories inherit the OS default umask. On shared hosting, ensure
collection paths are not world-readable. Set restrictive permissions explicitly
after creation if needed:

```bash
chmod 700 /path/to/collection
```

### Supply Chain

Pre-built shared libraries are downloaded from GitHub Releases via `vendor/bin/zvec-install`.
Downloads use HTTPS with TLS verification (CA bundle). SHA-256 checksums are verified
before extraction using `hash_equals()` (timing-safe comparison).

For production deployments, build the library from source with `./build_zvec.sh`
for full supply chain control.

## Known Limitations

- **GroupByQuery**: The C++ API has this method but it returns all documents in a single group with empty group value. This is a known issue in upstream zvec (marked as "Coming Soon" in zvec docs).
- **Platform**: Pre-built FFI library available for Linux x86_64 (glibc). macOS builds coming soon.
- **musl Linux** (e.g., Alpine): musl-based Linux is not yet supported by the pre-built library. Build from source instead.
- **alterColumn()**: Requires `nullable` to be explicitly specified when changing data type. Cannot rename AND change type in one call. Only scalar numeric types (INT32, INT64, UINT32, UINT64, FLOAT, DOUBLE) are supported for type changes.
- **GroupByVectorQuery**: Does not support HNSW, IVF, Flat, or Vamana query parameters (`setTopk()`, `setHnswParams()`, etc. throw ZVecException).
- **queryById()**: Uses `fetch()` internally to get the source vector, then performs a second query. Not a single round-trip to the C++ layer.

### Known Security Limitations

| ID | Description | Issue | Status |
|---|---|---|---|
| **SEC-001** | SHA-256 checksum verification added to downloaded `.so` files | [#70](https://github.com/crazy-goat/php-zvec/issues/70) | ✅ Fixed v0.4.12 |
| **SEC-002** | `tempnam()` symlink race replaced with cryptographically random temp directory | [#71](https://github.com/crazy-goat/php-zvec/issues/71) | ✅ Fixed v0.4.12 |
| **SEC-004** | Null pointer safety in C++ FFI bridge — 50+ handle-accepting functions | [#73](https://github.com/crazy-goat/php-zvec/issues/73) | 🔄 In Progress |
| **SEC-008** | API key masking in `var_dump()` + `sodium_memzero()` in destructor | [#76](https://github.com/crazy-goat/php-zvec/issues/76) | ✅ Fixed v0.4.12 |
| **SEC-012** | Explicit SSL certificate verification in embedding API requests | [#80](https://github.com/crazy-goat/php-zvec/issues/80) | ✅ Fixed v0.4.12 |

## License

Same as upstream zvec project (Apache License 2.0)
