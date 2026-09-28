cmake_minimum_required(VERSION 3.14)
project(zvec_ffi CXX)

set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)

set(ZVEC_ROOT "${CMAKE_CURRENT_SOURCE_DIR}/../zvec")
set(ZVEC_BUILD "${ZVEC_ROOT}/build")
set(ZVEC_INCLUDE "${ZVEC_ROOT}/src/include")
set(ZVEC_EXTERNAL_LIB "${ZVEC_BUILD}/external/usr/local/lib")
set(ZVEC_LIB "${ZVEC_BUILD}/lib")

# Two layouts are supported, and which one is in use is detected rather than
# passed in, so a stale CMake cache cannot silently pick the wrong path:
#
#   source build — libzvec.a + libzvec_core.a + the RocksDB/Arrow static
#                  archives under build/external/usr/local/lib.
#   official SDK — the shared libraries published by alibaba/zvec from v0.7.0
#                  onwards. build_zvec_lib.sh unpacks them into build/lib.
#
# Only the source build needs sparsehash on the include path; the SDK ships
# self-contained headers.
set(ZVEC_USE_SDK OFF)
if(APPLE)
    if(EXISTS "${ZVEC_LIB}/libzvec.dylib" OR EXISTS "${ZVEC_LIB}/libzvec.so")
        set(ZVEC_USE_SDK ON)
    endif()
elseif(EXISTS "${ZVEC_LIB}/libzvec.so")
    set(ZVEC_USE_SDK ON)
endif()

add_library(zvec_ffi SHARED zvec_ffi.cc)

target_include_directories(zvec_ffi PRIVATE ${ZVEC_INCLUDE})
if(NOT ZVEC_USE_SDK)
    target_include_directories(zvec_ffi PRIVATE
        ${ZVEC_ROOT}/thirdparty/sparsehash/sparsehash-2.0.4/src
    )
endif()

if(ZVEC_USE_SDK)
    message(STATUS "zvec_ffi: linking against the official zvec SDK (shared)")
    # libzvec.so is self-contained: Arrow, RocksDB and Protobuf are already
    # inside it. libzvec_core.so must NOT be linked as well -- it is not even a
    # DT_NEEDED of libzvec.so, so naming it would load a second copy of the
    # same static registries (arrow::compute::FunctionDoc among them) and their
    # destructors then run twice at exit, which corrupts the heap.
    target_link_libraries(zvec_ffi PRIVATE
        ${ZVEC_LIB}/libzvec.so
    )
    set_target_properties(zvec_ffi PROPERTIES
        BUILD_RPATH "$ORIGIN"
        INSTALL_RPATH "$ORIGIN"
    )
else()
    message(STATUS "zvec_ffi: linking against the zvec source build (static)")

    set(ZVEC_STATIC_LIBS
        ${ZVEC_LIB}/libzvec.a
        ${ZVEC_LIB}/libzvec_ailego.a
        ${ZVEC_LIB}/libzvec_core.a
        ${ZVEC_LIB}/libzvec_turbo.a
        # Upstream ships the DiskANN algorithm as a separate core_knn_* library
        # (see src/core/algorithm/diskann/CMakeLists.txt) and deliberately excludes
        # its sources from libzvec_core to avoid duplicate symbols. Its
        # INDEX_FACTORY_REGISTER_* statics therefore never make it into
        # libzvec_core.a, so index creation fails at runtime with
        # "DiskAnn factory entries are not registered" unless we link it here.
        #
        # core_framework and core_knn_cluster are NOT listed: their object files are
        # already pulled in by libzvec_core.a, and naming them here would duplicate
        # every shared symbol (multiple definition of IndexFactory::*, IndexCluster::*).
        # The static .a resolves against the whole-archive of libzvec_core.a, so the
        # diskann objects that need them link fine.
        ${ZVEC_LIB}/libcore_knn_diskann.a
    )

    set(ZVEC_EXTERNAL_LIBS
        ${ZVEC_LIB}/libFastPFOR.a
        ${ZVEC_LIB}/libsnowball.a
        ${ZVEC_EXTERNAL_LIB}/librocksdb.a
        ${ZVEC_EXTERNAL_LIB}/libarrow.a
        ${ZVEC_EXTERNAL_LIB}/libarrow_acero.a
        ${ZVEC_EXTERNAL_LIB}/libarrow_compute.a
        ${ZVEC_EXTERNAL_LIB}/libarrow_dataset.a
        ${ZVEC_EXTERNAL_LIB}/libarrow_bundled_dependencies.a
        ${ZVEC_EXTERNAL_LIB}/libparquet.a
        ${ZVEC_EXTERNAL_LIB}/libprotobuf.a
        ${ZVEC_EXTERNAL_LIB}/libprotoc.a
        ${ZVEC_EXTERNAL_LIB}/libantlr4-runtime.a
        ${ZVEC_EXTERNAL_LIB}/libglog.a
        ${ZVEC_EXTERNAL_LIB}/libgflags_nothreads.a
        ${ZVEC_EXTERNAL_LIB}/libyaml-cpp.a
        ${ZVEC_EXTERNAL_LIB}/liblz4.a
        ${ZVEC_EXTERNAL_LIB}/libroaring.a
    )

    if(APPLE)
        foreach(lib ${ZVEC_STATIC_LIBS})
            target_link_options(zvec_ffi PRIVATE -Wl,-force_load,${lib})
        endforeach()
        target_link_libraries(zvec_ffi PRIVATE ${ZVEC_EXTERNAL_LIBS})
    else()
        target_link_options(zvec_ffi PRIVATE
            -Wl,--whole-archive ${ZVEC_STATIC_LIBS} ${ZVEC_EXTERNAL_LIBS} -Wl,--no-whole-archive
        )
    endif()

    target_link_libraries(zvec_ffi PRIVATE
        z
        pthread
        dl
    )
endif()

if(APPLE)
    target_link_libraries(zvec_ffi PRIVATE
        "-framework CoreFoundation"
        "-framework Security"
    )
elseif(NOT ZVEC_USE_SDK)
    # Only the source build links OpenSSL: the SDK already has it statically
    # baked into libzvec.so, so it appears in no NEEDED entry there.
    target_link_libraries(zvec_ffi PRIVATE
        ssl
        crypto
    )
endif()
