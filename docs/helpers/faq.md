# FAQ — Recurring Pitfalls & Solutions

Knowledge base maintained by `coder`/`review` subagents. Read before
starting a task, append after finishing. See `README.md` for rules.

---

### Run tests with `-n` (no php.ini)

**Problem:** a legacy pre-installed `zvec` PHP extension (v0.4.10) in
`php.ini` shadows the FFI classes (`src/ZVec.php` bails out early), so FFI
classes like `ZVecIndexParams` never load and tests fail en masse.

**Solution:** always run the suite as
`php run-tests.php -n tests/` — the `-n` flag disables the extension.

**Reference:** issue #188.

---

### `run-tests.php` deletes legacy `tests/*.php` files

**Problem:** `run-tests.php` derives the executable path from the `.phpt`
basename and unlinks it unconditionally — legacy tracked
`tests/<name>.php` files sharing a basename with a `.phpt` file get
deleted.

**Solution:** back them up first, or restore with
`git checkout -- tests/` after the run. Note the restore also reverts
local edits — keep fixes re-applied.

**Reference:** issue #187.

---

### Always call `optimize()` before performance testing

**Problem:** after inserting docs, `index_completeness: 0` — queries work
but fall back to brute-force scan.

**Solution:** call `optimize()` before performance tests.

---

### Free C strings and follow status checks

**Problem:** memory leaks in FFI bindings.

**Solution:** always convert with `FFI::string($ptr)` and `FFI::free($ptr)`;
never store `FFI\CData` in long-lived variables; check status after every
FFI call via `self::checkStatus()`.

---

### `destroy()` invalidates the handle

**Problem:** after `destroy()` any method call segfaults.

**Solution:** only `close()` if the collection may be reopened; never use
an object after `destroy()`.

---

### queryVector() enforces query-param/index-type match

**Problem:** `queryVector()` rejects a query when the params' index type
(`HnswQueryParams`, `VamanaQueryParams`, ...) differs from the field's index
type (validation in `zvec/src/db/index/common/query.cc`).
`setVamanaParams()`/`setHnswRabitqParams()` used to call
`zvec_vector_query_set_hnsw_ef` (HnswQueryParams), so queryVector() on
Vamana/RaBitQ indexes always failed with INVALID_ARGUMENT.

**Solution:** dedicated FFI setters create the matching params class:
`zvec_vector_query_set_vamana_ef_search` → `VamanaQueryParams`,
`zvec_vector_query_set_hnsw_rabitq_ef` → `HnswRabitqQueryParams`.
The legacy `query()` path was never affected (it rebuilds params per
`queryParamType`).

**Reference:** issue #193.

---

### PHP forbids parameters after a variadic — capture named args instead

**Problem:** adding an option to a variadic API as
`fetch(...$args, ?bool $includeVector = true)` is a parse error
("Only the last parameter can be variadic"), and under
`array|string ...$args` a bool named arg is silently string-coerced
(`includeVector: false` → `""`) before it reaches the method body in
weak-mode callers; `strict_types=1` callers get a `TypeError` instead.

**Solution:** widen the variadic (`array|string|bool ...$args`), extract
the named key (`$args['includeVector'] ?? true`), `unset()` it **before**
any arg-count validation, then validate with `is_bool()`.

**Reference:** issue #192.

---

### Call `close()`/`destroy()` before deleting the collection directory

**Problem:** if the collection directory is `rm -rf`'d while the
collection handle is still open (e.g. only in test `finally`), RocksDB
flushes at process shutdown fail and spew ~10 lines of console ERROR
output (rocksdb_context.cc, inverted_indexer.cc, id_map.cc) — failing
`.phpt` output comparison.

**Solution:** call `$c->close()` (or `destroy()`) inside the test `try`
block, before the `finally` cleanup removes the directory.

**Reference:** issue #192 (new test initially failed on shutdown noise).

---

### Radius query poisons later queries on the same thread (Flat/IVF)

**Problem:** one `setRadius(>0)` query makes every later radius-less query
on the same thread (refiner, plain, any field with the same index type,
even other collections) return only the radius-filtered subset — silently.
Upstream caches the threshold on a `thread_local` context shared per index
type (`zvec/src/core/interface/index.cc:33-35`) and Flat/IVF `reset()` is
a no-op; the `if (radius > 0.0f)` gate in `flat_index.cc:63` blocks
resetting it to 0.

**Solution:** call `$c->resetRadiusThreshold($field, $queryVector)` after
radius-filtered queries. It runs a throwaway topk-1 query with
`radius = FLT_MAX`, which overwrites the stale threshold. Use the
`ZVec::RADIUS_THRESHOLD_RESET` constant, not `PHP_FLOAT_MAX` — the latter
overflows to `inf` in float32 (untested upstream semantics); FLT_MAX is
what upstream's own `reset_threshold()` uses. For IP metrics radius
filters docs with similarity below `-radius` (e.g. opposite vectors), so
the leak CAN occur there too — verified: `resetRadiusThreshold()` restores
the missing negative-similarity docs. With corpora where every similarity
is ≥ `-radius`, the call is a harmless no-op.

**Reference:** issue #200, `tests/test_radius_threshold_leak.phpt`.
