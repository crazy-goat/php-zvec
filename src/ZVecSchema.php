<?php

declare(strict_types=1);

namespace CrazyGoat\ZVec;

use FFI;

if (extension_loaded('zvec')) return;

/**
 * Fluent schema builder for collection field definitions.
 *
 * Create with `new ZVecSchema('collection_name')`, then chain add*()
 * methods to define fields. Minimum requirement: one vector field + one
 * string field (primary key). Supports scalar types, vector types (FP32,
 * FP64, INT8, FP16, INT4, INT16, BINARY32, BINARY64), sparse vectors,
 * array types, and binary fields. The underlying CData handle is freed
 * on destruction.
 *
 * @see ZVec::create()
 * @see ZVecDoc
 */
class ZVecSchema
{
    private FFI\CData $handle;

    /** @throws ZVecException */
    public function __construct(string $name)
    {
        if ($name === '') {
            throw new ZVecException('Schema name must not be empty');
        }
        $this->handle = self::ffi()->zvec_schema_create($name);
    }

    /** @throws ZVecException */
    public function __destruct()
    {
        self::ffi()->zvec_schema_free($this->handle);
    }

    private function __clone()
    {
    }

    public function getHandle(): FFI\CData
    {
        return $this->handle;
    }

    /** @throws ZVecException */
    public function setMaxDocCountPerSegment(int $count): self
    {
        self::ffi()->zvec_schema_set_max_doc_count_per_segment($this->handle, $count);
        return $this;
    }

    /** @throws ZVecException */
    public function addInt64(string $name, bool $nullable = false, bool $withInvertIndex = false): self
    {
        self::ffi()->zvec_schema_add_field_int64($this->handle, $name, $nullable ? 1 : 0, $withInvertIndex ? 1 : 0);
        return $this;
    }

    /** @throws ZVecException */
    public function addString(string $name, bool $nullable = false, bool $withInvertIndex = false): self
    {
        self::ffi()->zvec_schema_add_field_string($this->handle, $name, $nullable ? 1 : 0, $withInvertIndex ? 1 : 0);
        return $this;
    }

    /** @throws ZVecException */
    public function addFloat(string $name, bool $nullable = true): self
    {
        self::ffi()->zvec_schema_add_field_float($this->handle, $name, $nullable ? 1 : 0);
        return $this;
    }

    /** @throws ZVecException */
    public function addDouble(string $name, bool $nullable = true): self
    {
        self::ffi()->zvec_schema_add_field_double($this->handle, $name, $nullable ? 1 : 0);
        return $this;
    }

    /** @throws ZVecException */
    public function addBool(string $name, bool $nullable = false, bool $withInvertIndex = false): self
    {
        self::ffi()->zvec_schema_add_field_bool($this->handle, $name, $nullable ? 1 : 0, $withInvertIndex ? 1 : 0);
        return $this;
    }

    /** @throws ZVecException */
    public function addInt32(string $name, bool $nullable = false, bool $withInvertIndex = false): self
    {
        self::ffi()->zvec_schema_add_field_int32($this->handle, $name, $nullable ? 1 : 0, $withInvertIndex ? 1 : 0);
        return $this;
    }

    /** @throws ZVecException */
    public function addUint32(string $name, bool $nullable = false, bool $withInvertIndex = false): self
    {
        self::ffi()->zvec_schema_add_field_uint32($this->handle, $name, $nullable ? 1 : 0, $withInvertIndex ? 1 : 0);
        return $this;
    }

    /** @throws ZVecException */
    public function addUint64(string $name, bool $nullable = false, bool $withInvertIndex = false): self
    {
        self::ffi()->zvec_schema_add_field_uint64($this->handle, $name, $nullable ? 1 : 0, $withInvertIndex ? 1 : 0);
        return $this;
    }

    public const METRIC_L2 = 1;
    public const METRIC_IP = 2;
    public const METRIC_COSINE = 3;
    public const METRIC_MIPSL2 = 4;

    /** @throws ZVecException */
    public function addVectorFp32(string $name, int $dimension, int $metricType = self::METRIC_IP): self
    {
        if ($dimension <= 0) {
            throw new ZVecException("Dimension must be a positive integer, got: {$dimension}");
        }
        self::ffi()->zvec_schema_add_field_vector_fp32($this->handle, $name, $dimension, $metricType);
        return $this;
    }

