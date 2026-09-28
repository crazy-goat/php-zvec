#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

OS="$(uname -s)"
if [ "$OS" = "Darwin" ]; then
    CPUS=$(sysctl -n hw.ncpu)
    LIB_EXT="dylib"
else
    CPUS=$(nproc)
    LIB_EXT="so"
fi

if [ ! -f "sdk/lib/libzvec.${LIB_EXT}" ]; then
    echo "Error: zvec SDK not found. Run ./fetch_zvec_sdk.sh first."
    exit 1
fi

echo "Building FFI wrapper..."
# Extra arguments are passed to CMake, e.g. -DZVEC_FFI_STATIC_LIBSTDCXX=ON
cmake -S ffi -B ffi/build -DCMAKE_BUILD_TYPE=Release "$@"
cmake --build ffi/build -j"${CPUS}"

echo "FFI library built: ffi/build/libzvec_ffi.${LIB_EXT}"
