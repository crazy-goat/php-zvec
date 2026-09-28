PHP_ARG_ENABLE([zvec],
  [whether to enable zvec support],
  [AS_HELP_STRING([--enable-zvec],
    [Enable zvec support])],
  [no])

if test "$PHP_ZVEC" != "no"; then
  PHP_REQUIRE_CXX()

  dnl Links against the official prebuilt zvec SDK (./fetch_zvec_sdk.sh).
  dnl build_ext.sh copies libzvec next to modules/zvec.so; the rpath finds it there.
  ZVEC_SDK_ROOT="$abs_srcdir/../sdk"
  ZVEC_SDK_INCLUDE="$ZVEC_SDK_ROOT/include"
  ZVEC_SDK_LIB="$ZVEC_SDK_ROOT/lib"

  if test ! -d "$ZVEC_SDK_INCLUDE"; then
    AC_MSG_ERROR([zvec SDK not found at $ZVEC_SDK_INCLUDE. Run ./fetch_zvec_sdk.sh first.])
  fi

  PHP_ADD_INCLUDE($ZVEC_SDK_INCLUDE)

  ZVEC_SOURCES="zvec.cc \
    zvec_exception.cc \
    zvec_collection_options.cc \
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
  PHP_ADD_LIBRARY(dl, 1, ZVEC_SHARED_LIBADD)

  dnl Not PHP_ADD_LIBPATH: it would bake the absolute sdk/lib path into the rpath.
  ZVEC_SHARED_LIBADD="-L$ZVEC_SDK_LIB $ZVEC_SHARED_LIBADD"
  PHP_ADD_LIBRARY(zvec, 1, ZVEC_SHARED_LIBADD)

  case $host_os in
    darwin*)
      ZVEC_RPATH_FLAGS="-Wl,-rpath,@loader_path"
      ;;
    *)
      dnl Quoted so make ($$ -> $) and the shell/libtool pass $ORIGIN through literally.
      ZVEC_RPATH_FLAGS="-Wl,-rpath,'\$\$ORIGIN'"
      ;;
  esac
  EXTRA_LDFLAGS="$EXTRA_LDFLAGS $ZVEC_RPATH_FLAGS"

  PHP_SUBST(ZVEC_SHARED_LIBADD)
  PHP_SUBST(EXTRA_LDFLAGS)
fi
