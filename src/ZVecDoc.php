<?php

declare(strict_types=1);

if (extension_loaded('zvec')) return;

class ZVecDoc
{
    private FFI\CData $handle;
    private bool $ownsHandle;

    public function __construct(FFI\CData|string $handleOrPk, bool $ownsHandle = true)
    {
        if (is_string($handleOrPk)) {
            $this->handle = self::ffi()->zvec_doc_create($handleOrPk);
            $this->ownsHandle = true;
        } else {
            $this->handle = $handleOrPk;
            $this->ownsHandle = $ownsHandle;
        }
    }

    public function __destruct()
    {
        if ($this->ownsHandle) {
            self::ffi()->zvec_doc_free($this->handle);
        }
    }

    private function __clone()
    {
    }

    public function getHandle(): FFI\CData
    {
        return $this->handle;
    }

    public function setInt64(string $field, int $value): self
    {
        self::ffi()->zvec_doc_set_int64($this->handle, $field, $value);
        return $this;
    }

    public function setString(string $field, string $value): self
    {
        self::ffi()->zvec_doc_set_string($this->handle, $field, $value);
        return $this;
    }

    public function setFloat(string $field, float $value): self
    {
        self::ffi()->zvec_doc_set_float($this->handle, $field, $value);
        return $this;
    }

    public function setDouble(string $field, float $value): self
    {
        self::ffi()->zvec_doc_set_double($this->handle, $field, $value);
        return $this;
    }

    /**
     * @param float[] $vector
     */
    public function setVectorFp32(string $field, array $vector): self
    {
        $ffi = self::ffi();
        $dim = count($vector);
        $data = $ffi->new("float[$dim]");
        foreach ($vector as $i => $v) {
            $data[$i] = $v;
        }
        $ffi->zvec_doc_set_vector_fp32($this->handle, $field, $data, $dim);
        return $this;
    }

    /**
     * @param float[] $vector
     */
    public function setVectorFp64(string $field, array $vector): self
    {
        $ffi = self::ffi();
        $dim = count($vector);
        $data = $ffi->new("double[$dim]");
        foreach ($vector as $i => $v) {
            $data[$i] = $v;
        }
        $ffi->zvec_doc_set_vector_fp64($this->handle, $field, $data, $dim);
        return $this;
    }

    public function setBool(string $field, bool $value): self
    {
        self::ffi()->zvec_doc_set_bool($this->handle, $field, $value ? 1 : 0);
        return $this;
    }

    public function setInt32(string $field, int $value): self
    {
        self::ffi()->zvec_doc_set_int32($this->handle, $field, $value);
        return $this;
    }

    public function setUint32(string $field, int $value): self
    {
        self::ffi()->zvec_doc_set_uint32($this->handle, $field, $value);
        return $this;
    }

    public function setUint64(string $field, int $value): self
    {
        self::ffi()->zvec_doc_set_uint64($this->handle, $field, $value);
        return $this;
    }

    /**
     * @param int[] $vector
     */
    public function setVectorInt8(string $field, array $vector): self
    {
        $ffi = self::ffi();
        $dim = count($vector);
        $data = $ffi->new("int8_t[$dim]");
        foreach ($vector as $i => $v) {
            $data[$i] = $v;
        }
        $ffi->zvec_doc_set_vector_int8($this->handle, $field, $data, $dim);
        return $this;
    }

    /**
     * @param int[] $vector
     */
    public function setVectorFp16(string $field, array $vector): self
    {
        $ffi = self::ffi();
        $dim = count($vector);
        $data = $ffi->new("uint16_t[$dim]");
        foreach ($vector as $i => $v) {
            $data[$i] = $v;
        }
        $ffi->zvec_doc_set_vector_fp16($this->handle, $field, $data, $dim);
        return $this;
    }

