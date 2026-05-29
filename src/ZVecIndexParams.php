<?php

declare(strict_types=1);

if (extension_loaded('zvec')) return;

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

    public static function forHnsw(int $metricType, int $m = 50, int $efConstruction = 500, int $quantizeType = ZVec::QUANTIZE_UNDEFINED, bool $useContiguousMemory = false): self
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

    public static function forHnswRabitq(int $metricType, int $totalBits = 7, int $numClusters = 16, int $m = 50, int $efConstruction = 500, int $sampleCount = 0): self
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

    public static function forFlat(int $metricType, int $quantizeType = ZVec::QUANTIZE_UNDEFINED): self
    {
        $ffi = self::ffi();
        $handle = $ffi->zvec_index_params_create(ZVec::INDEX_TYPE_FLAT, $metricType);
        $ffi->zvec_index_params_set_flat($handle, $quantizeType);
        return new self($handle);
    }

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

    public static function forInvert(bool $enableRange = true, bool $enableWildcard = false): self
    {
        $ffi = self::ffi();
        $handle = $ffi->zvec_index_params_create(ZVec::INDEX_TYPE_INVERT, ZVecSchema::METRIC_IP);
        $ffi->zvec_index_params_set_invert($handle, $enableRange ? 1 : 0, $enableWildcard ? 1 : 0);
        return new self($handle);
    }

    private static function ffi(): FFI
    {
        return (new ReflectionClass(ZVec::class))->getMethod('ffi')->invoke(null);
    }
}
