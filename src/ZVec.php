<?php

declare(strict_types=1);

namespace CrazyGoat\ZVec;

use FFI;

if (extension_loaded('zvec')) return;

require_once __DIR__ . '/ZVecException.php';
require_once __DIR__ . '/ZVecCollectionOptions.php';
require_once __DIR__ . '/ZVecCollectionStats.php';
require_once __DIR__ . '/ZVecFieldSchema.php';
require_once __DIR__ . '/ZVecIndexParams.php';
require_once __DIR__ . '/ZVecQueryInterface.php';
require_once __DIR__ . '/ZVecVectorQuery.php';
require_once __DIR__ . '/ZVecGroupByVectorQuery.php';
require_once __DIR__ . '/ZVecSchema.php';
require_once __DIR__ . '/ZVecDoc.php';
require_once __DIR__ . '/ZVecReRanker.php';
require_once __DIR__ . '/ZVecRerankedDoc.php';
require_once __DIR__ . '/ZVecRrfReRanker.php';
require_once __DIR__ . '/ZVecWeightedReRanker.php';

class ZVec
{
    private static ?FFI $ffi = null;
    private static ?string $allowedBasePath = null;
    private static bool $verboseErrors = false;
    private FFI\CData $handle;
    private bool $closed = false;
    private bool $destroyed = false;
    private string $path = '';

    public static function getFFI(): ?FFI
    {
        return self::$ffi;
    }

    /** @internal */
    public static function ffi(): FFI
    {
        if (self::$ffi === null) {
            $ext = PHP_OS_FAMILY === 'Darwin' ? 'dylib' : 'so';
            $candidates = [
                __DIR__ . "/../lib/libzvec_ffi.$ext",
                __DIR__ . "/../ffi/build/libzvec_ffi.$ext",
            ];

            $libPath = null;
            foreach ($candidates as $candidate) {
                if (file_exists($candidate)) {
                    $libPath = $candidate;
                    break;
                }
            }

            if ($libPath === null) {
                throw new ZVecException("Library not found. Run 'composer install' or 'build_zvec.sh'.");
            }

            $headerPath = __DIR__ . '/../ffi/zvec_ffi_php.h';
            if (!file_exists($headerPath)) {
                throw new ZVecException("FFI header not found at {$headerPath}");
            }

            $headerContent = file_get_contents($headerPath);
            $headerContent = preg_replace('/#.*$/m', '', $headerContent);
            $headerContent = preg_replace('/\/\/.*$/m', '', $headerContent);
            $headerContent = preg_replace('/\/\*.*?\*\//s', '', $headerContent);
            $headerContent = preg_replace('/\n{3,}/', "\n\n", $headerContent);

            self::$ffi = FFI::cdef(trim($headerContent), $libPath);
        }
        return self::$ffi;
    }

    public static function checkStatus(FFI\CData $status): void
    {
        if ($status->code !== 0) {
            $ffi = self::ffi();
            $details = $ffi->new('zvec_error_details_t');
            $ffi->zvec_get_last_error_details(FFI::addr($details));
            $msg = $details->message !== null ? FFI::string($details->message) : FFI::string($status->message);

            if (self::$verboseErrors) {
                $file = $details->file !== null ? FFI::string($details->file) : null;
                $line = $details->line;
                $function = $details->function !== null ? FFI::string($details->function) : null;
                throw new ZVecException($msg, $status->code, null, $file, $line, $function);
            }

            throw new ZVecException($msg, $status->code);
        }
    }

    /**
     * @internal
     * @param string[] $strings
     * @return array{FFI\CData, int, FFI\CData[]}
     */
    public static function toCStringArray(FFI $ffi, array $strings): array
    {
        $cStrings = [];
        $count = count($strings);
        $arr = $ffi->new("char*[$count]", false);
        foreach ($strings as $i => $s) {
            $len = strlen($s) + 1;
            $cStr = $ffi->new("char[$len]", false);
            FFI::memcpy($cStr, $s, strlen($s));
            $cStr[$len - 1] = "\0";
            $cStrings[] = $cStr;
            $arr[$i] = $cStr;
        }
        return [$arr, $count, $cStrings];
    }

    /** @internal */
    public static function freeCStringArray(array $cStrings): void
    {
        foreach ($cStrings as $cStr) {
            FFI::free($cStr);
        }
    }

    private function checkClosed(): void
    {
        if ($this->destroyed) {
            throw new ZVecException("Collection has been destroyed and cannot be reused");
        }
        if ($this->closed) {
            throw new ZVecException("Collection is closed. Open with ZVec::open() to continue.");
        }
    }

    /**
     * Validate and resolve a collection path, preventing directory traversal attacks.
     *
     * When $allowedBasePath is set, the resolved path must fall within that directory.
     * When $allowedBasePath is null (default), the path must be an absolute path or
     * resolve to an absolute path without ".." components that escape the parent directory.
     */
    private static function validateCollectionPath(string $path): string
    {
        if ($path === '') {
            throw new ZVecException('Path must not be empty');
        }

        $normalized = str_replace('\\', '/', $path);
        $parts = explode('/', $normalized);
        $hasDriveLetter = PHP_OS_FAMILY === 'Windows' && preg_match('/^[A-Za-z]:/', $normalized);

        if (self::$allowedBasePath === null) {
            $base = $hasDriveLetter ? $parts[0] . '/' : '/';
            $resolved = $base;
            foreach ($parts as $part) {
                if ($part === '' || $part === '.') {
                    continue;
                }
                if ($part === '..') {
                    $resolved = rtrim($resolved, '/');
                    $resolved = dirname($resolved);
                    if ($resolved === '.') {
                        $resolved = '/';
                    }
                    $resolved = rtrim($resolved, '/') . '/';
                    continue;
                }
                $resolved = rtrim($resolved, '/') . '/' . $part;
            }

            $resolved = rtrim($resolved, '/');
            if ($resolved === '') {
                throw new ZVecException("Invalid collection path: {$path}");
            }

            return $resolved;
        }

        $allowed = realpath(self::$allowedBasePath);
        if ($allowed === false) {
            throw new ZVecException("Allowed base path does not exist: " . self::$allowedBasePath);
        }

        $resolvedDir = realpath(dirname($path));
        $basename = basename($path);

        if ($basename === '' || $basename === '.' || $basename === '..') {
            throw new ZVecException("Invalid collection path: {$path}");
        }

        if ($resolvedDir !== false) {
            $candidate = rtrim($resolvedDir, '/') . '/' . $basename;
            if (!str_starts_with($candidate, rtrim($allowed, '/') . '/') && $candidate !== $allowed) {
                throw new ZVecException(
                    "Collection path not allowed: {$path} (must be within {$allowed})"
                );
            }
            return $candidate;
        }

        $parent = dirname($path);
        if ($parent === '' || $parent === '.') {
            throw new ZVecException("Invalid collection path: {$path}");
        }

        $resolvedParent = realpath($parent);
        if ($resolvedParent === false) {
            throw new ZVecException("Parent directory does not exist: {$parent}");
        }

        $candidate = $resolvedParent . '/' . $basename;
        if (!str_starts_with($candidate, rtrim($allowed, '/') . '/') && $candidate !== $allowed) {
            throw new ZVecException(
                "Collection path not allowed: {$path} (must be within {$allowed})"
            );
        }

        return $candidate;
    }

