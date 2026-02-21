#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

# Pobierz repozytorium zvec
if [ ! -d "zvec" ]; then
    git clone --recurse-submodules https://github.com/alibaba/zvec.git zvec
else
    cd zvec
    git submodule update --init --recursive
    cd "$SCRIPT_DIR"
fi

# Pobierz i zainstaluj CMake 3.28 lokalnie
CMAKE_VERSION="3.28.3"
CMAKE_DIR="cmake-${CMAKE_VERSION}-macos-universal"
CMAKE_TAR="${CMAKE_DIR}.tar.gz"

if [ ! -d "${CMAKE_DIR}" ]; then
    echo "Pobieranie CMake ${CMAKE_VERSION}..."
    curl -L -O "https://github.com/Kitware/CMake/releases/download/v${CMAKE_VERSION}/${CMAKE_TAR}"
    tar -xzf "${CMAKE_TAR}"
    rm "${CMAKE_TAR}"
fi

export CMAKE="${SCRIPT_DIR}/${CMAKE_DIR}/CMake.app/Contents/bin/cmake"
export PATH="${SCRIPT_DIR}/${CMAKE_DIR}/CMake.app/Contents/bin:$PATH"

echo "Używam CMake: $(which cmake)"
cmake --version

# Buduj zvec
cd zvec
mkdir -p build
cd build
cmake ..
make -j$(sysctl -n hw.ncpu)

echo "zvec zbudowany w katalogu zvec/build"

# Buduj FFI wrapper
cd "$SCRIPT_DIR"
echo "Buduję FFI wrapper..."
cd ffi
mkdir -p build
cd build
cmake ..
make -j$(sysctl -n hw.ncpu)

echo "Gotowe! Biblioteka FFI: ffi/build/libzvec_ffi.dylib"
echo "Uruchom przykład: php php/example.php"