    /**
     * @param int[] $indices
     * @param float[] $values
     */
    public function setSparseVectorFp32(string $field, array $indices, array $values): self
    {
        $ffi = self::ffi();
        $count = count($indices);
        if ($count !== count($values)) {
            throw new ZVecException("Indices and values arrays must have the same length");
        }
        
        // Handle empty sparse vector
        if ($count === 0) {
            $ffi->zvec_doc_set_sparse_vector_fp32($this->handle, $field, null, null, 0);
            return $this;
        }
        
        $idxData = $ffi->new("uint32_t[$count]");
        $valData = $ffi->new("float[$count]");
        
        for ($i = 0; $i < $count; $i++) {
            $idxData[$i] = $indices[$i];
            $valData[$i] = $values[$i];
        }
        
        $ffi->zvec_doc_set_sparse_vector_fp32($this->handle, $field, $idxData, $valData, $count);
        return $this;
    }

    /**
     * @param int[] $vector
     */
    public function setVectorInt4(string $field, array $vector): self
    {
        $ffi = self::ffi();
        $dim = count($vector);
        $data = $ffi->new("int8_t[$dim]");
        foreach ($vector as $i => $v) {
            $data[$i] = $v;
        }
        $ffi->zvec_doc_set_vector_int4($this->handle, $field, $data, $dim);
        return $this;
    }

    /**
     * @param int[] $vector
     */
    public function setVectorInt16(string $field, array $vector): self
    {
        $ffi = self::ffi();
        $dim = count($vector);
        $data = $ffi->new("int16_t[$dim]");
        foreach ($vector as $i => $v) {
            $data[$i] = $v;
        }
        $ffi->zvec_doc_set_vector_int16($this->handle, $field, $data, $dim);
        return $this;
    }

    /**
     * @param int[] $data
     */
    public function setVectorBinary32(string $field, array $data): self
    {
        $ffi = self::ffi();
        $dim = count($data);
        $arr = $ffi->new("uint32_t[$dim]");
        foreach ($data as $i => $v) {
            $arr[$i] = $v;
        }
        $ffi->zvec_doc_set_vector_binary32($this->handle, $field, $arr, $dim);
        return $this;
    }

    /**
     * @param int[] $data
     */
    public function setVectorBinary64(string $field, array $data): self
    {
        $ffi = self::ffi();
        $dim = count($data);
        $arr = $ffi->new("uint64_t[$dim]");
        foreach ($data as $i => $v) {
            $arr[$i] = $v;
        }
        $ffi->zvec_doc_set_vector_binary64($this->handle, $field, $arr, $dim);
        return $this;
    }

    /**
     * @param int[] $indices
     * @param int[] $values (FP16 raw uint16 values)
     */
    public function setSparseVectorFp16(string $field, array $indices, array $values): self
    {
        $ffi = self::ffi();
        $count = count($indices);
        if ($count !== count($values)) {
            throw new ZVecException("Indices and values arrays must have the same length");
        }

        if ($count === 0) {
            $ffi->zvec_doc_set_sparse_vector_fp16($this->handle, $field, null, null, 0);
            return $this;
        }

        $idxData = $ffi->new("uint32_t[$count]");
        $valData = $ffi->new("uint16_t[$count]");

        for ($i = 0; $i < $count; $i++) {
            $idxData[$i] = $indices[$i];
            $valData[$i] = $values[$i];
        }

        $ffi->zvec_doc_set_sparse_vector_fp16($this->handle, $field, $idxData, $valData, $count);
        return $this;
    }

    /**
     * @param string $data Raw binary data
     */
    public function setBinary(string $field, string $data): self
    {
        $ffi = self::ffi();
        $size = strlen($data);
        if ($size === 0) {
            $ffi->zvec_doc_set_binary($this->handle, $field, null, 0);
            return $this;
        }
        $buf = $ffi->new("uint8_t[$size]", false);
        FFI::memcpy($buf, $data, $size);
        $ffi->zvec_doc_set_binary($this->handle, $field, $buf, $size);
        FFI::free($buf);
        return $this;
    }