    /**
     * Parse FFI query result into ZVecDoc array and free the result.
     *
     * @return ZVecDoc[]
     */
    private static function parseQueryResult(FFI\CData $result): array
    {
        $ffi = self::ffi();
        $docs = [];
        for ($i = 0; $i < $result->count; $i++) {
            $docs[] = new ZVecDoc($result->docs[$i], true);
        }
        $ffi->zvec_query_result_free_array(FFI::addr($result));
        return $docs;
    }

    /**
     * Parse FFI group query result into indexed array and free the result.
     *
     * @return array<int, array{group_value: string, docs: ZVecDoc[]}>
     */
    private static function parseGroupResult(FFI\CData $result): array
    {
        $ffi = self::ffi();
        $groups = [];
        for ($i = 0; $i < $result->count; $i++) {
            $group = $result->groups[$i];
            $gv = $group->group_by_value;
            $groupValue = is_string($gv) ? $gv : FFI::string($gv);
            $docs = [];
            for ($j = 0; $j < $group->count; $j++) {
                $docs[] = new ZVecDoc($group->docs[$j], true);
            }
            $groups[] = ['group_value' => $groupValue, 'docs' => $docs];
        }
        $ffi->zvec_group_results_free(FFI::addr($result));
        return $groups;
    }

    public static function create(string $path, ZVecSchema $schema, bool $readOnly = false, bool $enableMmap = true, int $maxBufferSize = self::DEFAULT_MAX_BUFFER_SIZE): self
    {
        $path = self::validateCollectionPath($path);
        $ffi = self::ffi();
        $out = $ffi->new('zvec_collection_t');
        $status = $ffi->zvec_collection_create($path, $schema->getHandle(), $readOnly ? 1 : 0, $enableMmap ? 1 : 0, $maxBufferSize, FFI::addr($out));
        self::checkStatus($status);
        return new self($out, $path);
    }

    public static function open(string $path, bool $readOnly = false, bool $enableMmap = true, int $maxBufferSize = self::DEFAULT_MAX_BUFFER_SIZE): self
    {
        $path = self::validateCollectionPath($path);
        $ffi = self::ffi();
        $out = $ffi->new('zvec_collection_t');
        $status = $ffi->zvec_collection_open($path, $readOnly ? 1 : 0, $enableMmap ? 1 : 0, $maxBufferSize, FFI::addr($out));
        self::checkStatus($status);
        return new self($out, $path);
    }

    public static function createWith(string $path, ZVecSchema $schema, ZVecCollectionOptions $options): self
    {
        return self::create($path, $schema, $options->readOnly, $options->enableMmap, $options->maxBufferSize);
    }

    public static function openWith(string $path, ZVecCollectionOptions $options): self
    {
        return self::open($path, $options->readOnly, $options->enableMmap, $options->maxBufferSize);
    }

    public function getOptions(): ZVecCollectionOptions
    {
        $arr = $this->options();
        return new ZVecCollectionOptions(
            readOnly: $arr['read_only'],
            enableMmap: $arr['enable_mmap'],
            maxBufferSize: $arr['max_buffer_size']
        );
    }

    private function __construct(FFI\CData $handle, string $path)
    {
        $this->handle = $handle;
        $this->path = $path;
    }

    public function __destruct()
    {
        $this->close();
    }

    private function __clone()
    {
    }

    public function close(): void
    {
        if ($this->closed || $this->destroyed) {
            return;
        }
        self::ffi()->zvec_collection_free($this->handle);
        $this->closed = true;
    }

