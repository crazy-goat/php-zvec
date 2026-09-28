#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

ZVEC_VERSION="${1:-v0.7.0}"
PREBUILT_URL="${2:-}"

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

STAMP_FILE="zvec/build/.zvec_version"

# --- Platform mapping for the official zvec SDK prebuilt ---
# The SDK is published per OS+arch; these are the asset name fragments.
if [ "$OS" = "Darwin" ]; then
    SDK_PLATFORM="osx"
elif [ "$OS" = "Linux" ]; then
    SDK_PLATFORM="linux"
else
    SDK_PLATFORM=""
fi

ARCH="$(uname -m)"
case "$ARCH" in
    x86_64|amd64)  SDK_ARCH="amd64" ;;
    aarch64|arm64) SDK_ARCH="arm64" ;;
    *)            SDK_ARCH="" ;;
esac

# Official SDKs are published as .tar.gz on unix and .zip on windows.
if [ "$OS" = "Darwin" ] || [ "$OS" = "Linux" ]; then
    SDK_EXT="tar.gz"
else
    SDK_EXT="zip"
fi

SDK_URL="https://github.com/alibaba/zvec/releases/download/${ZVEC_VERSION}/zvec-sdk-${SDK_PLATFORM}-${SDK_ARCH}.${SDK_EXT}"

# --- Download the official zvec SDK prebuilt ---
# Upstream publishes prebuilt SDKs from v0.7.0 onwards. Using them skips
# compiling Arrow and RocksDB from source, which dominates build time.
# Set ZVEC_NO_SDK=1 to force a source build.
download_sdk() {
    local url="$1"
    [ -z "$SDK_PLATFORM" ] || [ -z "$SDK_ARCH" ] && return 1
    echo "Trying official zvec SDK ${ZVEC_VERSION}: ${url}"
    local tmp
    tmp="$(mktemp -d)"
    if ! curl -fL --connect-timeout 15 "$url" -o "${tmp}/zvec-sdk.${SDK_EXT}" 2>/dev/null; then
        rm -rf "$tmp"
        return 1
    fi
    rm -rf zvec
    mkdir -p zvec
    if [ "$SDK_EXT" = "zip" ]; then
        unzip -q "${tmp}/zvec-sdk.zip" -d zvec || { rm -rf "$tmp" zvec; return 1; }
    else
        tar -xzf "${tmp}/zvec-sdk.${SDK_EXT}" -C zvec || { rm -rf "$tmp" zvec; return 1; }
    fi
    rm -rf "$tmp"
    # The SDK unpacks to ./include ./lib ./data; ffi/CMakeLists.txt expects
    # them under zvec/src/include and zvec/build/lib.
    if [ -d zvec/include ]; then
        mkdir -p zvec/src
        mv zvec/include zvec/src/include
    fi
    if [ -d zvec/lib ]; then
        mkdir -p zvec/build
        mv zvec/lib zvec/build/lib
    fi
    # The FFI build also puts sparsehash headers on the include path.
    mkdir -p zvec/thirdparty/sparsehash/sparsehash-2.0.4
    return 0
}

if [ -z "${ZVEC_NO_SDK:-}" ]; then
    if download_sdk "$SDK_URL"; then
        mkdir -p zvec/build
        echo "$ZVEC_VERSION" > "$STAMP_FILE"
        echo "zvec ${ZVEC_VERSION} ready (official SDK prebuilt)"
        exit 0
    fi
    echo "Official SDK unavailable for ${ZVEC_VERSION} on ${SDK_PLATFORM}-${SDK_ARCH}, building from source..."
fi

# --- Legacy prebuilt (CI flow) ---
if [ -n "$PREBUILT_URL" ]; then
    echo "Trying prebuilt zvec ${ZVEC_VERSION} from ${PREBUILT_URL}..."
    if curl -fL --connect-timeout 10 "$PREBUILT_URL" -o /tmp/zvec.tar.gz 2>/dev/null; then
        echo "Prebuilt zvec ${ZVEC_VERSION} downloaded"
        rm -rf zvec
        tar -xzf /tmp/zvec.tar.gz
        rm /tmp/zvec.tar.gz
        mkdir -p zvec/build
        echo "$ZVEC_VERSION" > "$STAMP_FILE"
        echo "zvec ${ZVEC_VERSION} ready (prebuilt)"
        exit 0
    fi
    echo "Prebuilt not found, building from source..."
fi

# --- Check if already built for this version ---
if [ -f "$STAMP_FILE" ] && [ "$(cat "$STAMP_FILE")" = "$ZVEC_VERSION" ]; then
    if [ -f zvec/build/lib/libzvec.a ]; then
        echo "zvec ${ZVEC_VERSION} already built (stamp matches), skipping build"
        exit 0
    fi
    echo "Stamp exists but build artifacts missing, rebuilding..."
fi

# --- Clone zvec ---
if [ ! -d "zvec" ]; then
    echo "Cloning zvec ${ZVEC_VERSION}..."
    git clone --recurse-submodules --branch "$ZVEC_VERSION" https://github.com/alibaba/zvec.git zvec
else
    cd zvec
    CURRENT_TAG=$(git describe --tags --abbrev=0 2>/dev/null || echo "")
    if [ "$CURRENT_TAG" != "$ZVEC_VERSION" ]; then
        echo "zvec version mismatch (current: ${CURRENT_TAG:-unknown}, needed: ${ZVEC_VERSION}), updating..."
        git fetch --tags
        git checkout "$ZVEC_VERSION"
        git submodule update --init --recursive
    else
        git submodule update --init --recursive
    fi
    cd "$SCRIPT_DIR"
fi

# --- GCC 15 workaround (Linux) ---
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
echo "Building zvec ${ZVEC_VERSION}..."
ZVEC_CMAKE_FLAGS="-DCMAKE_BUILD_TYPE=Release -DCMAKE_POLICY_VERSION_MINIMUM=3.5 ${ZVEC_CXX_COMPILER:-}"
mkdir -p zvec/build
${CMAKE} -S zvec -B zvec/build $ZVEC_CMAKE_FLAGS
${CMAKE} --build zvec/build -j${CPUS}

echo "$ZVEC_VERSION" > "$STAMP_FILE"
echo "zvec ${ZVEC_VERSION} built in zvec/build"
