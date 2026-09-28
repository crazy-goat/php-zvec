<?php

declare(strict_types=1);

namespace CrazyGoat\ZVec;

use FFI;

if (extension_loaded('zvec')) return;

/**
 * Query builder for vector search.
 *
 * Supports search parameters (HNSW ef, IVF nprobe, radius, linear mode),
 * reranking via ZVecReRanker, and filter expressions. Create via constructor
 * with field name and query vector, or use fromId() for document-based queries.
 * Return type of ZVec::query() differs when a reranker is attached.
 *
 * @see ZVec::query()
 * @see ZVecGroupByVectorQuery For grouped queries
 * @see ZVecReRanker
 */
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
    public ?bool $includeDocId = null;
    public ?int $prefetchOffset = null;
    public ?int $prefetchLines = null;
    public ?int $diskAnnListSize = null;
    public ?string $ftsField = null;
    public ?string $ftsQueryString = null;
    public ?string $ftsMatchString = null;
    public ?string $ftsDefaultOperator = null;
    public ?string $filter = null;

    /**
     * @param float[] $vector Dense vector data
     * @throws ZVecException
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

    /** @throws ZVecException */
    public function setHnswParams(int $ef): self
    {
        $this->queryParamType = ZVec::QUERY_PARAM_HNSW;
        $this->hnswEf = $ef;
        self::ffi()->zvec_vector_query_set_hnsw_ef($this->handle, $ef);
        return $this;
    }

    /** @throws ZVecException */
    /**
     * Request the internal numeric document id alongside the primary key.
     *
     * Only populated on documents returned by a query that enabled this flag;
     * read it back with {@see ZVecDoc::getDocId()}.
     *
     * @throws ZVecException
     */
    public function setIncludeDocId(bool $include): self
    {
        $this->includeDocId = $include;
        self::ffi()->zvec_vector_query_set_include_doc_id($this->handle, $include ? 1 : 0);
        return $this;
    }

    /**
     * Tune HNSW search-time software prefetch.
     *
     * Only meaningful for HNSW indexes. Both values are remembered by the query,
     * so the call order relative to {@see setHnswParams()} does not matter.
     *
     * @throws ZVecException
     */
    public function setHnswPrefetch(int $prefetchOffset, int $prefetchLines): self
    {
        if ($prefetchOffset < 0) {
            throw new ZVecException('prefetchOffset must be >= 0');
        }
        if ($prefetchLines < 0) {
            throw new ZVecException('prefetchLines must be >= 0');
        }
        $this->prefetchOffset = $prefetchOffset;
        $this->prefetchLines = $prefetchLines;
        self::ffi()->zvec_vector_query_set_hnsw_prefetch($this->handle, $prefetchOffset, $prefetchLines);
        return $this;
    }

    /**
     * Set DiskANN query params.
     *
     * Only valid on a DISKANN index (see {@see ZVecIndexParams::forDiskAnn()}).
     *
     * @param int $listSize Search frontier size — larger trades latency for recall
     *
     * @throws ZVecException
     */
    public function setDiskAnnParams(int $listSize): self
    {
        $this->queryParamType = ZVec::QUERY_PARAM_DISKANN;
        $this->diskAnnListSize = $listSize;
        self::ffi()->zvec_vector_query_set_diskann_list_size($this->handle, $listSize);
        return $this;
    }

    /** @throws ZVecException */
    public function setHnswRabitqParams(int $ef): self
    {
        $this->queryParamType = ZVec::QUERY_PARAM_HNSW_RABITQ;
        $this->hnswEf = $ef;
        self::ffi()->zvec_vector_query_set_hnsw_rabitq_ef($this->handle, $ef);
        return $this;
    }

    /** @throws ZVecException */
    public function setIvfParams(int $nprobe): self
    {
        $this->queryParamType = ZVec::QUERY_PARAM_IVF;
        $this->ivfNprobe = $nprobe;
        self::ffi()->zvec_vector_query_set_ivf_nprobe($this->handle, $nprobe);
        return $this;
    }

    /** @throws ZVecException */
    public function setFlatParams(): self
    {
        $this->queryParamType = ZVec::QUERY_PARAM_FLAT;
        self::ffi()->zvec_vector_query_set_flat_mode($this->handle);
        return $this;
    }

    /**
     * Run a full-text search against a FTS-indexed STRING field.
     * Set DiskANN query params.
     *
     * Replaces the vector clause of this query, so it must not be combined with
     * a dense query vector.
     * Only valid on a DISKANN index (see {@see ZVecIndexParams::forDiskAnn()}).
     *
     * Upstream accepts exactly one of the two sides, so pass either
     * $queryString or $matchString, not both. $defaultOperator controls how
     * adjacent bare terms combine.
     *
     * @param string $fieldName       Field carrying the FTS index
     * @param string $queryString     Lucene-style query terms
     * @param string $matchString     Alternative natural-language terms
     * @param string $defaultOperator ZVec::FTS_OPERATOR_OR (default) or FTS_OPERATOR_AND
     *
     * @throws ZVecException
     */
    public function setFts(string $fieldName, string $queryString = '', string $matchString = '', string $defaultOperator = ZVec::FTS_OPERATOR_OR): self
    {
        if ($fieldName === '') {
            throw new ZVecException('fieldName must be a non-empty string');
        }
        if (($queryString === '') === ($matchString === '')) {
            throw new ZVecException('provide exactly one of queryString or matchString, not both and not neither');
        }
        $operator = strtoupper($defaultOperator);
        if (!in_array($operator, [ZVec::FTS_OPERATOR_OR, ZVec::FTS_OPERATOR_AND], true)) {
            throw new ZVecException("defaultOperator must be 'OR' or 'AND', got: {$defaultOperator}");
        }
        $this->queryParamType = ZVec::QUERY_PARAM_FTS;
        $this->ftsField = $fieldName;
        $this->ftsQueryString = $queryString;
        $this->ftsMatchString = $matchString;
        $this->ftsDefaultOperator = $operator;
        self::ffi()->zvec_vector_query_set_fts($this->handle, $fieldName, $queryString, $matchString, $operator);
        return $this;
    }

    /** @throws ZVecException */
    public function setVamanaParams(int $efSearch): self
    {
        $this->queryParamType = ZVec::QUERY_PARAM_VAMANA;
        $this->hnswEf = $efSearch;
        self::ffi()->zvec_vector_query_set_vamana_ef_search($this->handle, $efSearch);
        return $this;
    }

    /** @throws ZVecException */
    public function setRadius(float $radius): self
    {
        $this->radius = $radius;
        self::ffi()->zvec_vector_query_set_radius($this->handle, $radius);
        return $this;
    }

    /** @throws ZVecException */
    public function setLinear(bool $linear): self
    {
        $this->isLinear = $linear;
        self::ffi()->zvec_vector_query_set_is_linear($this->handle, $linear ? 1 : 0);
        return $this;
    }

    /** @throws ZVecException */
    public function setUsingRefiner(bool $refiner): self
    {
        $this->isUsingRefiner = $refiner;
        self::ffi()->zvec_vector_query_set_using_refiner($this->handle, $refiner ? 1 : 0);
        return $this;
    }

    /** @throws ZVecException */
    public function setTopk(int $topk): self
    {
        $this->topk = $topk;
        self::ffi()->zvec_vector_query_set_topk($this->handle, $topk);
        return $this;
    }

    /** @throws ZVecException */
    public function setIncludeVector(bool $include): self
    {
        $this->includeVector = $include;
        self::ffi()->zvec_vector_query_set_include_vector($this->handle, $include ? 1 : 0);
        return $this;
    }

    /** @throws ZVecException */
    public function setFilter(string $filter): self
    {
        $this->filter = $filter;
        self::ffi()->zvec_vector_query_set_filter($this->handle, $filter);
        return $this;
    }

    /**
     * @param string[] $fields

     * @throws ZVecException
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
