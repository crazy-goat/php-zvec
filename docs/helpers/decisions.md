# Decisions — Important Choices & Rationale

Knowledge base maintained by `coder`/`review` subagents. Read before
starting a task, append after finishing. See `README.md` for rules.

---

### Enum values must match Node.js / Python SDKs

**Decision:** PHP constants use identical values to the official SDKs
(e.g. `QUANTIZE_INT8 = 2`, `METRIC_IP`, `TYPE_FLOAT = 8`).

**Rationale:** keeps the PHP API compatible across languages; the Node.js
API, Python SDK and C++ headers are the reference implementations.

---

### PHP 8.1+ with full type declarations

**Decision:** all properties, parameters and return types are fully typed;
union types (`FFI\CData|string`) and PHPDoc only where PHP's type system
is insufficient (array generics).

**Rationale:** safety and self-documenting code; no redundant PHPDoc.

---

### FFI declarations inline in `FFI::cdef()`

**Decision:** inline C declarations in `FFI::cdef()` — never load the `.h`
file at runtime.

**Rationale:** no runtime dependency on header paths.

---

### Global classes via Composer classmap

**Decision:** global classes (`ZVec`, `ZVecSchema`, `ZVecDoc`, ...) are
loaded via Composer classmap autoloading; `src/ZVec.php` is a barrel file
for backward compatibility with `require_once`.

---

### Alter column: scalar numeric types only, nullable never turns off

**Decision:** `alterColumn()` supports renaming and type change for scalar
numeric types only; `nullable: true → false` is not supported; rename and
type change cannot be combined in one call.

**Rationale:** limitation of the underlying C++ layer — verify in
`zvec/src/include/zvec/db/` before extending.

---

### fetch(): `includeVector` is a named argument captured from the variadic

**Decision:** `fetch(array|string|bool ...$args)` — `includeVector:` is
read from the variadic as a named argument (default `true`), not declared
as a positional parameter. Positional bools are rejected with
`ZVecException`. Additionally, fetch mirrors upstream
`normalize_nullable_fields_for_fetch` (c_api.cc): absent **nullable**
fields are returned as present-with-null after fetch (`hasField()` true,
`isFieldNull()` true); non-nullable absent fields stay absent.

**Rationale:** PHP grammar forbids parameters after a variadic, and the
declared variadic type must include `bool` or named bools are coerced to
strings in weak-mode callers (`TypeError` under `strict_types=1`). The
normalization matches the upstream C API byte-for-byte
(zvec/src/binding/c/c_api.cc:7024-7050) — one nullable-absent field
round-trips differently than before issue #192 (was: absent; now:
present-null). Note: the Python SDK (pybind `Fetch`) does **not** apply
this normalization — PHP intentionally follows the C API here.

**Reference:** issue #192.

---

### resetRadiusThreshold(): caller-driven purge instead of automatic mitigation

**Decision:** issue #200 (radius threshold leak) is mitigated with an
explicit `ZVecCollection::resetRadiusThreshold(string $fieldName, array
$vector)` + `ZVec::RADIUS_THRESHOLD_RESET` (3.4028235e38) constant, not by
transparently fixing every radius-less query in the bindings.

**Rationale:** the leak lives in the upstream zvec core (thread-local
context, no-op Flat/IVF reset); hiding it in `queryVector()` would need
per-call purge queries (cost) and could change result semantics. The
explicit call documents the upstream bug at the API surface, costs one
topk-1 query, and is a no-op where the leak cannot occur (IP metrics).
Denormalization detail: `set_threshold()` denormalizes before storing
(`index_context.h:235-241`), so the purge value must survive sign-flip
(IP: -FLT_MAX is still ≥ every IP score) and MipsL2 (-1) — FLT_MAX does;
`reset_threshold()`'s raw FLT_MAX is unreachable from the C API.

**Reference:** issue #200.