    public function flush(): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_flush($this->handle));
    }

    public function optimize(int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_optimize($this->handle, $concurrency));
    }

    public function destroy(): void
    {
        if ($this->destroyed) {
            return;
        }

        if ($this->closed) {
            // Re-open via stored path so we can destroy the on-disk data
            $ffi = self::ffi();
            $newHandle = $ffi->new('zvec_collection_t');
            $status = $ffi->zvec_collection_open($this->path, 0, 1, self::DEFAULT_MAX_BUFFER_SIZE, FFI::addr($newHandle));
            self::checkStatus($status);
            try {
                self::checkStatus($ffi->zvec_collection_destroy($newHandle));
            } catch (\Throwable $e) {
                // Free the reopened handle to prevent orphaned C++ object in g_collections
                $ffi->zvec_collection_free($newHandle);
                throw $e;
            }
        } else {
            self::checkStatus(self::ffi()->zvec_collection_destroy($this->handle));
            self::ffi()->zvec_collection_free($this->handle);
            $this->closed = true;
        }

        $this->destroyed = true;
    }

    public function schema(): string
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $bufSize = self::SCHEMA_BUFFER_SIZE;
        while (true) {
            $buf = $ffi->new("char[$bufSize]");
            self::checkStatus($ffi->zvec_collection_schema($this->handle, $buf, $bufSize));
            $str = FFI::string($buf);
            if (strlen($str) < $bufSize - 1) {
                return $str;
            }
            $bufSize *= 2;
            if ($bufSize > self::MAX_STRING_BUFFER_SIZE) {
                throw new ZVecException('Schema string exceeds maximum buffer size of 1 MB');
            }
        }
    }

    public function path(): string
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $bufSize = self::PATH_BUFFER_SIZE;
        while (true) {
            $buf = $ffi->new("char[$bufSize]");
            self::checkStatus($ffi->zvec_collection_path($this->handle, $buf, $bufSize));
            $str = FFI::string($buf);
            if (strlen($str) < $bufSize - 1) {
                return $str;
            }
            $bufSize *= 2;
            if ($bufSize > self::MAX_STRING_BUFFER_SIZE) {
                throw new ZVecException('Path string exceeds maximum buffer size of 1 MB');
            }
        }
    }

    /**
     * @return array{read_only: bool, enable_mmap: bool, max_buffer_size: int}
     */
    public function options(): array
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $readOnly = $ffi->new('int');
        $enableMmap = $ffi->new('int');
        $maxBufferSize = $ffi->new('uint32_t');
        self::checkStatus($ffi->zvec_collection_options($this->handle, FFI::addr($readOnly), FFI::addr($enableMmap), FFI::addr($maxBufferSize)));
        return [
            'read_only' => $readOnly->cdata !== 0,
            'enable_mmap' => $enableMmap->cdata !== 0,
            'max_buffer_size' => (int)$maxBufferSize->cdata,
        ];
    }

    public function addColumnInt64(string $name, bool $nullable = true, string $defaultExpr = '0', int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_add_column_int64($this->handle, $name, $nullable ? 1 : 0, $defaultExpr, $concurrency));
    }

    public function addColumnFloat(string $name, bool $nullable = true, string $defaultExpr = '0', int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_add_column_float($this->handle, $name, $nullable ? 1 : 0, $defaultExpr, $concurrency));
    }

    public function addColumnDouble(string $name, bool $nullable = true, string $defaultExpr = '0', int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_add_column_double($this->handle, $name, $nullable ? 1 : 0, $defaultExpr, $concurrency));
    }

    public function addColumnString(string $name, bool $nullable = true, string $defaultExpr = '', int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_add_column_string($this->handle, $name, $nullable ? 1 : 0, $defaultExpr, $concurrency));
    }

    public function addColumnBool(string $name, bool $nullable = true, string $defaultExpr = 'false', int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_add_column_bool($this->handle, $name, $nullable ? 1 : 0, $defaultExpr, $concurrency));
    }

    public function addColumnInt32(string $name, bool $nullable = true, string $defaultExpr = '0', int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_add_column_int32($this->handle, $name, $nullable ? 1 : 0, $defaultExpr, $concurrency));
    }

    public function addColumnUint32(string $name, bool $nullable = true, string $defaultExpr = '0', int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_add_column_uint32($this->handle, $name, $nullable ? 1 : 0, $defaultExpr, $concurrency));
    }

    public function addColumnUint64(string $name, bool $nullable = true, string $defaultExpr = '0', int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_add_column_uint64($this->handle, $name, $nullable ? 1 : 0, $defaultExpr, $concurrency));
    }

    public function dropColumn(string $name): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_drop_column($this->handle, $name));
    }

    public function renameColumn(string $oldName, string $newName, int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_rename_column($this->handle, $oldName, $newName, $concurrency));
    }

    public function alterColumn(string $columnName, ?string $newName = null, ?int $newDataType = null, ?bool $nullable = null, int $concurrency = 0): void
    {
        $this->checkClosed();

        if ($newDataType !== null && $nullable === null) {
            throw new ZVecException(
                'nullable must be explicitly specified when changing data type. '
                . 'Use alterColumn("name", newDataType: TYPE_FLOAT, nullable: true|false).'
            );
        }

        // Data type constants for alter column (scalar numeric only)
        // INT32 = 4, INT64 = 5, UINT32 = 6, UINT64 = 7, FLOAT = 8, DOUBLE = 9
        $dataType = $newDataType ?? 0; // 0 = UNDEFINED (no type change)
        $isNullable = $nullable === null ? 0 : ($nullable ? 1 : 0);
        $rename = $newName ?? '';
        
        self::checkStatus(self::ffi()->zvec_collection_alter_column($this->handle, $columnName, $rename, $dataType, $isNullable, $concurrency));
    }

    public function dropIndex(string $fieldName): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_drop_index($this->handle, $fieldName));
    }

    /**
     * Create an index using the unified IndexParams API.
     */
    public function createIndex(string $fieldName, ZVecIndexParams $params, int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_create_index($this->handle, $fieldName, $params->getHandle(), $concurrency));
    }

    /** @deprecated Use createIndex() with ZVecIndexParams::forInvert() instead */
    public function createInvertIndex(string $fieldName, bool $enableRange = true, bool $enableWildcard = false): void
    {
        $this->createIndex($fieldName, ZVecIndexParams::forInvert($enableRange, $enableWildcard));
    }

    /** @deprecated Use createIndex() with ZVecIndexParams::forHnsw() instead */
    public function createHnswIndex(string $fieldName, int $metricType = ZVecSchema::METRIC_IP, int $m = self::DEFAULT_HNSW_M, int $efConstruction = self::DEFAULT_HNSW_EF_CONSTRUCTION, int $quantizeType = 0, int $concurrency = 0, bool $useContiguousMemory = false): void
    {
        $this->createIndex($fieldName, ZVecIndexParams::forHnsw($metricType, $m, $efConstruction, $quantizeType, $useContiguousMemory), $concurrency);
    }

    /** @deprecated Use createIndex() with ZVecIndexParams::forHnswRabitq() instead */
    public function createHnswRabitqIndex(string $fieldName, int $metricType = ZVecSchema::METRIC_IP, int $totalBits = 7, int $numClusters = 16, int $m = self::DEFAULT_HNSW_M, int $efConstruction = self::DEFAULT_HNSW_EF_CONSTRUCTION, int $sampleCount = 0, int $concurrency = 0): void
    {
        $this->createIndex($fieldName, ZVecIndexParams::forHnswRabitq($metricType, $totalBits, $numClusters, $m, $efConstruction, $sampleCount), $concurrency);
    }

    /** @deprecated Use createIndex() with ZVecIndexParams::forFlat() instead */
    public function createFlatIndex(string $fieldName, int $metricType = ZVecSchema::METRIC_IP, int $quantizeType = 0, int $concurrency = 0): void
    {
        $this->createIndex($fieldName, ZVecIndexParams::forFlat($metricType, $quantizeType), $concurrency);
    }

    /** @deprecated Use createIndex() with ZVecIndexParams::forIvf() instead */
    public function createIvfIndex(string $fieldName, int $metricType = ZVecSchema::METRIC_IP, int $nList = 1024, int $nIters = 10, bool $useSoar = false, int $quantizeType = 0, int $concurrency = 0): void
    {
        $this->createIndex($fieldName, ZVecIndexParams::forIvf($metricType, $nList, $nIters, $useSoar, $quantizeType), $concurrency);
    }

    private function writeDocs(string $operation, ZVecDoc ...$docs): void
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $count = count($docs);
        $arr = $ffi->new("zvec_doc_t[$count]");
        foreach ($docs as $i => $doc) {
            $arr[$i] = $doc->getHandle();
        }
        self::checkStatus($ffi->{"zvec_collection_{$operation}"}($this->handle, $arr, $count));
    }

    public function insert(ZVecDoc ...$docs): void { $this->writeDocs('insert', ...$docs); }

    public function upsert(ZVecDoc ...$docs): void { $this->writeDocs('upsert', ...$docs); }

    public function update(ZVecDoc ...$docs): void { $this->writeDocs('update', ...$docs); }

    /**
     * @return array<int, array{pk: string, ok: bool, error: string|null}>
     */
    private function writeDocsBatch(string $operation, ZVecDoc ...$docs): array
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $count = count($docs);
        $arr = $ffi->new("zvec_doc_t[$count]");
        foreach ($docs as $i => $doc) {
            $arr[$i] = $doc->getHandle();
        }

        $result = $ffi->new('zvec_batch_result_t');
        self::checkStatus($ffi->{"zvec_collection_{$operation}_batch"}($this->handle, $arr, $count, FFI::addr($result)));

        $results = [];
        for ($i = 0; $i < $result->count; $i++) {
            $results[] = [
                'pk' => FFI::string($result->doc_pks[$i]),
                'ok' => $result->codes[$i] === 0,
                'error' => $result->messages[$i] !== null ? FFI::string($result->messages[$i]) : null,
            ];
        }

        $ffi->zvec_batch_result_free(FFI::addr($result));
        return $results;
    }

    /**
     * @return array<int, array{pk: string, ok: bool, error: string|null}>
     */
    public function insertBatch(ZVecDoc ...$docs): array { return $this->writeDocsBatch('insert', ...$docs); }

    /**
     * @return array<int, array{pk: string, ok: bool, error: string|null}>
     */
    public function upsertBatch(ZVecDoc ...$docs): array { return $this->writeDocsBatch('upsert', ...$docs); }

    /**
     * @return array<int, array{pk: string, ok: bool, error: string|null}>
     */
    public function updateBatch(ZVecDoc ...$docs): array { return $this->writeDocsBatch('update', ...$docs); }

    public function delete(string ...$pks): void
    {
        $this->checkClosed();
        if (empty($pks)) {
            throw new ZVecException('At least one PK is required');
        }
        $ffi = self::ffi();
        [$arr, $count, $cStrings] = self::toCStringArray($ffi, $pks);
        try {
            self::checkStatus($ffi->zvec_collection_delete($this->handle, $arr, $count));
        } finally {
            self::freeCStringArray($cStrings);
        }
    }

    public function deleteByFilter(string $filter): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_delete_by_filter($this->handle, $filter));
    }

    /**
     * @return ZVecDoc[]
     */
    public function fetch(string ...$pks): array
    {
        $this->checkClosed();
        if (empty($pks)) {
            throw new ZVecException('At least one PK is required');
        }
        $ffi = self::ffi();
        [$arr, $count, $cStrings] = self::toCStringArray($ffi, $pks);

        $result = $ffi->new('zvec_query_result_t');
        try {
            $status = $ffi->zvec_collection_fetch($this->handle, $arr, $count, FFI::addr($result));
            self::checkStatus($status);
        } finally {
            self::freeCStringArray($cStrings);
        }

        return self::parseQueryResult($result);
    }

    // Index types for unified IndexParams API
    public const INDEX_TYPE_HNSW = 1;
    public const INDEX_TYPE_IVF = 2;
    public const INDEX_TYPE_FLAT = 3;
    public const INDEX_TYPE_HNSW_RABITQ = 4;
    public const INDEX_TYPE_VAMANA = 5;
    public const INDEX_TYPE_INVERT = 10;

    public const QUERY_PARAM_NONE = 0;
    public const QUERY_PARAM_HNSW = 1;
    public const QUERY_PARAM_IVF = 2;
    public const QUERY_PARAM_FLAT = 3;
    public const QUERY_PARAM_HNSW_RABITQ = 4;
    public const QUERY_PARAM_VAMANA = 5;

    public const LOG_CONSOLE = 0;
    public const LOG_FILE = 1;

    public const LOG_DEBUG = 0;
    public const LOG_INFO = 1;
    public const LOG_WARN = 2;
    public const LOG_ERROR = 3;
    public const LOG_FATAL = 4;

    // Default buffer sizes
    public const DEFAULT_MAX_BUFFER_SIZE = 67108864; // 64 MB
    public const SCHEMA_BUFFER_SIZE = 8192;
    public const PATH_BUFFER_SIZE = 4096;
    public const MAX_STRING_BUFFER_SIZE = 1048576; // 1 MB max for schema/stats strings
    public const BYTES_PER_MB = 1048576;

    // Default HNSW index parameters
    public const DEFAULT_HNSW_M = 50;
    public const DEFAULT_HNSW_EF_CONSTRUCTION = 500;

    // Data types for alterColumn (scalar numeric only)
    public const TYPE_INT32 = 4;
    public const TYPE_INT64 = 5;
    public const TYPE_UINT32 = 6;
    public const TYPE_UINT64 = 7;
    public const TYPE_FLOAT = 8;
    public const TYPE_DOUBLE = 9;
    public const TYPE_BOOL = 3;

    // Vector data types for schema
    public const TYPE_VECTOR_FP32 = 23;
    public const TYPE_VECTOR_FP64 = 24;
    public const TYPE_VECTOR_FP16 = 22;
    public const TYPE_VECTOR_INT4 = 25;
    public const TYPE_VECTOR_INT8 = 26;
    public const TYPE_VECTOR_INT16 = 27;
    public const TYPE_VECTOR_BINARY32 = 20;
    public const TYPE_VECTOR_BINARY64 = 21;
    public const TYPE_SPARSE_VECTOR_FP32 = 31;
    public const TYPE_SPARSE_VECTOR_FP16 = 30;
    public const TYPE_BINARY = 1;
    public const TYPE_ARRAY_STRING = 41;
    public const TYPE_ARRAY_BOOL = 42;
    public const TYPE_ARRAY_INT32 = 43;
    public const TYPE_ARRAY_INT64 = 44;
    public const TYPE_ARRAY_UINT32 = 45;
    public const TYPE_ARRAY_UINT64 = 46;
    public const TYPE_ARRAY_FLOAT = 47;
    public const TYPE_ARRAY_DOUBLE = 48;

    // Quantize types for index creation
    public const QUANTIZE_UNDEFINED = 0;
    public const QUANTIZE_FP16 = 1;
    public const QUANTIZE_INT8 = 2;
    public const QUANTIZE_INT4 = 3;
    public const QUANTIZE_RABITQ = 4;

    /**
     * Initialize the zvec library.
     *
     * Must be called once before any other ZVec operation.
     * Subsequent calls are no-ops (idempotent).
     *
     * @param int $logType Log destination. One of:
     *     LOG_CONSOLE (0) — stderr output
     *     LOG_FILE    (1) — file output (requires $logDir)
     * @param int $logLevel Log verbosity filter. One of:
     *     LOG_DEBUG (0) — all messages
     *     LOG_INFO  (1) — informational and above
     *     LOG_WARN  (2) — warnings and above (default)
     *     LOG_ERROR (3) — errors only
     *     LOG_FATAL (4) — fatal errors only
     * @param string|null $logDir Directory for log file output. Required when
     *     $logType is LOG_FILE. Directory must exist and be writable.
     * @param string|null $logBasename Log file name prefix (default: "zvec").
     *     Final name: {basename}.YYYY-MM-DD.N.log
     * @param int $logFileSize Maximum single log file size in bytes before
     *     rotation. 0 = unlimited rotation.
     * @param int $logOverdueDays Days after which old log files are auto-deleted.
     *     0 = no auto-deletion.
     * @param int $queryThreads Thread pool size for queries. 0 = auto-detect
     *     (uses hardware concurrency).
     * @param int $optimizeThreads Thread pool size for optimize operations.
     *     0 = auto-detect.
     * @param float $invertToForwardScanRatio Threshold controlling when the
     *     query planner switches from inverted index to forward scan.
     *     0.0 = use library default.
     * @param float $bruteForceByKeysRatio Threshold for brute-force vs indexed
     *     key lookup. 0.0 = use library default.
     * @param int $memoryLimitMb Memory limit for the collection cache in MB.
     *     0 = unlimited.
     * @param string|null $allowedBasePath Restrict database paths to this directory.
     *     All create/open calls will reject paths outside this tree. null = no restriction.
     * @param bool $verboseErrors When true, error messages include file and line information.
     * @throws ZVecException On FFI initialization failure (e.g., shared library
     *     not found, GPU init failure), or when $allowedBasePath does not exist.
     */
    public static function init(
        int $logType = self::LOG_CONSOLE,
        int $logLevel = self::LOG_WARN,
        ?string $logDir = null,
        ?string $logBasename = null,
        int $logFileSize = 0,
        int $logOverdueDays = 0,
        int $queryThreads = 0,
        int $optimizeThreads = 0,
        float $invertToForwardScanRatio = 0.0,
        float $bruteForceByKeysRatio = 0.0,
        int $memoryLimitMb = 0,
        ?string $allowedBasePath = null,
        bool $verboseErrors = false,
    ): void {
        self::$verboseErrors = $verboseErrors;

        if ($allowedBasePath !== null && !is_dir($allowedBasePath)) {
            throw new ZVecException("Allowed base path does not exist: {$allowedBasePath}");
        }
        self::$allowedBasePath = $allowedBasePath;

        $ffi = self::ffi();

        $logConfig = $logType === self::LOG_FILE
            ? $ffi->zvec_log_config_create_file($logLevel, $logDir, $logBasename, $logFileSize, $logOverdueDays)
            : $ffi->zvec_log_config_create_console($logLevel);

        $configData = $ffi->zvec_config_data_create();
        $ffi->zvec_config_data_set_log_config($configData, $logConfig);

        if ($queryThreads > 0) {
            $ffi->zvec_config_data_set_query_thread_count($configData, $queryThreads);
        }
        if ($optimizeThreads > 0) {
            $ffi->zvec_config_data_set_optimize_thread_count($configData, $optimizeThreads);
        }
        if ($invertToForwardScanRatio > 0.0) {
            $ffi->zvec_config_data_set_invert_to_forward_scan_ratio($configData, $invertToForwardScanRatio);
        }
        if ($bruteForceByKeysRatio > 0.0) {
            $ffi->zvec_config_data_set_brute_force_by_keys_ratio($configData, $bruteForceByKeysRatio);
        }
        if ($memoryLimitMb > 0) {
            $ffi->zvec_config_data_set_memory_limit($configData, $memoryLimitMb * self::BYTES_PER_MB);
        }

        try {
            self::checkStatus($ffi->zvec_ffi_initialize($configData));
        } finally {
            $ffi->zvec_log_config_free($logConfig);
            $ffi->zvec_config_data_free($configData);
        }
    }

    /**
     * Check whether the zvec library has been initialized.
     *
     * @return bool true if init() was called successfully, false otherwise.
     */
    public static function isInitialized(): bool
    {
        return self::ffi()->zvec_ffi_is_initialized() !== 0;
    }

    /**
     * Shut down the zvec library and release global resources.
     *
     * Idempotent — safe to call multiple times.
     * After shutdown, init() must be called again before any operations.
     *
     * @throws ZVecException On FFI shutdown error.
     */
    public static function shutdown(): void
    {
        self::ffi()->zvec_ffi_shutdown();
    }

    /**
     * @return array{code: int, message: ?string, file: ?string, line: int, function: ?string}
     */
    public static function getLastErrorDetails(): array
    {
        $ffi = self::ffi();
        $details = $ffi->new('zvec_error_details_t');
        $ffi->zvec_get_last_error_details(FFI::addr($details));
        $result = [
            'code' => $details->code,
            'message' => $details->message !== null ? FFI::string($details->message) : null,
            'file' => $details->file !== null ? FFI::string($details->file) : null,
            'line' => $details->line,
            'function' => $details->function !== null ? FFI::string($details->function) : null,
        ];

        if (!self::$verboseErrors) {
            $result['file'] = null;
            $result['line'] = 0;
            $result['function'] = null;
        }

        return $result;
    }

    public static function clearError(): void
    {
        self::ffi()->zvec_clear_error();
    }

    public static function getVersion(): string
    {
        return self::ffi()->zvec_get_version();
    }

    public static function checkVersion(int $major, int $minor, int $patch): bool
    {
        return self::ffi()->zvec_check_version($major, $minor, $patch) !== 0;
    }

    public static function getVersionMajor(): int
    {
        return self::ffi()->zvec_get_version_major();
    }

    public static function getVersionMinor(): int
    {
        return self::ffi()->zvec_get_version_minor();
    }

    public static function getVersionPatch(): int
    {
        return self::ffi()->zvec_get_version_patch();
    }

    /**
     * Query using a native ZVecVectorQuery object.
     *
     * @return ZVecDoc[]
     */
    public function queryVector(ZVecVectorQuery $query): array
    {
        $this->checkClosed();

        $ffi = self::ffi();
        $result = $ffi->new('zvec_query_result_t');
        $status = $ffi->zvec_collection_query_vector($this->handle, $query->getHandle(), FFI::addr($result));
        self::checkStatus($status);

        return self::parseQueryResult($result);
    }

    /**
     * GroupBy query using a native ZVecGroupByVectorQuery object.
     *
     * @return array<array{group_value: string, docs: ZVecDoc[]}>
     */
    public function groupByVectorQuery(ZVecGroupByVectorQuery $query): array
    {
        $this->checkClosed();

        $ffi = self::ffi();
        $result = $ffi->new('zvec_group_results_t');
        $status = $ffi->zvec_collection_group_by_query_vector($this->handle, $query->getHandle(), FFI::addr($result));
        self::checkStatus($status);

        return self::parseGroupResult($result);
    }

    /**
     * Resolve query parameters from explicit arguments or ZVecVectorQuery object.
     *
     * @return array{
     *     fieldName: string,
     *     queryVector: float[],
     *     topk: int,
     *     includeVector: bool,
     *     filter: ?string,
     *     outputFields: ?string[],
     *     queryParamType: int,
     *     hnswEf: int,
     *     ivfNprobe: int,
     *     radius: float,
     *     isLinear: bool,
     *     isUsingRefiner: bool,
     *     useFp64: bool
     * }
     */
    private function resolveQueryParams(
        string|ZVecVectorQuery $fieldName,
        array $queryVector = [],
        int $topk = 10,
        bool $includeVector = false,
        ?string $filter = null,
        ?array $outputFields = null,
        int $queryParamType = self::QUERY_PARAM_NONE,
        int $hnswEf = 200,
        int $ivfNprobe = 10,
        float $radius = 0.0,
        bool $isLinear = false,
        bool $isUsingRefiner = false,
    ): array {
        $useFp64 = false;

        if ($fieldName instanceof ZVecVectorQuery) {
            $vq = $fieldName;
            $fieldName = $vq->fieldName;
            $queryVector = $vq->vector;
            $queryParamType = $vq->queryParamType;
            $hnswEf = $vq->hnswEf;
            $ivfNprobe = $vq->ivfNprobe;
            $radius = $vq->radius;
            $isLinear = $vq->isLinear;
            $isUsingRefiner = $vq->isUsingRefiner;
            $topk = $vq->topk ?? $topk;
            $includeVector = $vq->includeVector ?? $includeVector;
            $filter = $vq->filter ?? $filter;
            $useFp64 = $vq->useFp64;

            if ($vq->docId !== null) {
                throw new ZVecException("query() with docId not yet implemented. Use queryById() or fetch the vector first.");
            }
        }

        if ($topk <= 0) {
            throw new ZVecException("topk must be a positive integer, got: {$topk}");
        }
        if (is_string($fieldName) && $fieldName === '') {
            throw new ZVecException('Field name must not be empty');
        }

        return [
            'fieldName' => $fieldName,
            'queryVector' => $queryVector,
            'topk' => $topk,
            'includeVector' => $includeVector,
            'filter' => $filter,
            'outputFields' => $outputFields,
            'queryParamType' => $queryParamType,
            'hnswEf' => $hnswEf,
            'ivfNprobe' => $ivfNprobe,
            'radius' => $radius,
            'isLinear' => $isLinear,
            'isUsingRefiner' => $isUsingRefiner,
            'useFp64' => $useFp64,
        ];
    }

    /**
     * Execute FP32 vector query via FFI.
     *
     * @param float[] $queryVector
     * @param string[]|null $outputFields
     * @return ZVecDoc[]
     */
    private function executeQuery(
        string $fieldName,
        array $queryVector,
        int $topk,
        bool $includeVector,
        ?string $filter,
        ?array $outputFields,
        int $queryParamType,
        int $hnswEf,
        int $ivfNprobe,
        float $radius,
        bool $isLinear,
        bool $isUsingRefiner,
    ): array {
        $ffi = self::ffi();
        $dim = count($queryVector);
        $vecData = $ffi->new("float[$dim]");
        foreach ($queryVector as $i => $v) {
            $vecData[$i] = $v;
        }

        $result = $ffi->new('zvec_query_result_t');

        $ofCStrings = [];
        try {
            $ofArr = null;
            $ofCount = -1;
            if ($outputFields !== null || $queryParamType !== self::QUERY_PARAM_NONE) {
                if ($outputFields !== null) {
                    [$ofArr, $ofCount, $ofCStrings] = self::toCStringArray($ffi, $outputFields);
                }

                $status = $ffi->zvec_collection_query_ex(
                    $this->handle, $fieldName, $vecData, $dim,
                    $topk, $includeVector ? 1 : 0, $filter ?? '',
                    $ofArr, $ofCount,
                    $queryParamType, $hnswEf, $ivfNprobe,
                    $radius, $isLinear ? 1 : 0, $isUsingRefiner ? 1 : 0,
                    FFI::addr($result)
                );
            } else {
                $status = $ffi->zvec_collection_query(
                    $this->handle, $fieldName, $vecData, $dim,
                    $topk, $includeVector ? 1 : 0, $filter ?? '',
                    FFI::addr($result)
                );
            }
            self::checkStatus($status);
        } finally {
            self::freeCStringArray($ofCStrings);
        }

        return self::parseQueryResult($result);
    }

    /**
     * Execute FP64 vector query via FFI.
     *
     * @param float[] $queryVector
     * @param string[]|null $outputFields
     * @return ZVecDoc[]
     */
    private function executeQueryFp64(
        string $fieldName,
        array $queryVector,
        int $topk,
        bool $includeVector,
        ?string $filter,
        ?array $outputFields,
        int $queryParamType,
        int $hnswEf,
        int $ivfNprobe,
        float $radius,
        bool $isLinear,
        bool $isUsingRefiner,
    ): array {
        $ffi = self::ffi();
        $dim = count($queryVector);
        $vecData = $ffi->new("double[$dim]");
        foreach ($queryVector as $i => $v) {
            $vecData[$i] = $v;
        }

        $result = $ffi->new('zvec_query_result_t');

        $ofCStrings = [];
        try {
            $ofArr = null;
            $ofCount = -1;
            if ($outputFields !== null || $queryParamType !== self::QUERY_PARAM_NONE) {
                if ($outputFields !== null) {
                    [$ofArr, $ofCount, $ofCStrings] = self::toCStringArray($ffi, $outputFields);
                }

                $status = $ffi->zvec_collection_query_fp64_ex(
                    $this->handle, $fieldName, $vecData, $dim,
                    $topk, $includeVector ? 1 : 0, $filter ?? '',
                    $ofArr, $ofCount,
                    $queryParamType, $hnswEf, $ivfNprobe,
                    $radius, $isLinear ? 1 : 0, $isUsingRefiner ? 1 : 0,
                    FFI::addr($result)
                );
            } else {
                $status = $ffi->zvec_collection_query_fp64(
                    $this->handle, $fieldName, $vecData, $dim,
                    $topk, $includeVector ? 1 : 0, $filter ?? '',
                    FFI::addr($result)
                );
            }
            self::checkStatus($status);
        } finally {
            self::freeCStringArray($ofCStrings);
        }

        return self::parseQueryResult($result);
    }

    /**
     * @param float[] $queryVector
     * @param string[]|null $outputFields
     * @return ZVecDoc[]
     * @deprecated Use queryWithReranker() when passing a $reranker. The $reranker parameter is deprecated.
     */
    public function query(
        string|ZVecVectorQuery $fieldName,
        array $queryVector = [],
        int $topk = 10,
        bool $includeVector = false,
        ?string $filter = null,
        ?array $outputFields = null,
        int $queryParamType = self::QUERY_PARAM_NONE,
        int $hnswEf = 200,
        int $ivfNprobe = 10,
        float $radius = 0.0,
        bool $isLinear = false,
        bool $isUsingRefiner = false,
        ?ZVecReRanker $reranker = null
    ): array {
        if ($reranker !== null) {
            trigger_error(
                'query(): Passing $reranker is deprecated. Use queryWithReranker() instead.',
                E_USER_DEPRECATED
            );
            return $this->queryWithReranker(
                fieldName: $fieldName,
                queryVector: $queryVector,
                topk: $topk,
                includeVector: $includeVector,
                filter: $filter,
                outputFields: $outputFields,
                queryParamType: $queryParamType,
                hnswEf: $hnswEf,
                ivfNprobe: $ivfNprobe,
                radius: $radius,
                isLinear: $isLinear,
                isUsingRefiner: $isUsingRefiner,
                reranker: $reranker
            );
        }

        $this->checkClosed();

        $params = $this->resolveQueryParams(
            $fieldName, $queryVector, $topk, $includeVector, $filter,
            $outputFields, $queryParamType, $hnswEf, $ivfNprobe,
            $radius, $isLinear, $isUsingRefiner
        );

        if ($params['useFp64']) {
            return $this->executeQueryFp64(
                $params['fieldName'], $params['queryVector'], $params['topk'],
                $params['includeVector'], $params['filter'], $params['outputFields'],
                $params['queryParamType'], $params['hnswEf'], $params['ivfNprobe'],
                $params['radius'], $params['isLinear'], $params['isUsingRefiner']
            );
        }

        return $this->executeQuery(
            $params['fieldName'], $params['queryVector'], $params['topk'],
            $params['includeVector'], $params['filter'], $params['outputFields'],
            $params['queryParamType'], $params['hnswEf'], $params['ivfNprobe'],
            $params['radius'], $params['isLinear'], $params['isUsingRefiner']
        );
    }

    /**
     * @param int[] $queryVector
     */
    public function queryFp16(
        string $fieldName,
        array $queryVector,
        int $topk = 10,
        bool $includeVector = false,
        ?string $filter = null
    ): array {
        $this->checkClosed();
        if ($topk <= 0) {
            throw new ZVecException("topk must be a positive integer, got: {$topk}");
        }
        if ($fieldName === '') {
            throw new ZVecException('Field name must not be empty');
        }

        $ffi = self::ffi();
        $dim = count($queryVector);
        $vecData = $ffi->new("uint16_t[$dim]");
        foreach ($queryVector as $i => $v) {
            $vecData[$i] = $v;
        }

        $result = $ffi->new('zvec_query_result_t');
        $status = $ffi->zvec_collection_query_fp16(
            $this->handle, $fieldName, $vecData, $dim,
            $topk, $includeVector ? 1 : 0, $filter ?? '',
            FFI::addr($result)
        );
        self::checkStatus($status);

        return self::parseQueryResult($result);
    }

    /**
     * @param float[] $queryVector
     * @param string[]|null $outputFields
     * @return ZVecDoc[]
     * @deprecated Use queryWithReranker() when passing a $reranker. The $reranker parameter is deprecated.
     */
    public function queryFp64(
        string $fieldName,
        array $queryVector,
        int $topk = 10,
        bool $includeVector = false,
        ?string $filter = null,
        ?array $outputFields = null,
        int $queryParamType = self::QUERY_PARAM_NONE,
        int $hnswEf = 200,
        int $ivfNprobe = 10,
        float $radius = 0.0,
        bool $isLinear = false,
        bool $isUsingRefiner = false,
        ?ZVecReRanker $reranker = null
    ): array {
        if ($reranker !== null) {
            trigger_error(
                'queryFp64(): Passing $reranker is deprecated. Use queryWithReranker() instead.',
                E_USER_DEPRECATED
            );
            return $this->queryWithRerankerFp64(
                fieldName: $fieldName,
                queryVector: $queryVector,
                topk: $topk,
                includeVector: $includeVector,
                filter: $filter,
                outputFields: $outputFields,
                queryParamType: $queryParamType,
                hnswEf: $hnswEf,
                ivfNprobe: $ivfNprobe,
                radius: $radius,
                isLinear: $isLinear,
                isUsingRefiner: $isUsingRefiner,
                reranker: $reranker
            );
        }

        $this->checkClosed();

        if ($topk <= 0) {
            throw new ZVecException("topk must be a positive integer, got: {$topk}");
        }
        if ($fieldName === '') {
            throw new ZVecException('Field name must not be empty');
        }

        return $this->executeQueryFp64(
            $fieldName, $queryVector, $topk, $includeVector, $filter,
            $outputFields, $queryParamType, $hnswEf, $ivfNprobe,
            $radius, $isLinear, $isUsingRefiner
        );
    }

    /**
     * Two-stage retrieval: fetch candidates then rerank to top results.
     *
     * @param float[] $queryVector
     * @param string[]|null $outputFields
     * @return ZVecRerankedDoc[] Reranked results sorted by combined score
     */
    public function queryWithReranker(
        string|ZVecVectorQuery $fieldName,
        array $queryVector = [],
        int $topk = 10,
        bool $includeVector = false,
        ?string $filter = null,
        ?array $outputFields = null,
        int $queryParamType = self::QUERY_PARAM_NONE,
        int $hnswEf = 200,
        int $ivfNprobe = 10,
        float $radius = 0.0,
        bool $isLinear = false,
        bool $isUsingRefiner = false,
        ?ZVecReRanker $reranker = null
    ): array {
        $this->checkClosed();
        if ($reranker === null) {
            throw new ZVecException('queryWithReranker() requires a $reranker argument');
        }

        $params = $this->resolveQueryParams(
            $fieldName, $queryVector, $topk, $includeVector, $filter,
            $outputFields, $queryParamType, $hnswEf, $ivfNprobe,
            $radius, $isLinear, $isUsingRefiner
        );

        // Fetch more results for two-stage retrieval
        $fetchTopk = max($params['topk'] * 2, 100);

        if ($params['useFp64']) {
            $docs = $this->executeQueryFp64(
                $params['fieldName'], $params['queryVector'], $fetchTopk,
                $params['includeVector'], $params['filter'], $params['outputFields'],
                $params['queryParamType'], $params['hnswEf'], $params['ivfNprobe'],
                $params['radius'], $params['isLinear'], $params['isUsingRefiner']
            );
        } else {
            $docs = $this->executeQuery(
                $params['fieldName'], $params['queryVector'], $fetchTopk,
                $params['includeVector'], $params['filter'], $params['outputFields'],
                $params['queryParamType'], $params['hnswEf'], $params['ivfNprobe'],
                $params['radius'], $params['isLinear'], $params['isUsingRefiner']
            );
        }

        $queryResults = [$params['fieldName'] => $docs];
        return $reranker->rerank($queryResults);
    }

    /**
     * Two-stage retrieval for FP64 vectors.
     *
     * @param float[] $queryVector
     * @param string[]|null $outputFields
     * @return ZVecRerankedDoc[] Reranked results sorted by combined score
     */
    private function queryWithRerankerFp64(
        string $fieldName,
        array $queryVector,
        int $topk = 10,
        bool $includeVector = false,
        ?string $filter = null,
        ?array $outputFields = null,
        int $queryParamType = self::QUERY_PARAM_NONE,
        int $hnswEf = 200,
        int $ivfNprobe = 10,
        float $radius = 0.0,
        bool $isLinear = false,
        bool $isUsingRefiner = false,
        ?ZVecReRanker $reranker = null
    ): array {
        $this->checkClosed();
        if ($reranker === null) {
            throw new ZVecException('queryWithRerankerFp64() requires a $reranker argument');
        }
        if ($topk <= 0) {
            throw new ZVecException("topk must be a positive integer, got: {$topk}");
        }
        if ($fieldName === '') {
            throw new ZVecException('Field name must not be empty');
        }

        $fetchTopk = max($topk * 2, 100);

        $docs = $this->executeQueryFp64(
            $fieldName, $queryVector, $fetchTopk, $includeVector, $filter,
            $outputFields, $queryParamType, $hnswEf, $ivfNprobe,
            $radius, $isLinear, $isUsingRefiner
        );

        $queryResults = [$fieldName => $docs];
        return $reranker->rerank($queryResults);
    }

    /**
     * Multi-vector query - search across multiple vector fields simultaneously.
     *
     * Executes queries against multiple vector fields and merges results using
     * a reranker algorithm (RRF or Weighted). This enables hybrid search scenarios
     * like combining dense and sparse embeddings, or fusing multiple embedding types.
     *
     * @param ZVecVectorQuery[] $vectorQueries Array of vector queries, one per field
     * @param ZVecReRanker $reranker Reranker for merging results (required)
     * @param int $topk Number of top results to return after reranking
     * @param string|null $filter Optional filter expression applied to all queries
     * @param string[]|null $outputFields Fields to include in returned documents
     * @return ZVecRerankedDoc[] Reranked results sorted by combined score
     */
    public function queryMulti(
        array $vectorQueries,
        ZVecReRanker $reranker,
        int $topk = 10,
        ?string $filter = null,
        ?array $outputFields = null
    ): array {
        $this->checkClosed();

        if (empty($vectorQueries)) {
            throw new ZVecException("At least one vector query is required");
        }

        // Execute each vector query individually
        $queryResults = [];
        foreach ($vectorQueries as $vq) {
            if (!($vq instanceof ZVecVectorQuery)) {
                throw new ZVecException("All queries must be ZVecVectorQuery instances");
            }

            // For multi-vector, fetch more candidates to give reranker enough data
            $fetchTopk = max($topk * 2, 100);

            $docs = $this->query(
                fieldName: $vq,
                queryVector: [],
                topk: $fetchTopk,
                includeVector: false,
                filter: $filter,
                outputFields: $outputFields,
                queryParamType: $vq->queryParamType,
                hnswEf: $vq->hnswEf,
                ivfNprobe: $vq->ivfNprobe,
                radius: $vq->radius,
                isLinear: $vq->isLinear,
                isUsingRefiner: $vq->isUsingRefiner,
                reranker: null // Don't rerank individual queries
            );

            $queryResults[$vq->fieldName] = $docs;
        }

        // Apply reranker to merge results
        return $reranker->rerank($queryResults);
    }

    /**
     * @param string[]|null $outputFields
     * @return ZVecDoc[]
     */
    public function queryByFilter(string $filter, int $topk = 100, ?array $outputFields = null): array
    {
        $this->checkClosed();
        if ($topk <= 0) {
            throw new ZVecException("topk must be a positive integer, got: {$topk}");
        }
        $ffi = self::ffi();
        $result = $ffi->new('zvec_query_result_t');

        $ofCStrings = [];
        try {
            if ($outputFields !== null) {
                [$ofArr, $ofCount, $ofCStrings] = self::toCStringArray($ffi, $outputFields);

                $status = $ffi->zvec_collection_query_filter_ex(
                    $this->handle, $filter, $topk,
                    $ofArr, $ofCount,
                    FFI::addr($result)
                );
            } else {
                $status = $ffi->zvec_collection_query_filter($this->handle, $filter, $topk, FFI::addr($result));
            }
            self::checkStatus($status);
        } finally {
            self::freeCStringArray($ofCStrings);
        }

        $docs = self::parseQueryResult($result);

        return $docs;
    }

    /**
     * Query by document ID - find similar documents using an existing document's embedding.
     *
     * @param string $fieldName Vector field name to use for similarity
     * @param string $docId Document ID to use as the query source
     * @param int $topk Number of results to return
     * @param bool $includeVector Whether to include vector data in results
     * @param string|null $filter Optional filter expression
     * @param string[]|null $outputFields Fields to include in results
     * @param int $queryParamType Query parameter type (QUERY_PARAM_NONE, QUERY_PARAM_HNSW, etc.)
     * @param int $hnswEf HNSW ef parameter
     * @param int $ivfNprobe IVF nprobe parameter
     * @param float $radius Search radius for range queries
     * @param bool $isLinear Use linear search
     * @param bool $isUsingRefiner Use refiner
     * @return ZVecDoc[]
     */
    public function queryById(
        string $fieldName,
        string $docId,
        int $topk = 10,
        bool $includeVector = false,
        ?string $filter = null,
        ?array $outputFields = null,
        int $queryParamType = self::QUERY_PARAM_NONE,
        int $hnswEf = 200,
        int $ivfNprobe = 10,
        float $radius = 0.0,
        bool $isLinear = false,
        bool $isUsingRefiner = false
    ): array {
        $this->checkClosed();
        if ($topk <= 0) {
            throw new ZVecException("topk must be a positive integer, got: {$topk}");
        }
        if ($fieldName === '') {
            throw new ZVecException('Field name must not be empty');
        }
        if ($docId === '') {
            throw new ZVecException('Document ID must not be empty');
        }

        $docs = $this->fetch($docId);
        if (empty($docs)) {
            throw new ZVecException("Document not found: $docId");
        }

        $vector = $docs[0]->getVectorFp32($fieldName);
        $isFp64 = false;
        if ($vector === null) {
            $vector = $docs[0]->getVectorFp64($fieldName);
            $isFp64 = $vector !== null;
        }
        if ($vector === null) {
            throw new ZVecException("Vector field '$fieldName' not found in document: $docId");
        }

        if ($isFp64) {
            return $this->queryFp64(
                $fieldName, $vector, $topk, $includeVector, $filter,
                $outputFields, $queryParamType, $hnswEf, $ivfNprobe,
                $radius, $isLinear, $isUsingRefiner
            );
        }

        return $this->query(
            $fieldName,
            $vector,
            $topk,
            $includeVector,
            $filter,
            $outputFields,
            $queryParamType,
            $hnswEf,
            $ivfNprobe,
            $radius,
            $isLinear,
            $isUsingRefiner
        );
    }

    /**
     * @param float[] $queryVector
     * @param string[]|null $outputFields
     * @return array<array{group_value: string, docs: ZVecDoc[]}>
     */
    public function groupByQuery(
        string|ZVecVectorQuery $fieldName,
        array $queryVector,
        string $groupByField,
        int $groupCount = 2,
        int $groupTopk = 3,
        bool $includeVector = false,
        ?string $filter = null,
        ?array $outputFields = null,
        int $queryParamType = self::QUERY_PARAM_NONE,
        int $hnswEf = 200,
        int $ivfNprobe = 10,
        float $radius = 0.0,
        bool $isLinear = false,
        bool $isUsingRefiner = false
    ): array {
        $this->checkClosed();

        // Handle ZVecVectorQuery object
        if ($fieldName instanceof ZVecVectorQuery) {
            $vq = $fieldName;
            $fieldName = $vq->fieldName;
            $queryVector = $vq->vector;
            $queryParamType = $vq->queryParamType;
            $hnswEf = $vq->hnswEf;
            $ivfNprobe = $vq->ivfNprobe;
            $radius = $vq->radius;
            $isLinear = $vq->isLinear;
            $isUsingRefiner = $vq->isUsingRefiner;
            $includeVector = $vq->includeVector ?? $includeVector;
            $filter = $vq->filter ?? $filter;

            if ($vq->docId !== null) {
                throw new ZVecException("groupByQuery() with docId not yet implemented. Use queryById() or fetch the vector first.");
            }
        }

        if ($groupCount <= 0) {
            throw new ZVecException("groupCount must be a positive integer, got: {$groupCount}");
        }
        if ($groupTopk <= 0) {
            throw new ZVecException("groupTopk must be a positive integer, got: {$groupTopk}");
        }
        if (is_string($fieldName) && $fieldName === '') {
            throw new ZVecException('Field name must not be empty');
        }
        if ($groupByField === '') {
            throw new ZVecException('Group by field must not be empty');
        }

        $ffi = self::ffi();
        $dim = count($queryVector);
        $vecData = $ffi->new("float[$dim]");
        foreach ($queryVector as $i => $v) {
            $vecData[$i] = $v;
        }

        $ofArr = null;
        $ofCount = -1;
        $ofCStrings = [];
        $result = $ffi->new('zvec_group_results_t');
        try {
            if ($outputFields !== null) {
                [$ofArr, $ofCount, $ofCStrings] = self::toCStringArray($ffi, $outputFields);
            }
            $status = $ffi->zvec_collection_group_by_query(
                $this->handle, $fieldName, $vecData, $dim,
                $groupByField, $groupCount, $groupTopk,
                $includeVector ? 1 : 0, $filter ?? '',
                $ofArr, $ofCount,
                $queryParamType, $hnswEf, $ivfNprobe,
                $radius, $isLinear ? 1 : 0, $isUsingRefiner ? 1 : 0,
                FFI::addr($result)
            );
            self::checkStatus($status);
        } finally {
            self::freeCStringArray($ofCStrings);
        }

        $groups = self::parseGroupResult($result);

        return $groups;
    }

    public function stats(): string
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $bufSize = self::PATH_BUFFER_SIZE;
        while (true) {
            $buf = $ffi->new("char[$bufSize]");
            self::checkStatus($ffi->zvec_collection_stats($this->handle, $buf, $bufSize));
            $str = FFI::string($buf);
            if (strlen($str) < $bufSize - 1) {
                return $str;
            }
            $bufSize *= 2;
            if ($bufSize > self::MAX_STRING_BUFFER_SIZE) {
                throw new ZVecException('Stats string exceeds maximum buffer size of 1 MB');
            }
        }
    }

    /**
     * Get structured collection stats.
     */
    public function getStatsStruct(): ZVecCollectionStats
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $out = $ffi->new('zvec_collection_stats_t');
        self::checkStatus($ffi->zvec_collection_get_stats_struct($this->handle, FFI::addr($out)));
        return new ZVecCollectionStats($out);
    }

    /**
     * Get structured field schema for a specific field.
     */
    public function getFieldSchema(string $fieldName): ZVecFieldSchema
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $out = $ffi->new('zvec_field_schema_t');
        self::checkStatus($ffi->zvec_collection_get_field_schema($this->handle, $fieldName, FFI::addr($out)));
        return new ZVecFieldSchema($out);
    }
}

// Backward-compatible aliases for global namespace usage
class_alias(ZVec::class, 'ZVec');
class_alias(ZVecException::class, 'ZVecException');
class_alias(ZVecSchema::class, 'ZVecSchema');
class_alias(ZVecDoc::class, 'ZVecDoc');
class_alias(ZVecCollectionOptions::class, 'ZVecCollectionOptions');
class_alias(ZVecCollectionStats::class, 'ZVecCollectionStats');
class_alias(ZVecFieldSchema::class, 'ZVecFieldSchema');
class_alias(ZVecIndexParams::class, 'ZVecIndexParams');
\class_alias(ZVecQueryInterface::class, 'ZVecQueryInterface');
class_alias(ZVecVectorQuery::class, 'ZVecVectorQuery');
class_alias(ZVecGroupByVectorQuery::class, 'ZVecGroupByVectorQuery');
\class_alias(ZVecReRanker::class, 'ZVecReRanker');
class_alias(ZVecRerankedDoc::class, 'ZVecRerankedDoc');
class_alias(ZVecRrfReRanker::class, 'ZVecRrfReRanker');
class_alias(ZVecWeightedReRanker::class, 'ZVecWeightedReRanker');
