<?php

declare(strict_types=1);

namespace CrazyGoat\ZVec;

use FFI;

if (extension_loaded('zvec')) return;

/**
 * Index creation parameter builder.
 *
 * Replaces the deprecated createHnswIndex(), createFlatIndex(),
 * createIvfIndex(), and createHnswRabitqIndex() methods with a
 * unified, type-safe API. Use factory methods: forHnsw(), forFlat(),
 * forIvf(), forVamana(), forHnswRabitq(), or forInvert().
 * The underlying CData handle is freed on destruction.
 *
 * @see ZVec::createIndex()
 */
class ZVecIndexParams
{
    private FFI\CData $handle;

    private function __construct(FFI\CData $handle)
    {
        $this->handle = $handle;
    }

    public function __destruct()
    {
        self::ffi()->zvec_index_params_free($this->handle);
    }

    private function __clone()
    {
    }

    public function getHandle(): FFI\CData
    {
        return $this->handle;
    }

    /**
     * Create HNSW index params
     *
     * @throws ZVecException On FFI error
     */
    public static function forHnsw(int $metricType, int $m = ZVec::DEFAULT_HNSW_M, int $efConstruction = ZVec::DEFAULT_HNSW_EF_CONSTRUCTION, int $quantizeType = ZVec::QUANTIZE_UNDEFINED, bool $useContiguousMemory = false): self
    {
        if ($m <= 0) {
            throw new ZVecException("m must be a positive integer, got: {$m}");
        }
        if ($efConstruction <= 0) {
            throw new ZVecException("efConstruction must be a positive integer, got: {$efConstruction}");
        }
        $ffi = self::ffi();
        $handle = $ffi->zvec_index_params_create(ZVec::INDEX_TYPE_HNSW, $metricType);
        $ffi->zvec_index_params_set_hnsw($handle, $m, $efConstruction, $quantizeType, $useContiguousMemory ? 1 : 0);
        return new self($handle);
    }

    /**
     * Create HNSW with RaBitQ index params
     *
     * @throws ZVecException On FFI error
     */
    public static function forHnswRabitq(int $metricType, int $totalBits = 7, int $numClusters = 16, int $m = ZVec::DEFAULT_HNSW_M, int $efConstruction = ZVec::DEFAULT_HNSW_EF_CONSTRUCTION, int $sampleCount = 0): self
    {
        if ($m <= 0) {
            throw new ZVecException("m must be a positive integer, got: {$m}");
        }
        if ($efConstruction <= 0) {
            throw new ZVecException("efConstruction must be a positive integer, got: {$efConstruction}");
        }
        $ffi = self::ffi();
        $handle = $ffi->zvec_index_params_create(ZVec::INDEX_TYPE_HNSW_RABITQ, $metricType);
        $ffi->zvec_index_params_set_hnsw_rabitq($handle, $totalBits, $numClusters, $m, $efConstruction, $sampleCount);
        return new self($handle);
    }

    /**
     * Create Flat index params
     *
     * @throws ZVecException On FFI error
     */
    public static function forFlat(int $metricType, int $quantizeType = ZVec::QUANTIZE_UNDEFINED): self
    {
        $ffi = self::ffi();
        $handle = $ffi->zvec_index_params_create(ZVec::INDEX_TYPE_FLAT, $metricType);
        $ffi->zvec_index_params_set_flat($handle, $quantizeType);
        return new self($handle);
    }

    /**
     * Create IVF index params
     *
     * @throws ZVecException On FFI error
     */
    public static function forIvf(int $metricType, int $nList = 1024, int $nIters = 10, bool $useSoar = false, int $quantizeType = ZVec::QUANTIZE_UNDEFINED): self
    {
        if ($nList <= 0) {
            throw new ZVecException("nList must be a positive integer, got: {$nList}");
        }
        if ($nIters <= 0) {
            throw new ZVecException("nIters must be a positive integer, got: {$nIters}");
        }
        $ffi = self::ffi();
        $handle = $ffi->zvec_index_params_create(ZVec::INDEX_TYPE_IVF, $metricType);
        $ffi->zvec_index_params_set_ivf($handle, $nList, $nIters, $useSoar ? 1 : 0, $quantizeType);
        return new self($handle);
    }

    /**
     * Create Vamana index params
     *
     * @throws ZVecException On FFI error
     */
    public static function forVamana(int $metricType, int $maxDegree = 64, int $searchListSize = 100, float $alpha = 1.2, bool $saturateGraph = false, bool $useContiguousMemory = false, bool $useIdMap = false, int $quantizeType = ZVec::QUANTIZE_UNDEFINED): self
    {
        if ($maxDegree <= 0) {
            throw new ZVecException("maxDegree must be a positive integer, got: {$maxDegree}");
        }
        if ($searchListSize <= 0) {
            throw new ZVecException("searchListSize must be a positive integer, got: {$searchListSize}");
        }
        $ffi = self::ffi();
        $handle = $ffi->zvec_index_params_create(ZVec::INDEX_TYPE_VAMANA, $metricType);
        $ffi->zvec_index_params_set_vamana($handle, $maxDegree, $searchListSize, $alpha, $saturateGraph ? 1 : 0, $useContiguousMemory ? 1 : 0, $useIdMap ? 1 : 0, $quantizeType);
        return new self($handle);
    }