    /** @throws ZVecException */
    public function addVectorFp64(string $name, int $dimension, int $metricType = self::METRIC_IP): self
    {
        if ($dimension <= 0) {
            throw new ZVecException("Dimension must be a positive integer, got: {$dimension}");
        }
        self::ffi()->zvec_schema_add_field_vector_fp64($this->handle, $name, $dimension, $metricType);
        return $this;
    }

    /** @throws ZVecException */
    public function addSparseVectorFp32(string $name, int $metricType = self::METRIC_IP): self
    {
        self::ffi()->zvec_schema_add_field_sparse_vector_fp32($this->handle, $name, $metricType);
        return $this;
    }

    /** @throws ZVecException */
    public function addVectorInt8(string $name, int $dimension, int $metricType = self::METRIC_IP): self
    {
        if ($dimension <= 0) {
            throw new ZVecException("Dimension must be a positive integer, got: {$dimension}");
        }
        self::ffi()->zvec_schema_add_field_vector_int8($this->handle, $name, $dimension, $metricType);
        return $this;
    }

    /** @throws ZVecException */
    public function addVectorFp16(string $name, int $dimension, int $metricType = self::METRIC_IP): self
    {
        if ($dimension <= 0) {
            throw new ZVecException("Dimension must be a positive integer, got: {$dimension}");
        }
        self::ffi()->zvec_schema_add_field_vector_fp16($this->handle, $name, $dimension, $metricType);
        return $this;
    }

    /** @throws ZVecException */
    public function addVectorInt4(string $name, int $dimension, int $metricType = self::METRIC_IP): self
    {
        if ($dimension <= 0) {
            throw new ZVecException("Dimension must be a positive integer, got: {$dimension}");
        }
        self::ffi()->zvec_schema_add_field_vector_int4($this->handle, $name, $dimension, $metricType);
        return $this;
    }

    /** @throws ZVecException */
    public function addVectorInt16(string $name, int $dimension, int $metricType = self::METRIC_IP): self
    {
        if ($dimension <= 0) {
            throw new ZVecException("Dimension must be a positive integer, got: {$dimension}");
        }
        self::ffi()->zvec_schema_add_field_vector_int16($this->handle, $name, $dimension, $metricType);
        return $this;
    }

    /** @throws ZVecException */
    public function addVectorBinary32(string $name, int $dimension, int $metricType = self::METRIC_IP): self
    {
        if ($dimension <= 0) {
            throw new ZVecException("Dimension must be a positive integer, got: {$dimension}");
        }
        self::ffi()->zvec_schema_add_field_vector_binary32($this->handle, $name, $dimension, $metricType);
        return $this;
    }

    /** @throws ZVecException */
    public function addVectorBinary64(string $name, int $dimension, int $metricType = self::METRIC_IP): self
    {
        if ($dimension <= 0) {
            throw new ZVecException("Dimension must be a positive integer, got: {$dimension}");
        }
        self::ffi()->zvec_schema_add_field_vector_binary64($this->handle, $name, $dimension, $metricType);
        return $this;
    }

    /** @throws ZVecException */
    public function addSparseVectorFp16(string $name, int $metricType = self::METRIC_IP): self
    {
        self::ffi()->zvec_schema_add_field_sparse_vector_fp16($this->handle, $name, $metricType);
        return $this;
    }

    /** @deprecated Use addBinary() instead */
    public function addFieldBinary(string $name, bool $nullable = true): self
    {
        trigger_error('addFieldBinary() is deprecated, use addBinary() instead', E_USER_DEPRECATED);
        return $this->addBinary($name, $nullable);
    }

    /** @throws ZVecException */
    public function addBinary(string $name, bool $nullable = true): self
    {
        self::ffi()->zvec_schema_add_field_binary($this->handle, $name, $nullable ? 1 : 0);
        return $this;
    }

    /** @deprecated Use addArrayString() instead */
    public function addFieldArrayString(string $name, bool $nullable = true): self
    {
        trigger_error('addFieldArrayString() is deprecated, use addArrayString() instead', E_USER_DEPRECATED);
        return $this->addArrayString($name, $nullable);
    }

