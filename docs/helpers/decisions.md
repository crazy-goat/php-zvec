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
strings. The normalization matches the upstream C API byte-for-byte
(zvec/src/binding/c/c_api.cc:7024-7050) — one nullable-absent field
round-trips differently than before issue #192 (was: absent; now:
present-null).

**Reference:** issue #192.
