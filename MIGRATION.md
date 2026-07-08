# Migration Guide: v0.4.x → v0.5.0

This guide helps users of the deprecated v0.4.x APIs migrate to the modern
v0.5.0 APIs. All deprecated methods still work but emit `E_USER_DEPRECATED`
warnings and will be removed in v0.6.0.

## Index Creation

The four separate `create*Index()` methods have been replaced by a unified
`createIndex()` + `ZVecIndexParams` pattern.

### HNSW Index

```php
// BEFORE (deprecated — removed in v0.6.0):
$collection->createHnswIndex(
    'embedding',
    ZVecSchema::METRIC_IP,
    50,    // $m
    500,   // $efConstruction
    0,     // $quantizeType
    0,     // $concurrency
    false  // $useContiguousMemory
);

// AFTER (recommended):
$collection->createIndex('embedding', ZVecIndexParams::forHnsw(
    metricType: ZVecSchema::METRIC_IP,
    m: 50,
    efConstruction: 500,
    quantizeType: ZVec::QUANTIZE_UNDEFINED,
    useContiguousMemory: false,
));
```

**Note:** `$concurrency` is no longer part of the index params; use the
`$concurrency` parameter on `createIndex()` directly if needed:
`$collection->createIndex('embedding', $params, concurrency: 4)`.

### Flat Index

```php
// BEFORE (deprecated — removed in v0.6.0):
$collection->createFlatIndex(
    'embedding',
    ZVecSchema::METRIC_IP,
    0, // $quantizeType
    0  // $concurrency
);

// AFTER (recommended):
$collection->createIndex('embedding', ZVecIndexParams::forFlat(
    metricType: ZVecSchema::METRIC_IP,
    quantizeType: ZVec::QUANTIZE_UNDEFINED,
));
```

### IVF Index

```php
// BEFORE (deprecated — removed in v0.6.0):
$collection->createIvfIndex(
    'embedding',
    ZVecSchema::METRIC_IP,
    1024,  // $nList
    10,    // $nIters
    false, // $useSoar
    0,     // $quantizeType
    0      // $concurrency
);

// AFTER (recommended):
$collection->createIndex('embedding', ZVecIndexParams::forIvf(
    metricType: ZVecSchema::METRIC_IP,
    nList: 1024,
    nIters: 10,
    useSoar: false,
    quantizeType: ZVec::QUANTIZE_UNDEFINED,
));
```

### HNSW-RaBitQ Index

```php
// BEFORE (deprecated — removed in v0.6.0):
$collection->createHnswRabitqIndex(
    'embedding',
    ZVecSchema::METRIC_IP,
    7,    // $totalBits
    16,   // $numClusters
    50,   // $m
    500,  // $efConstruction
    0,    // $sampleCount
    0     // $concurrency
);

// AFTER (recommended):
$collection->createIndex('embedding', ZVecIndexParams::forHnswRabitq(
    metricType: ZVecSchema::METRIC_IP,
    totalBits: 7,
    numClusters: 16,
    m: 50,
    efConstruction: 500,
    sampleCount: 0,
));
```

### Vamana (DiskANN) Index

The old API had no Vamana support. This is new in v0.5.0:

```php
// NEW (no prior equivalent):
$collection->createIndex('embedding', ZVecIndexParams::forVamana(
    metricType: ZVecSchema::METRIC_COSINE,
    maxDegree: 64,
    searchListSize: 100,
    alpha: 1.2,
    saturateGraph: false,
    useContiguousMemory: false,
    useIdMap: false,
    quantizeType: ZVec::QUANTIZE_UNDEFINED,
));
```

### Inverted Index

Inverted indexes are also available via `ZVecIndexParams`:

```php
// NEW (no prior equivalent):
$collection->createIndex('title', ZVecIndexParams::forInvert(
    enableRange: true,
    enableWildcard: false,
));
```

The old `createInvertIndex()` method is not deprecated and works alongside
the new API.

## Statistics

The old `stats()` method returns a JSON string that needs manual parsing.
The new `getStatsStruct()` returns a typed `ZVecCollectionStats` object.

```php
// BEFORE:
$json = $collection->stats();
$data = json_decode($json, true);
echo $data['doc_count'];       // int
echo $data['index_count'];     // int
echo $data['segment_count'];   // int
echo $data['index_completeness']; // float

// AFTER:
$stats = $collection->getStatsStruct();
echo $stats->getDocCount();            // int
echo $stats->getIndexCount();          // int
echo $stats->getSegmentCount();        // int
echo $stats->getIndexCompleteness();   // float
echo $stats->getIndexNames();          // string[]
```

