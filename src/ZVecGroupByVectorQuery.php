<?php

declare(strict_types=1);

if (extension_loaded('zvec')) return;

class ZVecGroupByVectorQuery extends ZVecVectorQuery
{
    /**
     * @param float[] $vector
     */
    public function __construct(string $fieldName, array $vector, string $groupByField, int $groupCount = 2, int $groupTopk = 3)
    {
        if ($fieldName === '') {
            throw new ZVecException('Field name must not be empty');
        }
        if ($groupByField === '') {
            throw new ZVecException('Group by field must not be empty');
        }
        if ($groupCount <= 0) {
            throw new ZVecException("groupCount must be a positive integer, got: {$groupCount}");
        }
        if ($groupTopk <= 0) {
            throw new ZVecException("groupTopk must be a positive integer, got: {$groupTopk}");
        }
        $ffi = self::ffi();
        $this->handle = $ffi->zvec_group_by_vector_query_create();
        $this->handleType = 'group_by_query';
        $this->fieldName = $fieldName;
        $this->vector = $vector;
        $this->queryParamType = ZVec::QUERY_PARAM_NONE;
        $this->hnswEf = 200;
        $this->ivfNprobe = 10;
        $this->radius = 0.0;
        $this->isLinear = false;
        $this->isUsingRefiner = false;

        $ffi->zvec_group_by_vector_query_set_field_name($this->handle, $fieldName);
        $ffi->zvec_group_by_vector_query_set_group_by_field($this->handle, $groupByField);
        $ffi->zvec_group_by_vector_query_set_group_count($this->handle, $groupCount);
        $ffi->zvec_group_by_vector_query_set_group_topk($this->handle, $groupTopk);

        $dim = count($vector);
        if ($dim > 0) {
            $data = $ffi->new("float[$dim]");
            foreach ($vector as $i => $v) {
                $data[$i] = (float)$v;
            }
            $ffi->zvec_group_by_vector_query_set_vector_fp32($this->handle, $data, $dim);
        }
    }

    public function setGroupByField(string $field): self
    {
        self::ffi()->zvec_group_by_vector_query_set_group_by_field($this->handle, $field);
        return $this;
    }

    public function setGroupCount(int $count): self
    {
        self::ffi()->zvec_group_by_vector_query_set_group_count($this->handle, $count);
        return $this;
    }

    public function setGroupTopk(int $topk): self
    {
        self::ffi()->zvec_group_by_vector_query_set_group_topk($this->handle, $topk);
        return $this;
    }

    /**
     * Override: use group_by_vector_query FFI functions to prevent UB.
     * GroupByVectorQuery does not support general topk; use setGroupTopk() instead.
     */
    public function setTopk(int $topk): self
    {
        throw new ZVecException(
            'topk is not supported for group-by queries. Use setGroupTopk() to control results per group.'
        );
    }

    public function setRadius(float $radius): self
    {
        $this->radius = $radius;
        self::ffi()->zvec_group_by_vector_query_set_radius($this->handle, $radius);
        return $this;
    }

    public function setLinear(bool $linear): self
    {
        $this->isLinear = $linear;
        self::ffi()->zvec_group_by_vector_query_set_is_linear($this->handle, $linear ? 1 : 0);
        return $this;
    }

    public function setUsingRefiner(bool $refiner): self
    {
        $this->isUsingRefiner = $refiner;
        self::ffi()->zvec_group_by_vector_query_set_using_refiner($this->handle, $refiner ? 1 : 0);
        return $this;
    }

    public function setIncludeVector(bool $include): self
    {
        self::ffi()->zvec_group_by_vector_query_set_include_vector($this->handle, $include ? 1 : 0);
        return $this;
    }

    public function setFilter(string $filter): self
    {
        self::ffi()->zvec_group_by_vector_query_set_filter($this->handle, $filter);
        return $this;
    }

    /**
     * @param string[] $fields
     */
    public function setOutputFields(array $fields): self
    {
        $ffi = self::ffi();
        $count = count($fields);
        if ($count > 0) {
            $cStrings = [];
            $arr = $ffi->new("char*[$count]");
            foreach ($fields as $i => $f) {
                $len = strlen($f) + 1;
                $cStr = $ffi->new("char[$len]", false);
                FFI::memcpy($cStr, $f, strlen($f));
                $cStr[$len - 1] = "\0";
                $cStrings[] = $cStr;
                $arr[$i] = $cStr;
            }
            $ffi->zvec_group_by_vector_query_set_output_fields($this->handle, $arr, $count);
            foreach ($cStrings as $cStr) {
                FFI::free($cStr);
            }
        }
        return $this;
    }

    public function setHnswParams(int $ef): self
    {
        throw new ZVecException(
            'HNSW params are not directly supported for group-by queries.'
        );
    }

    public function setHnswRabitqParams(int $ef): self
    {
        throw new ZVecException(
            'HNSW RaBitQ params are not directly supported for group-by queries.'
        );
    }

    public function setIvfParams(int $nprobe): self
    {
        throw new ZVecException(
            'IVF params are not directly supported for group-by queries.'
        );
    }

    public function setFlatParams(): self
    {
        throw new ZVecException(
            'Flat mode is not directly supported for group-by queries.'
        );
    }

    public function setVamanaParams(int $efSearch): self
    {
        throw new ZVecException(
            'Vamana params are not directly supported for group-by queries.'
        );
    }

    private static function ffi(): FFI
    {
        return ZVec::ffi();
    }
}
