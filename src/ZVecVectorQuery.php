<?php

declare(strict_types=1);

namespace CrazyGoat\ZVec;

use FFI;

if (extension_loaded('zvec')) return;

class ZVecVectorQuery implements ZVecQueryInterface
{
    private FFI\CData $handle;
    private bool $closed = false;

    public string $fieldName;

    /**
     * @var float[]|int[] Sparse vectors: [index => weight], Dense vectors: [0.1, 0.2, ...]
     */
    public array $vector;

    /**
     * For query by document ID instead of explicit vector
     */
    public ?string $docId = null;

    public int $queryParamType;
    public int $hnswEf;
    public int $ivfNprobe;
    public float $radius;
    public bool $isLinear;
    public bool $isUsingRefiner;
    public bool $useFp64 = false;
    public ?int $topk = null;
    public ?bool $includeVector = null;
    public ?string $filter = null;

    /**
     * @param float[] $vector Dense vector data
     */
    public function __construct(string $fieldName, array $vector)
    {
        if ($fieldName === '') {
            throw new ZVecException('Field name must not be empty');
        }
        $ffi = self::ffi();
        $this->handle = $ffi->zvec_vector_query_create();
        $this->fieldName = $fieldName;
        $this->vector = $vector;
        $this->queryParamType = ZVec::QUERY_PARAM_NONE;
        $this->hnswEf = 200;
        $this->ivfNprobe = 10;
        $this->radius = 0.0;
        $this->isLinear = false;
        $this->isUsingRefiner = false;

        $ffi->zvec_vector_query_set_field_name($this->handle, $fieldName);
        $dim = count($vector);
        if ($dim > 0) {
            $data = $ffi->new("float[$dim]");
            foreach ($vector as $i => $v) {
                $data[$i] = (float)$v;
            }
            $ffi->zvec_vector_query_set_vector_fp32($this->handle, $data, $dim);
        }
    }

    public function __destruct()
    {
        if (!$this->closed) {
            $this->free();
        }
    }

    private function __clone()
    {
    }

    public function getHandle(): FFI\CData
    {
        return $this->handle;
    }

    public function free(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        try {
            self::ffi()->zvec_vector_query_free($this->handle);
        } catch (\Throwable) {
        }
    }

    public function setFp64(bool $fp64 = true): self
    {
        $this->useFp64 = $fp64;
        return $this;
    }

    /**
     * Create a VectorQuery from document ID (find similar documents)
     */
    public static function fromId(string $fieldName, string $docId): self
    {
        $query = new self($fieldName, []);
        $query->docId = $docId;
        return $query;
    }

    public function setHnswParams(int $ef): self
    {
        $this->queryParamType = ZVec::QUERY_PARAM_HNSW;
        $this->hnswEf = $ef;
        self::ffi()->zvec_vector_query_set_hnsw_ef($this->handle, $ef);
        return $this;
    }

    public function setHnswRabitqParams(int $ef): self
    {
        $this->queryParamType = ZVec::QUERY_PARAM_HNSW_RABITQ;
        $this->hnswEf = $ef;
        self::ffi()->zvec_vector_query_set_hnsw_ef($this->handle, $ef);
        return $this;
    }

    public function setIvfParams(int $nprobe): self
    {
        $this->queryParamType = ZVec::QUERY_PARAM_IVF;
        $this->ivfNprobe = $nprobe;
        self::ffi()->zvec_vector_query_set_ivf_nprobe($this->handle, $nprobe);
        return $this;
    }

    public function setFlatParams(): self
    {
        $this->queryParamType = ZVec::QUERY_PARAM_FLAT;
        self::ffi()->zvec_vector_query_set_flat_mode($this->handle);
        return $this;
    }

    public function setVamanaParams(int $efSearch): self
    {
        $this->queryParamType = ZVec::QUERY_PARAM_VAMANA;
        $this->hnswEf = $efSearch;
        self::ffi()->zvec_vector_query_set_hnsw_ef($this->handle, $efSearch);
        return $this;
    }

    public function setRadius(float $radius): self
    {
        $this->radius = $radius;
        self::ffi()->zvec_vector_query_set_radius($this->handle, $radius);
        return $this;
    }

    public function setLinear(bool $linear): self
    {
        $this->isLinear = $linear;
        self::ffi()->zvec_vector_query_set_is_linear($this->handle, $linear ? 1 : 0);
        return $this;
    }

    public function setUsingRefiner(bool $refiner): self
    {
        $this->isUsingRefiner = $refiner;
        self::ffi()->zvec_vector_query_set_using_refiner($this->handle, $refiner ? 1 : 0);
        return $this;
    }

    public function setTopk(int $topk): self
    {
        $this->topk = $topk;
        self::ffi()->zvec_vector_query_set_topk($this->handle, $topk);
        return $this;
    }

    public function setIncludeVector(bool $include): self
    {
        $this->includeVector = $include;
        self::ffi()->zvec_vector_query_set_include_vector($this->handle, $include ? 1 : 0);
        return $this;
    }

    public function setFilter(string $filter): self
    {
        $this->filter = $filter;
        self::ffi()->zvec_vector_query_set_filter($this->handle, $filter);
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
            [$arr, $count, $cStrings] = ZVec::toCStringArray($ffi, $fields);
            try {
                $ffi->zvec_vector_query_set_output_fields($this->handle, $arr, $count);
            } finally {
                ZVec::freeCStringArray($cStrings);
            }
        }
        return $this;
    }

    private static function ffi(): FFI
    {
        return ZVec::ffi();
    }
}
