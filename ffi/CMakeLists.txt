cmake_minimum_required(VERSION 3.14)
project(zvec_ffi CXX)

set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)

# Official prebuilt zvec SDK, fetched by ../fetch_zvec_sdk.sh.
set(ZVEC_SDK_DIR "${CMAKE_CURRENT_SOURCE_DIR}/../sdk" CACHE PATH "zvec SDK root")
set(ZVEC_SDK_LIB "${ZVEC_SDK_DIR}/lib/${CMAKE_SHARED_LIBRARY_PREFIX}zvec${CMAKE_SHARED_LIBRARY_SUFFIX}")

if(NOT EXISTS "${ZVEC_SDK_LIB}")
    message(FATAL_ERROR "zvec SDK not found at ${ZVEC_SDK_DIR}. Run ./fetch_zvec_sdk.sh first.")
endif()

add_library(zvec_ffi SHARED zvec_ffi.cc)

target_include_directories(zvec_ffi PRIVATE "${ZVEC_SDK_DIR}/include")
target_link_libraries(zvec_ffi PRIVATE "${ZVEC_SDK_LIB}" ${CMAKE_DL_LIBS})

# Release builds on Linux: embed libstdc++/libgcc like the SDK itself does, so the
# adapter runs on hosts without libstdc++ (php:*-alpine) or with an older one.
option(ZVEC_FFI_STATIC_LIBSTDCXX "Link libstdc++ and libgcc statically" OFF)
if(ZVEC_FFI_STATIC_LIBSTDCXX)
    target_link_options(zvec_ffi PRIVATE -static-libstdc++ -static-libgcc)
endif()

# libzvec is always shipped next to libzvec_ffi (lib/ for Composer installs,
# ffi/build/ for local builds), so resolve it relative to the adapter itself.
if(APPLE)
    set_target_properties(zvec_ffi PROPERTIES INSTALL_RPATH "@loader_path")
else()
    set_target_properties(zvec_ffi PROPERTIES INSTALL_RPATH "$ORIGIN")
endif()
set_target_properties(zvec_ffi PROPERTIES BUILD_WITH_INSTALL_RPATH ON)

add_custom_command(TARGET zvec_ffi POST_BUILD
    COMMAND ${CMAKE_COMMAND} -E copy_if_different "${ZVEC_SDK_LIB}" "$<TARGET_FILE_DIR:zvec_ffi>"
    COMMAND ${CMAKE_COMMAND} -E copy_directory "${ZVEC_SDK_DIR}/data" "$<TARGET_FILE_DIR:zvec_ffi>/zvec_data"
)
