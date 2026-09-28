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
echo "Run tests: php run-tests.php -n tests/"