    /**
     * @param int[] $data
     */
    public function setArrayInt32(string $field, array $data): self
    {
        $ffi = self::ffi();
        $count = count($data);
        $arr = $ffi->new("int32_t[$count]");
        foreach ($data as $i => $v) {
            $arr[$i] = $v;
        }
        $ffi->zvec_doc_set_array_int32($this->handle, $field, $arr, $count);
        return $this;
    }

    /**
     * @param int[] $data
     */
    public function setArrayInt64(string $field, array $data): self
    {
        $ffi = self::ffi();
        $count = count($data);
        $arr = $ffi->new("int64_t[$count]");
        foreach ($data as $i => $v) {
            $arr[$i] = $v;
        }
        $ffi->zvec_doc_set_array_int64($this->handle, $field, $arr, $count);
        return $this;
    }

    /**
     * @param int[] $data
     */
    public function setArrayUint32(string $field, array $data): self
    {
        $ffi = self::ffi();
        $count = count($data);
        $arr = $ffi->new("uint32_t[$count]");
        foreach ($data as $i => $v) {
            $arr[$i] = $v;
        }
        $ffi->zvec_doc_set_array_uint32($this->handle, $field, $arr, $count);
        return $this;
    }

    /**
     * @param int[] $data
     */
    public function setArrayUint64(string $field, array $data): self
    {
        $ffi = self::ffi();
        $count = count($data);
        $arr = $ffi->new("uint64_t[$count]");
        foreach ($data as $i => $v) {
            $arr[$i] = $v;
        }
        $ffi->zvec_doc_set_array_uint64($this->handle, $field, $arr, $count);
        return $this;
    }

    /**
     * @param float[] $data
     */
    public function setArrayFloat(string $field, array $data): self
    {
        $ffi = self::ffi();
        $count = count($data);
        $arr = $ffi->new("float[$count]");
        foreach ($data as $i => $v) {
            $arr[$i] = $v;
        }
        $ffi->zvec_doc_set_array_float($this->handle, $field, $arr, $count);
        return $this;
    }

    /**
     * @param float[] $data
     */
    public function setArrayDouble(string $field, array $data): self
    {
        $ffi = self::ffi();
        $count = count($data);
        $arr = $ffi->new("double[$count]");
        foreach ($data as $i => $v) {
            $arr[$i] = $v;
        }
        $ffi->zvec_doc_set_array_double($this->handle, $field, $arr, $count);
        return $this;
    }

    /**
     * @param string[] $strings
     */
    public function setArrayString(string $field, array $strings): self
    {
        $ffi = self::ffi();
        $count = count($strings);
        $cStrings = [];
        $arr = $ffi->new("char*[$count]");
        foreach ($strings as $i => $s) {
            $len = strlen($s) + 1;
            $cStr = $ffi->new("char[$len]", false);
            FFI::memcpy($cStr, $s, strlen($s));
            $cStr[$len - 1] = "\0";
            $cStrings[] = $cStr;
            $arr[$i] = $cStr;
        }
        $ffi->zvec_doc_set_array_string($this->handle, $field, $arr, $count);
        foreach ($cStrings as $cStr) {
            FFI::free($cStr);
        }
        return $this;
    }

    /**
     * @param bool[] $data
     */
    public function setArrayBool(string $field, array $data): self
    {
        $ffi = self::ffi();
        $count = count($data);
        $arr = $ffi->new("uint8_t[$count]");
        foreach ($data as $i => $v) {
            $arr[$i] = $v ? 1 : 0;
        }
        $ffi->zvec_doc_set_array_bool($this->handle, $field, $arr, $count);
        return $this;
    }

    public function getPk(): string
    {
        $result = self::ffi()->zvec_doc_get_pk($this->handle);
        if (is_string($result)) {
            return $result;
        }
        return FFI::string($result);
    }

    public function getScore(): float
    {
        return self::ffi()->zvec_doc_get_score($this->handle);
    }

