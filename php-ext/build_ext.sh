#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

echo "=== Building zvec PHP extension ==="

if [ ! -f "../zvec/build/lib/libzvec_db.a" ]; then
    echo "ERROR: zvec C++ library not built. Run ./build_zvec.sh first."
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
make -j$(sysctl -n hw.ncpu 2>/dev/null || echo 4)

echo ""
echo "=== Build complete ==="
echo "Extension: $(pwd)/modules/zvec.so"
echo ""
echo "Test with:"
echo "  php -n -d extension=modules/zvec.so -r 'echo \"zvec loaded: \" . (extension_loaded(\"zvec\") ? \"yes\" : \"no\") . \"\\n\";'"
