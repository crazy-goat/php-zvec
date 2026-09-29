#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

ZVEC_VERSION="${1:-v0.7.0}"

echo "=== Step 1/2: Fetch zvec SDK ${ZVEC_VERSION} ==="
./fetch_zvec_sdk.sh "$ZVEC_VERSION"

echo ""
echo "=== Step 2/2: Build FFI wrapper ==="
./build_ffi.sh

echo ""
echo "=== Done ==="
# -n drops php.ini, which also disables the legacy zvec extension but takes FFI
# with it when FFI comes from a conf.d ini instead of being compiled in.
echo "Run tests: php run-tests.php -n -d extension=ffi.so tests/"