    public function getInt64(string $field): ?int
    {
        $ffi = self::ffi();
        $out = $ffi->new('int64_t');
        if ($ffi->zvec_doc_get_int64($this->handle, $field, FFI::addr($out))) {
            return $out->cdata;
        }
        return null;
    }

    public function getString(string $field): ?string
    {
        $ffi = self::ffi();
        $out = $ffi->new('char*');
        if ($ffi->zvec_doc_get_string($this->handle, $field, FFI::addr($out))) {
            return FFI::string($out);
        }
        return null;
    }

    public function getFloat(string $field): ?float
    {
        $ffi = self::ffi();
        $out = $ffi->new('float');
        if ($ffi->zvec_doc_get_float($this->handle, $field, FFI::addr($out))) {
            return $out->cdata;
        }
        return null;
    }

    public function getDouble(string $field): ?float
    {
        $ffi = self::ffi();
        $out = $ffi->new('double');
        if ($ffi->zvec_doc_get_double($this->handle, $field, FFI::addr($out))) {
            return $out->cdata;
        }
        return null;
    }

    /**
     * @return float[]|null
     */
    public function getVectorFp32(string $field): ?array
    {
        $ffi = self::ffi();
        $out = $ffi->new('float*');
        $dim = $ffi->new('uint32_t');
        if ($ffi->zvec_doc_get_vector_fp32($this->handle, $field, FFI::addr($out), FFI::addr($dim))) {
            $result = [];
            for ($i = 0; $i < $dim->cdata; $i++) {
                $result[] = $out[$i];
            }
            return $result;
        }
        return null;
    }

    /**
     * @return float[]|null
     */
    public function getVectorFp64(string $field): ?array
    {
        $ffi = self::ffi();
        $out = $ffi->new('double*');
        $dim = $ffi->new('uint32_t');
        if ($ffi->zvec_doc_get_vector_fp64($this->handle, $field, FFI::addr($out), FFI::addr($dim))) {
            $result = [];
            for ($i = 0; $i < $dim->cdata; $i++) {
                $result[] = $out[$i];
            }
            return $result;
        }
        return null;
    }

    public function getBool(string $field): ?bool
    {
        $ffi = self::ffi();
        $out = $ffi->new('int');
        if ($ffi->zvec_doc_get_bool($this->handle, $field, FFI::addr($out))) {
            return $out->cdata !== 0;
        }
        return null;
    }

    public function getInt32(string $field): ?int
    {
        $ffi = self::ffi();
        $out = $ffi->new('int32_t');
        if ($ffi->zvec_doc_get_int32($this->handle, $field, FFI::addr($out))) {
            return $out->cdata;
        }
        return null;
    }

    public function getUint32(string $field): ?int
    {
        $ffi = self::ffi();
        $out = $ffi->new('uint32_t');
        if ($ffi->zvec_doc_get_uint32($this->handle, $field, FFI::addr($out))) {
            return $out->cdata;
        }
        return null;
    }

    public function getUint64(string $field): ?int
    {
        $ffi = self::ffi();
        $out = $ffi->new('uint64_t');
        if ($ffi->zvec_doc_get_uint64($this->handle, $field, FFI::addr($out))) {
            return $out->cdata;
        }
        return null;
    }

    /**
     * @return int[]|null
     */
    public function getVectorInt8(string $field): ?array
    {
        $ffi = self::ffi();
        $out = $ffi->new('int8_t*');
        $dim = $ffi->new('uint32_t');
        if ($ffi->zvec_doc_get_vector_int8($this->handle, $field, FFI::addr($out), FFI::addr($dim))) {
            $result = [];
            for ($i = 0; $i < $dim->cdata; $i++) {
                $result[] = $out[$i];
            }
            return $result;
        }
        return null;
    }

    /**
     * @return int[]|null
     */
    public function getVectorFp16(string $field): ?array
    {
        $ffi = self::ffi();
        $out = $ffi->new('uint16_t*');
        $dim = $ffi->new('uint32_t');
        if ($ffi->zvec_doc_get_vector_fp16($this->handle, $field, FFI::addr($out), FFI::addr($dim))) {
            $result = [];
            for ($i = 0; $i < $dim->cdata; $i++) {
                $result[] = $out[$i];
            }
            return $result;
        }
        return null;
    }

