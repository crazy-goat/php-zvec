#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

OS="$(uname -s)"

# --- CPU count ---
if [ "$OS" = "Darwin" ]; then
    CPUS=$(sysctl -n hw.ncpu)
elif [ "$OS" = "Linux" ]; then
    CPUS=$(nproc)
else
    echo "Unsupported OS: $OS"
    exit 1
fi

# --- Check zvec is built ---
if [ ! -f zvec/build/lib/libzvec_db.a ]; then
    echo "Error: zvec library not built. Run ./build_zvec_lib.sh first."
    exit 1
fi

# --- CMake ---
if [ "$OS" = "Darwin" ]; then
    CMAKE_VERSION="3.28.3"
    CMAKE_DIR="cmake-${CMAKE_VERSION}-macos-universal"
    CMAKE="${SCRIPT_DIR}/${CMAKE_DIR}/CMake.app/Contents/bin/cmake"
else
    CMAKE="cmake"
fi

# --- GCC 15 workaround (Linux) ---
if [ "$OS" = "Linux" ]; then
    GXX_VER=$(${CXX:-c++} -dumpversion 2>/dev/null | cut -d. -f1)
    if [ "$GXX_VER" -ge 15 ] 2>/dev/null; then
        GXX_WRAPPER="/tmp/zvec-g++-wrapper.sh"
        cat > "$GXX_WRAPPER" << 'WRAPPER_EOF'
#!/bin/bash
exec /usr/bin/c++ -include stdint.h "$@"
WRAPPER_EOF
        chmod +x "$GXX_WRAPPER"
        ZVEC_CXX_COMPILER="-DCMAKE_CXX_COMPILER=$GXX_WRAPPER"
    fi
fi

echo "Building FFI wrapper..."
FFI_CMAKE_FLAGS="-DCMAKE_BUILD_TYPE=Release ${ZVEC_CXX_COMPILER:-}"
mkdir -p ffi/build
${CMAKE} -S ffi -B ffi/build $FFI_CMAKE_FLAGS
${CMAKE} --build ffi/build -j${CPUS}

if [ "$OS" = "Darwin" ]; then
    LIB_EXT="dylib"
else
    LIB_EXT="so"
fi
echo "FFI library built: ffi/build/libzvec_ffi.${LIB_EXT}"
