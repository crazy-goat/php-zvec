#!/bin/bash
set -euo pipefail

ZVEC_VERSION="${ZVEC_VERSION:-v0.2.0}"
MARCH="${MARCH:-nehalem}"
JOBS="${JOBS:-$(nproc)}"
PLATFORM="${PLATFORM:-ubuntu24-x86_64}"

SRC_DIR="/src/zvec"
BUILD_DIR="/src/zvec/build-${MARCH}"
OUT_DIR="/build/out/${PLATFORM}"

if [ ! -d "$SRC_DIR/.git" ]; then
    echo "==> Cloning zvec ${ZVEC_VERSION}..."
    git clone --recurse-submodules --branch "$ZVEC_VERSION" \
        https://github.com/alibaba/zvec.git "$SRC_DIR"
else
    echo "==> Using cached zvec source in ${SRC_DIR}"
fi

echo "==> Building zvec with -march=${MARCH} (${JOBS} jobs)..."
mkdir -p "$BUILD_DIR"
cd "$BUILD_DIR"

cmake "$SRC_DIR" -DENABLE_$(echo "$MARCH" | tr '[:lower:]' '[:upper:]')=ON
make -j"$(( JOBS * 2 + 1 ))" V=1

echo "==> Packaging tarball..."
PKG=$(mktemp -d)
mkdir -p "$PKG/zvec/src"
mkdir -p "$PKG/zvec/thirdparty/sparsehash/sparsehash-2.0.4"
mkdir -p "$PKG/zvec/build/external/usr/local"

rsync -a "$SRC_DIR/src/include"                                "$PKG/zvec/src/"
rsync -a "$SRC_DIR/thirdparty/sparsehash/sparsehash-2.0.4/src" "$PKG/zvec/thirdparty/sparsehash/sparsehash-2.0.4/"
rsync -a "$BUILD_DIR/lib"                                       "$PKG/zvec/build/"
rsync -a "$BUILD_DIR/external/usr/local/lib"                    "$PKG/zvec/build/external/usr/local/"

mkdir -p "$OUT_DIR"
TARBALL="$OUT_DIR/zvec-${ZVEC_VERSION}-${PLATFORM}.tar.gz"
tar -czf "$TARBALL" -C "$PKG" zvec
rm -rf "$PKG"

echo "==> Done: $TARBALL ($(du -h "$TARBALL" | cut -f1))"