    /**
     * @return array{indices: int[], values: float[]}|null
     */
    public function getSparseVectorFp32(string $field): ?array
    {
        $ffi = self::ffi();
        $indicesOut = $ffi->new('uint32_t*');
        $valuesOut = $ffi->new('float*');
        $count = $ffi->new('uint32_t');
        
        if ($ffi->zvec_doc_get_sparse_vector_fp32($this->handle, $field, FFI::addr($indicesOut), FFI::addr($valuesOut), FFI::addr($count))) {
            $indices = [];
            $values = [];
            for ($i = 0; $i < $count->cdata; $i++) {
                $indices[] = $indicesOut[$i];
                $values[] = $valuesOut[$i];
            }
            return ['indices' => $indices, 'values' => $values];
        }
        return null;
    }

    /**
     * @return int[]|null
     */
    public function getVectorInt4(string $field): ?array
    {
        $ffi = self::ffi();
        $out = $ffi->new('int8_t*');
        $dim = $ffi->new('uint32_t');
        if ($ffi->zvec_doc_get_vector_int4($this->handle, $field, FFI::addr($out), FFI::addr($dim))) {
            $result = [];
            for ($i = 0; $i < $dim->cdata; $i++) {
                $result[] = $out[$i];
            }
            return $result;
        }
        return null;
    }

    /**
     * @return int[]|null
     */
    public function getVectorInt16(string $field): ?array
    {
        $ffi = self::ffi();
        $out = $ffi->new('int16_t*');
        $dim = $ffi->new('uint32_t');
        if ($ffi->zvec_doc_get_vector_int16($this->handle, $field, FFI::addr($out), FFI::addr($dim))) {
            $result = [];
            for ($i = 0; $i < $dim->cdata; $i++) {
                $result[] = $out[$i];
            }
            return $result;
        }
        return null;
    }

    /**
     * @return int[]|null
     */
    public function getVectorBinary32(string $field): ?array
    {
        $ffi = self::ffi();
        $out = $ffi->new('uint32_t*');
        $dim = $ffi->new('uint32_t');
        if ($ffi->zvec_doc_get_vector_binary32($this->handle, $field, FFI::addr($out), FFI::addr($dim))) {
            $result = [];
            for ($i = 0; $i < $dim->cdata; $i++) {
                $result[] = $out[$i];
            }
            return $result;
        }
        return null;
    }

    /**
     * @return int[]|null
     */
    public function getVectorBinary64(string $field): ?array
    {
        $ffi = self::ffi();
        $out = $ffi->new('uint64_t*');
        $dim = $ffi->new('uint32_t');
        if ($ffi->zvec_doc_get_vector_binary64($this->handle, $field, FFI::addr($out), FFI::addr($dim))) {
            $result = [];
            for ($i = 0; $i < $dim->cdata; $i++) {
                $result[] = $out[$i];
            }
            return $result;
        }
        return null;
    }

    /**
     * @return array{indices: int[], values: int[]}|null
     */
    public function getSparseVectorFp16(string $field): ?array
    {
        $ffi = self::ffi();
        $indicesOut = $ffi->new('uint32_t*');
        $valuesOut = $ffi->new('uint16_t*');
        $count = $ffi->new('uint32_t');
        
        if ($ffi->zvec_doc_get_sparse_vector_fp16($this->handle, $field, FFI::addr($indicesOut), FFI::addr($valuesOut), FFI::addr($count))) {
            $indices = [];
            $values = [];
            for ($i = 0; $i < $count->cdata; $i++) {
                $indices[] = $indicesOut[$i];
                $values[] = $valuesOut[$i];
            }
            return ['indices' => $indices, 'values' => $values];
        }
        return null;
    }

