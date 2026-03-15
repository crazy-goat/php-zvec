PHP_ARG_ENABLE([zvec],
  [whether to enable zvec support],
  [AS_HELP_STRING([--enable-zvec],
    [Enable zvec support])],
  [no])

if test "$PHP_ZVEC" != "no"; then
  PHP_REQUIRE_CXX()

  ZVEC_ROOT="$abs_srcdir/../zvec"
  ZVEC_BUILD="$ZVEC_ROOT/build"
  ZVEC_INCLUDE="$ZVEC_ROOT/src/include"
  ZVEC_SPARSEHASH="$ZVEC_ROOT/thirdparty/sparsehash/sparsehash-2.0.4/src"
  ZVEC_LIB="$ZVEC_BUILD/lib"
  ZVEC_EXTERNAL_LIB="$ZVEC_BUILD/external/usr/local/lib"

  PHP_ADD_INCLUDE($ZVEC_INCLUDE)
  PHP_ADD_INCLUDE($ZVEC_SPARSEHASH)

  ZVEC_SOURCES="zvec.cc \
    zvec_exception.cc \
    zvec_schema.cc \
    zvec_doc.cc \
    zvec_vector_query.cc \
    zvec_collection.cc \
    zvec_reranker.cc \
    zvec_reranked_doc.cc \
    zvec_rrf_reranker.cc \
    zvec_weighted_reranker.cc \
    zvec_embedding_interfaces.cc \
    zvec_openai_embedding.cc \
    zvec_qwen_embedding.cc"

  PHP_NEW_EXTENSION(zvec, $ZVEC_SOURCES, $ext_shared,, -std=c++17 -DCOMPILE_DL_ZVEC)

  PHP_ADD_LIBRARY(stdc++, 1, ZVEC_SHARED_LIBADD)
  PHP_ADD_LIBRARY(z, 1, ZVEC_SHARED_LIBADD)
  PHP_ADD_LIBRARY(pthread, 1, ZVEC_SHARED_LIBADD)
  PHP_ADD_LIBRARY(dl, 1, ZVEC_SHARED_LIBADD)

  dnl External libs linked normally (no static registrations)
  dnl macOS keeps explicit list (order matters), Linux uses wildcard to avoid missing deps.
  case $host_os in
    darwin*)
      ZVEC_EXTERNAL_LIBS=" \
        $ZVEC_EXTERNAL_LIB/librocksdb.a \
        $ZVEC_EXTERNAL_LIB/libarrow.a \
        $ZVEC_EXTERNAL_LIB/libarrow_acero.a \
        $ZVEC_EXTERNAL_LIB/libarrow_compute.a \
        $ZVEC_EXTERNAL_LIB/libarrow_dataset.a \
        $ZVEC_EXTERNAL_LIB/libarrow_bundled_dependencies.a \
        $ZVEC_EXTERNAL_LIB/libparquet.a \
        $ZVEC_EXTERNAL_LIB/libprotobuf.a \
        $ZVEC_EXTERNAL_LIB/libantlr4-runtime.a \
        $ZVEC_EXTERNAL_LIB/libglog.a \
        $ZVEC_EXTERNAL_LIB/libgflags_nothreads.a \
        $ZVEC_EXTERNAL_LIB/libyaml-cpp.a \
        $ZVEC_EXTERNAL_LIB/liblz4.a \
        $ZVEC_EXTERNAL_LIB/libroaring.a"
      ;;
    *)
      ZVEC_EXTERNAL_LIBS=""
      ;;
  esac

  ZVEC_CORE_LIBS=" \
    $ZVEC_LIB/libzvec_db.a \
    $ZVEC_LIB/libzvec_ailego.a \
    $ZVEC_LIB/libcore_metric.a \
    $ZVEC_LIB/libcore_knn_hnsw.a \
    $ZVEC_LIB/libcore_knn_hnsw_sparse.a \
    $ZVEC_LIB/libcore_knn_flat.a \
    $ZVEC_LIB/libcore_knn_flat_sparse.a \
    $ZVEC_LIB/libcore_knn_ivf.a \
    $ZVEC_LIB/libcore_knn_cluster.a \
    $ZVEC_LIB/libcore_quantizer.a \
    $ZVEC_LIB/libcore_utility.a \
    $ZVEC_LIB/libcore_mix_reducer.a \
    $ZVEC_LIB/libcore_framework.a \
    $ZVEC_LIB/libcore_interface.a"

  case $host_os in
    darwin*)
      dnl macOS: use -force_load for static factory registration, frameworks for TLS
      ZVEC_FORCE_LOAD_LIBS=""
      for lib in $ZVEC_CORE_LIBS; do
        ZVEC_FORCE_LOAD_LIBS="$ZVEC_FORCE_LOAD_LIBS -Wl,-force_load,$lib"
      done
      ZVEC_PLATFORM_LIBS="-framework CoreFoundation -framework Security"
      ZVEC_STRIP_FLAGS="-Wl,-dead_strip -Wl,-x -Wl,-exported_symbols_list,$ext_srcdir/zvec.exported_symbols"
      ;;
    *)
      dnl Linux: libtool strips --whole-archive, so we pass static libs
      dnl directly via EXTRA_LDFLAGS to bypass libtool processing
      ZVEC_ALL_STATIC="$ZVEC_CORE_LIBS"
      for lib in $ZVEC_EXTERNAL_LIB/*.a; do
        ZVEC_ALL_STATIC="$ZVEC_ALL_STATIC $lib"
      done
      EXTRA_LDFLAGS="$EXTRA_LDFLAGS -Wl,--whole-archive $ZVEC_ALL_STATIC -Wl,--no-whole-archive -lssl -lcrypto"
      ZVEC_PLATFORM_LIBS=""
      ZVEC_STRIP_FLAGS=""
      ;;
  esac

  case $host_os in
    darwin*)
      ZVEC_SHARED_LIBADD="$ZVEC_FORCE_LOAD_LIBS $ZVEC_EXTERNAL_LIBS $ZVEC_PLATFORM_LIBS $ZVEC_STRIP_FLAGS $ZVEC_SHARED_LIBADD"
      ;;
  esac
  PHP_SUBST(ZVEC_SHARED_LIBADD)
  PHP_SUBST(EXTRA_LDFLAGS)
fi
