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

# --- CMake ---
if [ "$OS" = "Darwin" ]; then
    CMAKE_VERSION="3.28.3"
    CMAKE_DIR="cmake-${CMAKE_VERSION}-macos-universal"
    CMAKE_TAR="${CMAKE_DIR}.tar.gz"

    if [ ! -d "${CMAKE_DIR}" ]; then
        echo "Downloading CMake ${CMAKE_VERSION}..."
        curl -L -O "https://github.com/Kitware/CMake/releases/download/v${CMAKE_VERSION}/${CMAKE_TAR}"
        tar -xzf "${CMAKE_TAR}"
        rm "${CMAKE_TAR}"
    fi

    CMAKE="${SCRIPT_DIR}/${CMAKE_DIR}/CMake.app/Contents/bin/cmake"
else
    CMAKE="cmake"
fi

echo "Using CMake: $(${CMAKE} --version | head -1)"
echo "CPUs: ${CPUS}"

# --- Clone zvec ---
ZVEC_VERSION="v0.4.0"
if [ ! -d "zvec" ]; then
    echo "Cloning zvec ${ZVEC_VERSION}..."
    git clone --recurse-submodules --branch "$ZVEC_VERSION" https://github.com/alibaba/zvec.git zvec
else
    cd zvec
    git submodule update --init --recursive
    cd "$SCRIPT_DIR"
fi

# --- GCC 15 workaround (Linux) ---
# GCC 15 moves uint64_t from global to std:: namespace.
# RocksDB 8.1.1 headers use bare uint64_t, so pre-include <stdint.h>.
if [ "$OS" = "Linux" ]; then
    GXX_VER=$(${CXX:-c++} -dumpversion 2>/dev/null | cut -d. -f1)
    if [ "$GXX_VER" -ge 15 ] 2>/dev/null; then
        echo "Detected GCC ${GXX_VER}, creating g++ wrapper for -include stdint.h"
        GXX_WRAPPER="/tmp/zvec-g++-wrapper.sh"
        cat > "$GXX_WRAPPER" << 'WRAPPER_EOF'
#!/bin/bash
exec /usr/bin/c++ -include stdint.h "$@"
WRAPPER_EOF
        chmod +x "$GXX_WRAPPER"
        ZVEC_CXX_COMPILER="-DCMAKE_CXX_COMPILER=$GXX_WRAPPER"
    fi
fi

# --- Build zvec ---
echo "Building zvec..."
ZVEC_CMAKE_FLAGS="-DCMAKE_BUILD_TYPE=Release -DCMAKE_POLICY_VERSION_MINIMUM=3.5 ${ZVEC_CXX_COMPILER:-}"
mkdir -p zvec/build
${CMAKE} -S zvec -B zvec/build $ZVEC_CMAKE_FLAGS
${CMAKE} --build zvec/build -j${CPUS}

echo "zvec built in zvec/build"

# --- Build FFI wrapper ---
echo "Building FFI wrapper..."
FFI_CMAKE_FLAGS="-DCMAKE_BUILD_TYPE=Release ${ZVEC_CXX_COMPILER:-}"
mkdir -p ffi/build
${CMAKE} -S ffi -B ffi/build $FFI_CMAKE_FLAGS
${CMAKE} --build ffi/build -j${CPUS}

# --- Done ---
if [ "$OS" = "Darwin" ]; then
    LIB_EXT="dylib"
else
    LIB_EXT="so"
fi
echo ""
echo "Done! FFI library: ffi/build/libzvec_ffi.${LIB_EXT}"
echo "Run tests: php run-tests.php tests/"
