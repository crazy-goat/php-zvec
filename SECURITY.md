# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| >= 0.4.11 | ✅ |

## Reporting a Vulnerability

Please open a GitHub issue with the `type:security` label, or contact the maintainers directly.

## Security Mitigations

### TOCTOU / Symlink Race in Temp File Creation

**Status:** Fixed in v0.4.12

**Issue:** [SEC-002 (#71)](https://github.com/crazy-goat/php-zvec/issues/71)

**Problem:** The `Installer::install()` method previously used `tempnam()` + `.tar.gz` suffix to create a temporary download file. An attacker with local filesystem access could predict the path and plant a symlink, causing the downloaded archive to be written to an attacker-controlled location (arbitrary file write). The orphaned `tempnam()` file was also never cleaned up.

**Solution:** The temporary file strategy was replaced with a cryptographically random directory (128 bits via `random_bytes(8)` + `bin2hex`), created with `mkdir()` at permission 0700:

```
$tmpDir = sys_get_temp_dir() . '/zvec_ffi_' . bin2hex(random_bytes(8));
mkdir($tmpDir, 0700, true);
$tmpFile = $tmpDir . '/download.tar.gz';
```

Key properties:
- **Defense-in-depth**: `mkdir()` with 0700 permissions creates a directory only the current user can access. An attacker cannot create symlinks inside a directory they cannot traverse.
- **Atomic creation**: `mkdir()` either succeeds or fails — there is no gap between existence check and use.
- **Single random name**: 128 bits of cryptographic randomness (`random_bytes(8)` = 16 hex chars) makes path prediction infeasible.
- **No stale files**: The `finally` block does `rm -rf` on the entire temp directory, eliminating orphaned files.
- **No external dependencies**: Uses only PHP built-in functions.

### Integrity Verification of Downloaded FFI Library

**Status:** Fixed in v0.4.12

**Issue:** [SEC-001 (#70)](https://github.com/crazy-goat/php-zvec/issues/70)

**Problem:** The `Installer::install()` method downloaded the FFI shared library without verifying its integrity. A compromised release artifact or man-in-the-middle attack could serve a malicious `.so`/`.dylib` file.

**Solution:** SHA-256 checksums are published alongside each release in `checksums.sha256`. Before extracting, `Installer::verifyChecksum()` computes `hash_file('sha256', ...)` and compares it against the expected hash using `hash_equals()` (timing-safe comparison).

### Null Pointer Safety in C++ FFI Bridge

**Status:** In Progress ([SEC-004 #73](https://github.com/crazy-goat/php-zvec/issues/73))

All C++ FFI functions accept opaque handle types. When these are null (e.g., after destroy or invalid state), null-pointer dereference causes segfaults. The fix adds null-pointer guards to all 50+ handle-accepting functions.

## Security Assumptions

1. **Local filesystem security**: The temp directory fix (SEC-002) assumes the attacker already has local user access. It prevents privilege escalation via symlink attacks.
2. **TLS is not explicitly pinned**: The download stream context sets `verify_peer` and `verify_peer_name` to true, relying on the system's CA bundle. No certificate pinning is implemented.
3. **API keys in memory**: Embedding function API keys are stored in PHP string properties. Starting from v0.4.12+, `__debugInfo()` masks the key in var_dump output and `__destruct()` calls `sodium_memzero()` when the sodium extension is available (see SEC-008).