    /**
     * Create DiskANN index params
     *
     * Disk-based graph index for billion-scale corpora, distinct from the
     * in-memory Vamana graph. Mirrors the official Go SDK signature
     * NewDiskANNIndexParams(metric, maxDegree, listSize, pqChunkNum).
     *
     * @param int $maxDegree    Maximum out-degree of the graph
     * @param int $listSize     Search list size used at build time
     * @param int $pqChunkNum   PQ chunks for on-disk compression, 0 disables PQ
     * @param int $quantizeType Vector quantization type
     *
     * @throws ZVecException On FFI error
     */
    public static function forDiskAnn(int $metricType, int $maxDegree = 100, int $listSize = 50, int $pqChunkNum = 0, int $quantizeType = ZVec::QUANTIZE_UNDEFINED): self
    {
        if ($maxDegree <= 0) {
            throw new ZVecException("maxDegree must be a positive integer, got: {$maxDegree}");
        }
        if ($listSize <= 0) {
            throw new ZVecException("listSize must be a positive integer, got: {$listSize}");
        }
        if ($pqChunkNum < 0) {
            throw new ZVecException("pqChunkNum must be >= 0, got: {$pqChunkNum}");
        }
        $ffi = self::ffi();
        $handle = $ffi->zvec_index_params_create(ZVec::INDEX_TYPE_DISKANN, $metricType);
        $ffi->zvec_index_params_set_diskann($handle, $maxDegree, $listSize, $pqChunkNum);
        if ($quantizeType !== ZVec::QUANTIZE_UNDEFINED) {
            $ffi->zvec_index_params_set_quantize_type($handle, $quantizeType);
        }
        return new self($handle);
    }

    /**
     * Create Full-Text Search index params
     *
     * Inverted index over a STRING column. Mirrors the official Go SDK
     * NewFTSIndexParams(tokenizerName, filters, extraParams).
     *
     * @param string   $tokenizer   "standard", "ngram", "jieba" or "whitespace"
     * @param string[] $filters     Any of "lowercase", "ascii_folding", "stemmer"
     * @param string   $extraParams JSON object, e.g. '{"stemmer_lang":"english"}',
     *                              '{"ngram_min":2,"ngram_max":3}', '{"cut_mode":"mix"}'
     *
     * @throws ZVecException On FFI error
     */
    public static function forFts(string $tokenizer = 'standard', array $filters = ['lowercase'], string $extraParams = ''): self
    {
        if ($tokenizer === '') {
            throw new ZVecException('tokenizer must be a non-empty string');
        }
        foreach ($filters as $filter) {
            if (!is_string($filter) || $filter === '') {
                throw new ZVecException('each FTS filter must be a non-empty string');
            }
        }
        $ffi = self::ffi();
        $handle = $ffi->zvec_index_params_create(ZVec::INDEX_TYPE_FTS, ZVecSchema::METRIC_IP);

        [$arr, $count, $cStrings] = ZVec::toCStringArray($ffi, $filters);
        try {
            $ffi->zvec_index_params_set_fts($handle, $tokenizer, $arr, $count, $extraParams);
        } finally {
            ZVec::freeCStringArray($cStrings);
        }
        return new self($handle);
    }

    public static function forInvert(bool $enableRange = true, bool $enableWildcard = false): self
    {
        $ffi = self::ffi();
        $handle = $ffi->zvec_index_params_create(ZVec::INDEX_TYPE_INVERT, ZVecSchema::METRIC_IP);
        $ffi->zvec_index_params_set_invert($handle, $enableRange ? 1 : 0, $enableWildcard ? 1 : 0);
        return new self($handle);
    }

    /**
     * Enable/disable random rotation before INT8/INT4 quantization.
     *
     * Only effective with QUANTIZE_INT8 or QUANTIZE_INT4 quantize types.
     * When enabled, vectors are randomly rotated before quantization to
     * reduce quantization error (improves recall for quantized indexes).
     *
     * @throws ZVecException On FFI error
     */
    public function setQuantizerEnableRotate(bool $enableRotate): self
    {
        self::ffi()->zvec_index_params_set_quantizer_enable_rotate($this->handle, $enableRotate ? 1 : 0);
        return $this;
    }

    private static function ffi(): FFI
    {
        return ZVec::ffi();
    }
}
