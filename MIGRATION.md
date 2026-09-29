# Migration Guide

Two guides live in this file, each covering one upgrade:

- **[v0.6.0 → v0.7.0](#migration-guide-v060--v070)** (at the bottom) — new
  additive API, the prebuilt zvec SDK, and the test-suite invocation change.
- **[v0.4.x → v0.5.0](#migration-guide-v04x--v050)** (below) — replacing the
  deprecated v0.4.x APIs with the modern ones.

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

---

# Migration Guide: v0.5.x → v0.6.0

**There is no action required.** The public PHP API is fully backward
compatible: nothing was renamed, removed, or retyped. Every change listed
below is additive.

All breaking changes for zvec v0.6.0 were confined to the C++ FFI layer
(`ffi/zvec_ffi.*`), which is internal. If you build the FFI library yourself,
the one thing that matters is the `ffi/CMakeLists.txt` change noted at the
end — nothing to do in your own code.

## New features

All of these are additions to an existing class; you only adopt them if you
want the capability.

### `fetch()` — select fields and skip vectors

```php
// NEW in v0.6.0 — no prior equivalent:
$docs = $collection->fetch(['pk1', 'pk2'], ['name', 'score']);
$docs = $collection->fetch('pk1', includeVector: false);
```

BC: the legacy variadic form `fetch('pk1', 'pk2')` still returns every field.
Mixing scalar PKs with an output-fields array is rejected with a hint.

### Random rotation for INT8/INT4 quantization

```php
// NEW in v0.6.0:
$params = ZVecIndexParams::forHnsw(
    metricType: ZVecSchema::METRIC_IP,
    quantizeType: ZVec::QUANTIZE_INT8,
);
$params->setQuantizerEnableRotate(true);
```

Rotation is applied before quantization and reduces quantization error.

### `include_doc_id` — return the internal document id

```php
// NEW in v0.6.0:
$query = new ZVecVectorQuery('embedding', $vector);
$query->setIncludeDocId(true);
foreach ($collection->queryVector($query) as $doc) {
    echo $doc->getDocId() . ': ' . $doc->getPk() . "\n";
}
```

Read-only: ranking, filtering and match sets are unaffected. `getDocId()`
returns `0` unless the query enabled the flag. Ids are 0-based upstream —
treat the base as an implementation detail, not a contract.

### DiskANN index and query params

`Vamana` is the in-memory graph of the DiskANN family; `DiskANN` is the
distinct disk-based index for billion-scale corpora.

```php
// NEW in v0.6.0:
$collection->createIndex('embedding', ZVecIndexParams::forDiskAnn(
    metricType: ZVecSchema::METRIC_COSINE,
    maxDegree: 100,
    listSize: 50,
    pqChunkNum: 0,
));

$query = new ZVecVectorQuery('embedding', $vector);
$query->setDiskAnnParams(listSize: 200);
```

New constants: `ZVec::INDEX_TYPE_DISKANN` and `ZVec::QUERY_PARAM_DISKANN`, both `6`.

### Full-Text Search

```php
// NEW in v0.6.0:
$collection->createIndex('body', ZVecIndexParams::forFts(
    tokenizer: 'standard',
    filters: ['lowercase'],
));

$query = new ZVecVectorQuery('body', []);
$query->setTopk(10)->setFts('body', 'fox rabbit');
$results = $collection->queryVector($query);
```

Pass either `queryString` or `matchString` to `setFts()`, not both and not
neither — upstream accepts exactly one, and the PHP layer rejects the other
two cases up front. `setFts()` replaces the query's vector clause, so it must
not be combined with a dense query vector.

New constants: `ZVec::INDEX_TYPE_FTS` and `ZVec::QUERY_PARAM_FTS` (both `11`),
plus `ZVec::FTS_OPERATOR_OR` and `ZVec::FTS_OPERATOR_AND`.

Not yet covered: hybrid dense + FTS retrieval through `queryMulti()`, and
stemming filters beyond those passed to `forFts()`.

### HNSW search prefetch

```php
// NEW in v0.6.0:
$query->setHnswPrefetch(prefetchOffset: 256, prefetchLines: 4);
```

Order-independent with respect to `setHnswParams()` — both are remembered by
the query. `0` disables prefetching; negative values throw.

## Not available

Two knobs from the same feature request are **not** exposed, because the zvec
v0.6.0 C++ and C APIs do not surface them: mmap copy-on-write configuration
and index dirty status. `CollectionOptions` still carries only `read_only`,
`enable_mmap` and `max_buffer_size`, and `is_dirty` exists only on an internal
buffer struct. These will appear if and when upstream exposes them.

## For maintainers: FFI build

zvec v0.6.0 builds its DiskANN algorithm as a separate `core_knn_diskann`
library and deliberately filters those sources out of `libzvec_core`, so the
`INDEX_FACTORY_REGISTER_*` statics never reach `libzvec_core.a`. Linking
`libcore_knn_diskann.a` in `ffi/CMakeLists.txt` is required, otherwise index
creation fails at runtime with `DiskAnn factory entries are not registered`.

`core_framework` and `core_knn_cluster` are deliberately *not* listed:
`libzvec_core.a` already contains those objects and naming them duplicates
every shared symbol.

---

# Migration Guide: v0.6.0 → v0.7.0

Everything below is **additive**. No existing method was removed, renamed or
retyped, so existing code keeps working unchanged. The one change that can
affect you is how the test suite is invoked (see the last section), and the
switch to a prebuilt zvec SDK.

## New: a scalar index can be declared in the schema

Previously an FTS or invert index on a STRING field needed two steps: create
the collection, then call `createIndex()`. The index can now be part of the
schema, so it exists from the first insert.

```php
// BEFORE — two steps, index absent until createIndex():
$schema = new ZVecSchema('docs');
$schema->addString('body');
$collection = ZVec::create($path, $schema);
$collection->createIndex('body', ZVecIndexParams::forFts());

// AFTER — one step:
$schema = new ZVecSchema('docs');
$schema->addString('body', indexParams: ZVecIndexParams::forFts());
$collection = ZVec::create($path, $schema);
```

The new argument is last and optional, so `addString('body')` and
`addString('body', withInvertIndex: true)` are unchanged. Two notes:

- Passing both `$withInvertIndex` and `$indexParams` now throws
  (`Use either $withInvertIndex or $indexParams, not both`) instead of one
  silently winning.
- A mismatched index type is reported by `ZVec::create()` naming the field, e.g.
  `scalar field[body] does not support vector index params`. That is unchanged
  behaviour, just now reachable without a second call.

## New: IVF-RaBitQ index type

An IVF partitioned index storing RaBitQ-quantized vectors. Mirrors the Python
`IvfRabitqIndexParam` / `IvfRabitqQueryParam`.

```php
$collection->createIndex('v', ZVecIndexParams::forIvfRabitq(
    metricType: ZVecSchema::METRIC_L2,
    nList: 1024,
    totalBits: 7,      // 1..9
    sampleCount: 0,
));

$query = (new ZVecVectorQuery('v', $vector))
    ->setTopk(10)
    ->setIvfRabitqParams(nprobe: 16);
```

New constants `ZVec::INDEX_TYPE_IVF_RABITQ` and `ZVec::QUERY_PARAM_IVF_RABITQ`,
both `7`.

**Platform:** upstream supports RaBitQ on **Linux x86_64 with AVX2+FMA or
AVX-512 only**, and rejects an FP64 field, a dimension outside 64–4095, or a
metric other than L2/IP/COSINE. On other platforms `createIndex()` fails with
`NOT_SUPPORTED`. Note that `QUANTIZE_RABITQ` is *not* what selects this index —
it is implied by the index type, and passing it to plain `forIvf()` is rejected.

## New: Vamana `two_pass_build` and query prefetch

`two_pass_build` runs a second full-graph Vamana construction pass: better graph
quality, slower build. It is a trailing optional parameter, so existing
positional calls to `forVamana()` are unaffected.

```php
$params = ZVecIndexParams::forVamana(
    metricType: ZVecSchema::METRIC_IP,
    maxDegree: 64,
    // ...
    twoPassBuild: true,
);
```

`setVamanaPrefetch()` tunes search-time software prefetch, the counterpart of
the existing `setHnswPrefetch()`. A `prefetchOffset` of `0` disables prefetch;
`0` lines means "derive from vector size". The call order relative to
`setVamanaParams()` does not matter.

```php
$query = (new ZVecVectorQuery('v', $vector))
    ->setTopk(10)
    ->setVamanaPrefetch(256, 4);
```

> **Known limitation:** prefetch — HNSW *and* Vamana — is honoured only by
> `queryVector()`. The legacy `query()` path sends just `queryParamType`,
> `ef`/`nprobe`, `radius`, `isLinear` and `isUsingRefiner` upstream, so prefetch
> is silently dropped there. This is not new in v0.7.0; it applies equally to
> `setHnswPrefetch()`.

## New: `ZVec::init()` tuning options

```php
ZVec::init(
    // ...existing parameters...
    ?string $jiebaDictDir = null,             // folder with jieba.dict.utf8 + hmm_model.utf8
    ?float $ftsBruteForceByKeysRatio = null,  // 0.0-1.0, upstream default 0.05
);
```

Read back with `ZVec::getJiebaDictDir()` and
`ZVec::getFtsBruteForceByKeysRatio()`. With neither option given, the jieba
dictionary shipped in `zvec_data/jieba_dict` is still found automatically.

Like every other `init()` option, both take effect only on the **first**
successful `init()` in a process; upstream ignores later calls. `null` is the
"keep the upstream default" marker, because `0.0` is a real value upstream
accepts.

Both are validated in PHP before any FFI call, which is the only place the
check can live: upstream `GlobalConfig::initialize()` sets its initialized flag
*before* validating, so a rejected value would leave the library marked
initialized and make every later `init()` a silent no-op. A `jiebaDictDir`
missing its dictionary files is rejected too, for a worse reason — cppjieba
calls `abort()`, killing the process with exit code 134 rather than raising a
catchable error.

## New: DiskANN I/O backend introspection

zvec picks an I/O backend for DiskANN disk reads on first use — on Linux it
tries `io_uring`, then `libaio`, then falls back to synchronous `pread()`;
macOS always uses `pread()`. The choice dominates DiskANN throughput and was
previously unobservable.

```php
ZVec::getIoBackendType();          // ZVec::IO_BACKEND_PREAD | LIBAIO | IO_URING
ZVec::getIoBackendTypeName(2);     // "io_uring"
ZVec::getIoBackendDescription();   // human-readable, with install hints on Linux
```

New constants `ZVec::IO_BACKEND_PREAD` / `IO_BACKEND_LIBAIO` /
`IO_BACKEND_IO_URING` (`0` / `1` / `2`). The getters work without
`ZVec::init()` — the backend is resolved lazily and the value is process-wide.

If you see `pread` on Linux, neither io_uring nor libaio could be loaded, so
DiskANN reads are synchronous. Installing libaio (`libaio1t64` on Ubuntu
24.04+) enables async I/O.

## Changed: the test suite must be run with FFI enabled

This is the one change that can break someone's workflow.

```bash
# BEFORE — silently skipped most of the suite on many machines:
php run-tests.php -n tests/

# AFTER:
php run-tests.php -n -d extension=ffi.so tests/
```

`-n` keeps a pre-installed legacy `zvec` extension from shadowing the FFI
classes, but it also strips `php.ini` — so wherever FFI is provided by a
`conf.d` ini rather than compiled in, FFI disappears with it. Measured on a
glibc host with PHP 8.5.4: 16 passed, **172 skipped**, 1 failed, and the run
still printed a green-looking summary.

If your PHP has FFI compiled in, drop the `-d extension=ffi.so` flag. Either
way, check that `Tests skipped` is `0` before trusting a run — a large skip
count means FFI was not loaded, not that the code is fine.

## Changed: zvec is now a prebuilt SDK

zvec is no longer compiled from source. `./fetch_zvec_sdk.sh` downloads the
official prebuilt SDK from the
[alibaba/zvec releases](https://github.com/alibaba/zvec/releases) (pinned
SHA-256) and `./build_zvec.sh` builds only our adapter.

If you build from source or vendor `libzvec`, the important consequence is
that `libzvec` and `zvec_data/` (the jieba dictionary) must sit in the same
directory as `libzvec_ffi`. The adapter resolves libzvec via an `$ORIGIN` /
`@loader_path` rpath, so a Composer install under `lib/` needs all three.

Release asset names changed: `libzvec_ffi-<platform>.tar.gz` instead of
`libzvec_ffi-ubuntu24-x86_64.tar.gz`. `vendor/bin/zvec-install` handles this
automatically; only custom CI that fetches assets by name needs updating.

## Unchanged

- Every pre-v0.7.0 method, constant and behaviour.
- All previously deprecated `create*Index()` and `addField*()` methods still
  work and still emit `E_USER_DEPRECATED`.
- `queryById()`, `queryMulti()`, `queryWithReranker()` and
  `groupByQuery()` signatures.

