<?php

declare(strict_types=1);

namespace CrazyGoat\ZVec;

use FFI;

if (extension_loaded('zvec')) return;

/**
 * Structured collection statistics as an object.
 *
 * Wraps the FFI stats struct with typed getters for document count,
 * index count, and per-index completeness. Returned by getStatsStruct().
 * The underlying CData handle is freed on destruction.
 *
 * @see ZVec::getStatsStruct()
 */
class ZVecCollectionStats
{
    private FFI\CData $handle;

    public function __construct(FFI\CData $handle)
    {
        $this->handle = $handle;
    }

    public function __destruct()
    {
        self::ffi()->zvec_collection_stats_free($this->handle);
    }

    private function __clone()
    {
    }

    /** @throws ZVecException */
    public function getDocCount(): int
    {
        return self::ffi()->zvec_collection_stats_get_doc_count($this->handle);
    }

    /** @throws ZVecException */
    public function getIndexCount(): int
    {
        return self::ffi()->zvec_collection_stats_get_index_count($this->handle);
    }

    /** @throws ZVecException */
    public function getIndexName(int $index): string
    {
        $ptr = self::ffi()->zvec_collection_stats_get_index_name($this->handle, $index);
        if ($ptr === null) {
            throw new ZVecException("Index out of range: $index");
        }
        return is_string($ptr) ? $ptr : FFI::string($ptr);
    }

    /** @throws ZVecException */
    public function getIndexCompleteness(int $index): float
    {
        return self::ffi()->zvec_collection_stats_get_index_completeness($this->handle, $index);
    }

    /**
     * @return array<string, float>
     */
    public function getAllIndexCompleteness(): array
    {
        $count = $this->getIndexCount();
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $name = $this->getIndexName($i);
            $result[$name] = $this->getIndexCompleteness($i);
        }
        return $result;
    }

    /**
     * @return array{doc_count: int, index_completeness: array<string, float>}
     */
    public function toArray(): array
    {
        return [
            'doc_count' => $this->getDocCount(),
            'index_completeness' => $this->getAllIndexCompleteness(),
        ];
    }

    private static function ffi(): FFI
    {
        return ZVec::ffi();
    }
}
