#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

echo "=== Building zvec PHP extension ==="

SDK_LIB_SO="../sdk/lib/libzvec.so"
SDK_LIB_DYLIB="../sdk/lib/libzvec.dylib"

if [ ! -f "$SDK_LIB_SO" ] && [ ! -f "$SDK_LIB_DYLIB" ]; then
    echo "ERROR: zvec SDK not found. Run ./fetch_zvec_sdk.sh first."
    exit 1
fi

if [ -f Makefile ]; then
    echo "--- Cleaning previous build ---"
    make clean 2>/dev/null || true
    phpize --clean 2>/dev/null || true
fi

echo "--- Running phpize ---"
phpize

echo "--- Configuring ---"
./configure --enable-zvec

echo "--- Building ---"
make -j$(sysctl -n hw.ncpu 2>/dev/null || nproc 2>/dev/null || echo 4)

echo "--- Copying zvec shared library next to modules/zvec.so ---"
if [ -f "$SDK_LIB_SO" ]; then
    cp -f "$SDK_LIB_SO" modules/
elif [ -f "$SDK_LIB_DYLIB" ]; then
    cp -f "$SDK_LIB_DYLIB" modules/
fi

echo "--- Stripping symbols ---"
SIZE_BEFORE=$(stat -f%z modules/zvec.so 2>/dev/null || stat -c%s modules/zvec.so 2>/dev/null)
strip -x modules/zvec.so
SIZE_AFTER=$(stat -f%z modules/zvec.so 2>/dev/null || stat -c%s modules/zvec.so 2>/dev/null)
echo "Size: $((SIZE_BEFORE / 1024))KB -> $((SIZE_AFTER / 1024))KB"

echo ""
echo "=== Build complete ==="
echo "Extension: $(pwd)/modules/zvec.so"
echo "zvec shared library copied to: $(pwd)/modules/"
echo ""
echo "Test with:"
echo "  php -n -d extension=modules/zvec.so -r 'echo \"zvec loaded: \" . (extension_loaded(\"zvec\") ? \"yes\" : \"no\") . \"\\n\";'"