    /**
     * @return string|null Raw binary data
     */
    public function getBinary(string $field): ?string
    {
        $ffi = self::ffi();
        $out = $ffi->new('uint8_t*');
        $size = $ffi->new('uint32_t');
        if ($ffi->zvec_doc_get_binary($this->handle, $field, FFI::addr($out), FFI::addr($size))) {
            if ($size->cdata === 0) {
                return '';
            }
            return FFI::string($out, $size->cdata);
        }
        return null;
    }

    /**
     * @return int[]|null
     */
    public function getArrayInt32(string $field): ?array
    {
        $ffi = self::ffi();
        $out = $ffi->new('int32_t*');
        $count = $ffi->new('uint32_t');
        if ($ffi->zvec_doc_get_array_int32($this->handle, $field, FFI::addr($out), FFI::addr($count))) {
            $result = [];
            for ($i = 0; $i < $count->cdata; $i++) {
                $result[] = $out[$i];
            }
            return $result;
        }
        return null;
    }

    /**
     * @return int[]|null
     */
    public function getArrayInt64(string $field): ?array
    {
        $ffi = self::ffi();
        $out = $ffi->new('int64_t*');
        $count = $ffi->new('uint32_t');
        if ($ffi->zvec_doc_get_array_int64($this->handle, $field, FFI::addr($out), FFI::addr($count))) {
            $result = [];
            for ($i = 0; $i < $count->cdata; $i++) {
                $result[] = $out[$i];
            }
            return $result;
        }
        return null;
    }

    /**
     * @return int[]|null
     */
    public function getArrayUint32(string $field): ?array
    {
        $ffi = self::ffi();
        $out = $ffi->new('uint32_t*');
        $count = $ffi->new('uint32_t');
        if ($ffi->zvec_doc_get_array_uint32($this->handle, $field, FFI::addr($out), FFI::addr($count))) {
            $result = [];
            for ($i = 0; $i < $count->cdata; $i++) {
                $result[] = $out[$i];
            }
            return $result;
        }
        return null;
    }

    /**
     * @return int[]|null
     */
    public function getArrayUint64(string $field): ?array
    {
        $ffi = self::ffi();
        $out = $ffi->new('uint64_t*');
        $count = $ffi->new('uint32_t');
        if ($ffi->zvec_doc_get_array_uint64($this->handle, $field, FFI::addr($out), FFI::addr($count))) {
            $result = [];
            for ($i = 0; $i < $count->cdata; $i++) {
                $result[] = $out[$i];
            }
            return $result;
        }
        return null;
    }

    /**
     * @return float[]|null
     */
    public function getArrayFloat(string $field): ?array
    {
        $ffi = self::ffi();
        $out = $ffi->new('float*');
        $count = $ffi->new('uint32_t');
        if ($ffi->zvec_doc_get_array_float($this->handle, $field, FFI::addr($out), FFI::addr($count))) {
            $result = [];
            for ($i = 0; $i < $count->cdata; $i++) {
                $result[] = $out[$i];
            }
            return $result;
        }
        return null;
    }

    /**
     * @return float[]|null
     */
    public function getArrayDouble(string $field): ?array
    {
        $ffi = self::ffi();
        $out = $ffi->new('double*');
        $count = $ffi->new('uint32_t');
        if ($ffi->zvec_doc_get_array_double($this->handle, $field, FFI::addr($out), FFI::addr($count))) {
            $result = [];
            for ($i = 0; $i < $count->cdata; $i++) {
                $result[] = $out[$i];
            }
            return $result;
        }
        return null;
    }

    /**
     * @return string[]|null
     */
    public function getArrayString(string $field): ?array
    {
        $ffi = self::ffi();
        $buf = $ffi->new('char[8192]');
        $count = $ffi->new('uint32_t');
        $len = $ffi->zvec_doc_get_array_string($this->handle, $field, $buf, 8192, FFI::addr($count));
        if ($len <= 0) {
            return null;
        }
        $str = FFI::string($buf, $len);
        return $str === '' ? [] : explode("\0", $str);
    }