    /** @throws ZVecException */
    public function addArrayString(string $name, bool $nullable = true): self
    {
        self::ffi()->zvec_schema_add_field_array_string($this->handle, $name, $nullable ? 1 : 0);
        return $this;
    }

    /** @deprecated Use addArrayBool() instead */
    public function addFieldArrayBool(string $name, bool $nullable = true): self
    {
        trigger_error('addFieldArrayBool() is deprecated, use addArrayBool() instead', E_USER_DEPRECATED);
        return $this->addArrayBool($name, $nullable);
    }

    /** @throws ZVecException */
    public function addArrayBool(string $name, bool $nullable = true): self
    {
        self::ffi()->zvec_schema_add_field_array_bool($this->handle, $name, $nullable ? 1 : 0);
        return $this;
    }

    /** @deprecated Use addArrayInt32() instead */
    public function addFieldArrayInt32(string $name, bool $nullable = true): self
    {
        trigger_error('addFieldArrayInt32() is deprecated, use addArrayInt32() instead', E_USER_DEPRECATED);
        return $this->addArrayInt32($name, $nullable);
    }

    /** @throws ZVecException */
    public function addArrayInt32(string $name, bool $nullable = true): self
    {
        self::ffi()->zvec_schema_add_field_array_int32($this->handle, $name, $nullable ? 1 : 0);
        return $this;
    }

    /** @deprecated Use addArrayInt64() instead */
    public function addFieldArrayInt64(string $name, bool $nullable = true): self
    {
        trigger_error('addFieldArrayInt64() is deprecated, use addArrayInt64() instead', E_USER_DEPRECATED);
        return $this->addArrayInt64($name, $nullable);
    }

    /** @throws ZVecException */
    public function addArrayInt64(string $name, bool $nullable = true): self
    {
        self::ffi()->zvec_schema_add_field_array_int64($this->handle, $name, $nullable ? 1 : 0);
        return $this;
    }

    /** @deprecated Use addArrayUint32() instead */
    public function addFieldArrayUint32(string $name, bool $nullable = true): self
    {
        trigger_error('addFieldArrayUint32() is deprecated, use addArrayUint32() instead', E_USER_DEPRECATED);
        return $this->addArrayUint32($name, $nullable);
    }

    /** @throws ZVecException */
    public function addArrayUint32(string $name, bool $nullable = true): self
    {
        self::ffi()->zvec_schema_add_field_array_uint32($this->handle, $name, $nullable ? 1 : 0);
        return $this;
    }

    /** @deprecated Use addArrayUint64() instead */
    public function addFieldArrayUint64(string $name, bool $nullable = true): self
    {
        trigger_error('addFieldArrayUint64() is deprecated, use addArrayUint64() instead', E_USER_DEPRECATED);
        return $this->addArrayUint64($name, $nullable);
    }

    /** @throws ZVecException */
    public function addArrayUint64(string $name, bool $nullable = true): self
    {
        self::ffi()->zvec_schema_add_field_array_uint64($this->handle, $name, $nullable ? 1 : 0);
        return $this;
    }

    /** @deprecated Use addArrayFloat() instead */
    public function addFieldArrayFloat(string $name, bool $nullable = true): self
    {
        trigger_error('addFieldArrayFloat() is deprecated, use addArrayFloat() instead', E_USER_DEPRECATED);
        return $this->addArrayFloat($name, $nullable);
    }

    /** @throws ZVecException */
    public function addArrayFloat(string $name, bool $nullable = true): self
    {
        self::ffi()->zvec_schema_add_field_array_float($this->handle, $name, $nullable ? 1 : 0);
        return $this;
    }

    /** @deprecated Use addArrayDouble() instead */
    public function addFieldArrayDouble(string $name, bool $nullable = true): self
    {
        trigger_error('addFieldArrayDouble() is deprecated, use addArrayDouble() instead', E_USER_DEPRECATED);
        return $this->addArrayDouble($name, $nullable);
    }

    /** @throws ZVecException */
    public function addArrayDouble(string $name, bool $nullable = true): self
    {
        self::ffi()->zvec_schema_add_field_array_double($this->handle, $name, $nullable ? 1 : 0);
        return $this;
    }

    private static function ffi(): FFI
    {
        return ZVec::ffi();
    }
}
