cmake_minimum_required(VERSION 3.14)
project(zvec_ffi CXX)

set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)

set(ZVEC_ROOT "${CMAKE_CURRENT_SOURCE_DIR}/../zvec")
set(ZVEC_BUILD "${ZVEC_ROOT}/build")
set(ZVEC_INCLUDE "${ZVEC_ROOT}/src/include")
set(ZVEC_EXTERNAL_LIB "${ZVEC_BUILD}/external/usr/local/lib")
set(ZVEC_LIB "${ZVEC_BUILD}/lib")

add_library(zvec_ffi SHARED zvec_ffi.cc)

target_include_directories(zvec_ffi PRIVATE
    ${ZVEC_INCLUDE}
    ${ZVEC_ROOT}/thirdparty/sparsehash/sparsehash-2.0.4/src
)

set(ZVEC_STATIC_LIBS
    ${ZVEC_LIB}/libzvec_db.a
    ${ZVEC_LIB}/libzvec_ailego.a
    ${ZVEC_LIB}/libcore_metric.a
    ${ZVEC_LIB}/libcore_knn_hnsw.a
    ${ZVEC_LIB}/libcore_knn_hnsw_sparse.a
    ${ZVEC_LIB}/libcore_knn_flat.a
    ${ZVEC_LIB}/libcore_knn_flat_sparse.a
    ${ZVEC_LIB}/libcore_knn_ivf.a
    ${ZVEC_LIB}/libcore_knn_cluster.a
    ${ZVEC_LIB}/libcore_quantizer.a
    ${ZVEC_LIB}/libcore_utility.a
    ${ZVEC_LIB}/libcore_mix_reducer.a
    ${ZVEC_LIB}/libcore_framework.a
    ${ZVEC_LIB}/libcore_interface.a
)

set(ZVEC_EXTERNAL_LIBS
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

if(APPLE)
    target_link_libraries(zvec_ffi PRIVATE
        "-framework CoreFoundation"
        "-framework Security"
    )
else()
    target_link_libraries(zvec_ffi PRIVATE
        ssl
        crypto
    )
endif()