    /**
     * @return bool[]|null
     */
    public function getArrayBool(string $field): ?array
    {
        $ffi = self::ffi();
        $buf = $ffi->new('uint8_t[4096]');
        $count = $ffi->zvec_doc_get_array_bool($this->handle, $field, $buf, 4096);
        if ($count <= 0) {
            return null;
        }
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = $buf[$i] !== 0;
        }
        return $result;
    }

    public function hasField(string $field): bool
    {
        return self::ffi()->zvec_doc_has_field($this->handle, $field) !== 0;
    }

    public function hasVector(string $field): bool
    {
        return self::ffi()->zvec_doc_has_vector($this->handle, $field) !== 0;
    }

    /**
     * @return string[]
     */
    public function fieldNames(): array
    {
        $ffi = self::ffi();
        $buf = $ffi->new('char[8192]');
        $len = $ffi->zvec_doc_field_names($this->handle, $buf, 8192);
        if ($len < 0) {
            return [];
        }
        $str = FFI::string($buf);
        return $str === '' ? [] : explode("\n", $str);
    }

    /**
     * @return string[]
     */
    public function vectorNames(): array
    {
        $ffi = self::ffi();
        $buf = $ffi->new('char[8192]');
        $len = $ffi->zvec_doc_vector_names($this->handle, $buf, 8192);
        if ($len < 0) {
            return [];
        }
        $str = FFI::string($buf);
        return $str === '' ? [] : explode("\n", $str);
    }

    // --- Enhanced Doc API ---

    public function setFieldNull(string $field): self
    {
        self::ffi()->zvec_doc_set_field_null($this->handle, $field);
        return $this;
    }

    public function isFieldNull(string $field): bool
    {
        return self::ffi()->zvec_doc_is_field_null($this->handle, $field) !== 0;
    }

    public function removeField(string $field): self
    {
        self::ffi()->zvec_doc_remove_field($this->handle, $field);
        return $this;
    }

    public function merge(ZVecDoc $other): self
    {
        self::ffi()->zvec_doc_merge($this->handle, $other->handle);
        return $this;
    }

    public function serialize(): string
    {
        $ffi = self::ffi();
        $data = $ffi->new('uint8_t*');
        $size = $ffi->new('size_t');
        ZVec::checkStatus($ffi->zvec_doc_serialize($this->handle, FFI::addr($data), FFI::addr($size)));
        if ($size->cdata === 0) {
            return '';
        }
        $result = FFI::string($data, $size->cdata);
        $ffi->zvec_free_serialized($data);
        return $result;
    }

    public static function deserialize(string $data): self
    {
        $ffi = self::ffi();
        $size = strlen($data);
        $buf = $ffi->new("uint8_t[$size]", false);
        FFI::memcpy($buf, $data, $size);
        try {
            $out = $ffi->new('zvec_doc_t');
            ZVec::checkStatus($ffi->zvec_doc_deserialize($buf, $size, FFI::addr($out)));
            return new self($out, true);
        } finally {
            FFI::free($buf);
        }
    }

    public function isEmpty(): bool
    {
        return self::ffi()->zvec_doc_is_empty($this->handle) !== 0;
    }

    public function clear(): self
    {
        self::ffi()->zvec_doc_clear($this->handle);
        return $this;
    }

    public function getMemoryUsage(): int
    {
        return self::ffi()->zvec_doc_memory_usage($this->handle);
    }

    public function setOperator(int $op): self
    {
        self::ffi()->zvec_doc_set_operator($this->handle, $op);
        return $this;
    }

    public function getOperator(): int
    {
        return self::ffi()->zvec_doc_get_operator($this->handle);
    }

    public const OP_INSERT = 0;
    public const OP_UPDATE = 1;
    public const OP_UPSERT = 2;
    public const OP_DELETE = 3;

    private static function ffi(): FFI
    {
        return (new ReflectionClass(ZVec::class))->getMethod('ffi')->invoke(null);
    }
}