**Benefits:** Type-safe, no JSON decode needed, IDE autocompletion.

## Schema Introspection

`getFieldSchema()` is new in v0.5.0 — no prior equivalent.

```php
// New API:
$schema = $collection->getFieldSchema('embedding');
echo $schema->getName();          // "embedding"
echo $schema->getDataType();      // 23 = TYPE_VECTOR_FP32
echo $schema->getDimension();     // 768
echo $schema->getMetricType();    // 2 = METRIC_IP
echo $schema->isVectorField();    // true
echo $schema->isSparseVector();   // false
```

Also new: `ZVecFieldSchema` exposes `getElementType()` for array fields and
proper nullable detection.

## Collection Options

The old `create()` / `open()` methods used flat boolean/integer parameters.
The new `ZVecCollectionOptions` object provides a structured, extensible way
to configure collection creation and opening.

```php
// BEFORE:
$collection = ZVec::create(
    $path,
    $schema,
    false,               // $readOnly
    true,                // $enableMmap
    67108864             // $maxBufferSize
);

// AFTER:
$options = new ZVecCollectionOptions(
    readOnly: false,
    enableMmap: true,
    maxBufferSize: 67108864,
);
$collection = ZVec::createWith($path, $schema, $options);

// Or use factory methods:
$options = ZVecCollectionOptions::defaults();
$options->setReadOnly(true)
       ->setMaxBufferSize(134217728);  // 128 MB
$collection = ZVec::openWith($path, $options);
```

Factory methods available:
- `ZVecCollectionOptions::readOnly()` — open in read-only mode
- `ZVecCollectionOptions::readWrite()` — explicit read-write mode
- `ZVecCollectionOptions::defaults()` — default settings

## Query Object Pattern

The old `query()` method accepted many positional parameters. The new
`ZVecVectorQuery` builder provides a fluent, self-documenting alternative.

```php
// BEFORE:
$results = $collection->query(
    'embedding',
    [0.1, 0.2, 0.3, 0.4],
    topk: 10,
    includeVector: true,
    filter: 'category = "electronics"',
    outputFields: ['name', 'price'],
    hnswEf: 200,
);

// AFTER:
$query = new ZVecVectorQuery('embedding', [0.1, 0.2, 0.3, 0.4]);
$query->setTopk(10)
      ->setIncludeVector(true)
      ->setFilter('category = "electronics"')
      ->setOutputFields(['name', 'price'])
      ->setHnswParams(ef: 200);

$results = $collection->queryVector($query);
```

The `queryVector()` method accepts the query object directly and returns
the same `ZVecDoc[]`.

## Reranker in Queries

The `$reranker` parameter on `query()` is deprecated. Use `queryWithReranker()`
instead for type-safe reranked results.

```php
// BEFORE (deprecated):
$results = $collection->query(
    'embedding',
    [0.1, 0.2, 0.3, 0.4],
    topk: 10,
    reranker: new ZVecRrfReRanker(topn: 10),
);
// Returns ZVecDoc[]|ZVecRerankedDoc[] — ambiguous type

// AFTER (recommended):
$reranker = new ZVecRrfReRanker(topn: 10);
$results = $collection->queryWithReranker(
    'embedding',
    [0.1, 0.2, 0.3, 0.4],
    topk: 10,
    reranker: $reranker,
);
// Returns ZVecRerankedDoc[] — always typed
```

## Deprecated Schema Methods

The old `addField*()` prefix methods are deprecated. Use the unprefixed versions.

```php
// BEFORE (deprecated):
$schema->addFieldBinary('blob');
$schema->addFieldArrayString('tags');
$schema->addFieldArrayBool('flags');

// AFTER (recommended):
$schema->addBinary('blob');
$schema->addArrayString('tags');
$schema->addArrayBool('flags');
```

Full list of renamed methods:

| Deprecated (old) | Recommended (new) |
|---|---|
| `addFieldBinary()` | `addBinary()` |
| `addFieldArrayString()` | `addArrayString()` |
| `addFieldArrayBool()` | `addArrayBool()` |
| `addFieldArrayInt32()` | `addArrayInt32()` |
| `addFieldArrayInt64()` | `addArrayInt64()` |
| `addFieldArrayUint32()` | `addArrayUint32()` |
| `addFieldArrayUint64()` | `addArrayUint64()` |
| `addFieldArrayFloat()` | `addArrayFloat()` |
| `addFieldArrayDouble()` | `addArrayDouble()` |
