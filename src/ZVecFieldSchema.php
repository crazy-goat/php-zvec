<?php

declare(strict_types=1);

namespace CrazyGoat\ZVec;

use FFI;

if (extension_loaded('zvec')) return;

/**
 * Field schema introspection result.
 *
 * Returned by getFieldSchema(). Provides data type, dimension,
 * vector-specific metadata (dense vs sparse), index type, and
 * nullability for any collection field. The underlying CData
 * handle is freed on destruction.
 *
 * @see ZVec::getFieldSchema()
 */
class ZVecFieldSchema
{
    private FFI\CData $handle;

    public function __construct(FFI\CData $handle)
    {
        $this->handle = $handle;
    }

    public function __destruct()
    {
        self::ffi()->zvec_field_schema_free($this->handle);
    }

    private function __clone()
    {
    }

    /** @throws ZVecException */
    public function getName(): string
    {
        $ptr = self::ffi()->zvec_field_schema_get_name($this->handle);
        return is_string($ptr) ? $ptr : FFI::string($ptr);
    }

    /** @throws ZVecException */
    public function getDataType(): int
    {
        return self::ffi()->zvec_field_schema_get_data_type($this->handle);
    }

    /** @throws ZVecException */
    public function getElementDataType(): int
    {
        return self::ffi()->zvec_field_schema_get_element_data_type($this->handle);
    }

    /** @throws ZVecException */
    public function getElementDataSize(): int
    {
        return self::ffi()->zvec_field_schema_get_element_data_size($this->handle);
    }

    /** @throws ZVecException */
    public function getDimension(): int
    {
        return self::ffi()->zvec_field_schema_get_dimension($this->handle);
    }

    /** @throws ZVecException */
    public function isVectorField(): bool
    {
        return self::ffi()->zvec_field_schema_is_vector_field($this->handle) !== 0;
    }

    /** @throws ZVecException */
    public function isDenseVector(): bool
    {
        return self::ffi()->zvec_field_schema_is_dense_vector($this->handle) !== 0;
    }

    /** @throws ZVecException */
    public function isSparseVector(): bool
    {
        return self::ffi()->zvec_field_schema_is_sparse_vector($this->handle) !== 0;
    }

    /** @throws ZVecException */
    public function isArrayType(): bool
    {
        return self::ffi()->zvec_field_schema_is_array_type($this->handle) !== 0;
    }

    /** @throws ZVecException */
    public function isNullable(): bool
    {
        return self::ffi()->zvec_field_schema_is_nullable($this->handle) !== 0;
    }

    /** @throws ZVecException */
    public function hasInvertIndex(): bool
    {
        return self::ffi()->zvec_field_schema_has_invert_index($this->handle) !== 0;
    }

    /** @throws ZVecException */
    public function hasIndex(): bool
    {
        return self::ffi()->zvec_field_schema_has_index($this->handle) !== 0;
    }

    /** @throws ZVecException */
    public function getIndexType(): int
    {
        return self::ffi()->zvec_field_schema_get_index_type($this->handle);
    }

    private static function ffi(): FFI
    {
        return ZVec::ffi();
    }
}
