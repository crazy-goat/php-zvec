<?php

declare(strict_types=1);

class ZVecException extends RuntimeException {}

class ZVec
{
    private static ?FFI $ffi = null;
    private FFI\CData $handle;
    private bool $closed = false;

    private static function ffi(): FFI
    {
        if (self::$ffi === null) {
            $libPath = __DIR__ . '/../ffi/build/libzvec_ffi.dylib';

            if (!file_exists($libPath)) {
                throw new ZVecException("Library not found: $libPath. Run build_zvec.sh first.");
            }

            self::$ffi = FFI::cdef('
                typedef void* zvec_collection_t;
                typedef void* zvec_schema_t;
                typedef void* zvec_doc_t;

                typedef struct {
                    int code;
                    char message[512];
                } zvec_status_t;

                typedef struct {
                    int count;
                    int* codes;
                    char** messages;
                    char** doc_pks;
                } zvec_batch_result_t;

                typedef struct {
                    zvec_doc_t* docs;
                    int count;
                } zvec_query_result_t;

                zvec_status_t zvec_init(int log_type, int log_level,
                                        const char* log_dir, const char* log_basename,
                                        uint32_t log_file_size, uint32_t log_overdue_days,
                                        uint32_t query_threads, uint32_t optimize_threads,
                                        float invert_to_forward_scan_ratio,
                                        float brute_force_by_keys_ratio,
                                        uint64_t memory_limit_mb);

                zvec_schema_t zvec_schema_create(const char* name);
                void zvec_schema_free(zvec_schema_t schema);
                void zvec_schema_set_max_doc_count_per_segment(zvec_schema_t schema, uint64_t count);
                void zvec_schema_add_field_int64(zvec_schema_t schema, const char* name, int nullable, int with_invert_index);
                void zvec_schema_add_field_string(zvec_schema_t schema, const char* name, int nullable, int with_invert_index);
                void zvec_schema_add_field_bool(zvec_schema_t schema, const char* name, int nullable, int with_invert_index);
                void zvec_schema_add_field_int32(zvec_schema_t schema, const char* name, int nullable, int with_invert_index);
                void zvec_schema_add_field_uint32(zvec_schema_t schema, const char* name, int nullable, int with_invert_index);
                void zvec_schema_add_field_uint64(zvec_schema_t schema, const char* name, int nullable, int with_invert_index);
                void zvec_schema_add_field_float(zvec_schema_t schema, const char* name, int nullable);
                void zvec_schema_add_field_double(zvec_schema_t schema, const char* name, int nullable);
                void zvec_schema_add_field_vector_fp32(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type);
                void zvec_schema_add_field_vector_int8(zvec_schema_t schema, const char* name, uint32_t dimension, uint32_t metric_type);
                void zvec_schema_add_field_sparse_vector_fp32(zvec_schema_t schema, const char* name, uint32_t metric_type);
                void zvec_schema_add_field_sparse_vector_fp32(zvec_schema_t schema, const char* name, uint32_t metric_type);

                zvec_status_t zvec_collection_create(const char* path, zvec_schema_t schema, int read_only, int enable_mmap, uint32_t max_buffer_size, zvec_collection_t* out);
                zvec_status_t zvec_collection_open(const char* path, int read_only, int enable_mmap, uint32_t max_buffer_size, zvec_collection_t* out);
                void zvec_collection_free(zvec_collection_t coll);
                zvec_status_t zvec_collection_flush(zvec_collection_t coll);
                zvec_status_t zvec_collection_optimize(zvec_collection_t coll, uint32_t concurrency);
                zvec_status_t zvec_collection_destroy(zvec_collection_t coll);

                zvec_status_t zvec_collection_schema(zvec_collection_t coll, char* buf, size_t buf_size);
                zvec_status_t zvec_collection_path(zvec_collection_t coll, char* buf, size_t buf_size);
                zvec_status_t zvec_collection_options(zvec_collection_t coll, int* read_only, int* enable_mmap, uint32_t* max_buffer_size);

                zvec_status_t zvec_collection_add_column_int64(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency);
                zvec_status_t zvec_collection_add_column_bool(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency);
                zvec_status_t zvec_collection_add_column_int32(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency);
                zvec_status_t zvec_collection_add_column_uint32(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency);
                zvec_status_t zvec_collection_add_column_uint64(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency);
                zvec_status_t zvec_collection_add_column_float(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency);
                zvec_status_t zvec_collection_add_column_double(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency);
                zvec_status_t zvec_collection_add_column_string(zvec_collection_t coll, const char* name, int nullable, const char* default_expr, uint32_t concurrency);
                zvec_status_t zvec_collection_drop_column(zvec_collection_t coll, const char* name);
                zvec_status_t zvec_collection_rename_column(zvec_collection_t coll, const char* old_name, const char* new_name, uint32_t concurrency);
                zvec_status_t zvec_collection_alter_column(zvec_collection_t coll, const char* column_name, const char* new_name, uint32_t data_type, int nullable, uint32_t concurrency);

                zvec_status_t zvec_collection_create_invert_index(zvec_collection_t coll, const char* field_name, int enable_range, int enable_wildcard);
zvec_status_t zvec_collection_create_hnsw_index(zvec_collection_t coll, const char* field_name, uint32_t metric_type, int m, int ef_construction, uint32_t quantize_type, uint32_t concurrency);
zvec_status_t zvec_collection_create_flat_index(zvec_collection_t coll, const char* field_name, uint32_t metric_type, uint32_t quantize_type, uint32_t concurrency);
zvec_status_t zvec_collection_create_ivf_index(zvec_collection_t coll, const char* field_name, uint32_t metric_type, int n_list, int n_iters, int use_soar, uint32_t quantize_type, uint32_t concurrency);
zvec_status_t zvec_collection_drop_index(zvec_collection_t coll, const char* field_name);

                zvec_doc_t zvec_doc_create(const char* pk);
                void zvec_doc_free(zvec_doc_t doc);
                void zvec_doc_set_int64(zvec_doc_t doc, const char* field, int64_t value);
                void zvec_doc_set_bool(zvec_doc_t doc, const char* field, int value);
                void zvec_doc_set_int32(zvec_doc_t doc, const char* field, int32_t value);
                void zvec_doc_set_uint32(zvec_doc_t doc, const char* field, uint32_t value);
                void zvec_doc_set_uint64(zvec_doc_t doc, const char* field, uint64_t value);
                void zvec_doc_set_string(zvec_doc_t doc, const char* field, const char* value);
                void zvec_doc_set_float(zvec_doc_t doc, const char* field, float value);
                void zvec_doc_set_double(zvec_doc_t doc, const char* field, double value);
                void zvec_doc_set_vector_fp32(zvec_doc_t doc, const char* field, const float* data, uint32_t dim);
                void zvec_doc_set_vector_int8(zvec_doc_t doc, const char* field, const int8_t* data, uint32_t dim);

                const char* zvec_doc_get_pk(zvec_doc_t doc);
                float zvec_doc_get_score(zvec_doc_t doc);
                int zvec_doc_get_int64(zvec_doc_t doc, const char* field, int64_t* out);
                int zvec_doc_get_bool(zvec_doc_t doc, const char* field, int* out);
                int zvec_doc_get_int32(zvec_doc_t doc, const char* field, int32_t* out);
                int zvec_doc_get_uint32(zvec_doc_t doc, const char* field, uint32_t* out);
                int zvec_doc_get_uint64(zvec_doc_t doc, const char* field, uint64_t* out);
                int zvec_doc_get_string(zvec_doc_t doc, const char* field, const char** out);
                int zvec_doc_get_float(zvec_doc_t doc, const char* field, float* out);
                int zvec_doc_get_double(zvec_doc_t doc, const char* field, double* out);
                int zvec_doc_get_vector_fp32(zvec_doc_t doc, const char* field, const float** out, uint32_t* dim);
                int zvec_doc_get_vector_int8(zvec_doc_t doc, const char* field, const int8_t** out, uint32_t* dim);

                // Doc introspection
                int zvec_doc_has_field(zvec_doc_t doc, const char* field);
                int zvec_doc_has_vector(zvec_doc_t doc, const char* field);
                int zvec_doc_field_names(zvec_doc_t doc, char* buf, size_t buf_size);
                int zvec_doc_vector_names(zvec_doc_t doc, char* buf, size_t buf_size);

                zvec_status_t zvec_collection_insert(zvec_collection_t coll, zvec_doc_t* docs, int count);
                zvec_status_t zvec_collection_upsert(zvec_collection_t coll, zvec_doc_t* docs, int count);
                zvec_status_t zvec_collection_update(zvec_collection_t coll, zvec_doc_t* docs, int count);
                zvec_status_t zvec_collection_delete(zvec_collection_t coll, const char** pks, int count);
                zvec_status_t zvec_collection_delete_by_filter(zvec_collection_t coll, const char* filter);

                zvec_status_t zvec_collection_insert_batch(zvec_collection_t coll, zvec_doc_t* docs, int count, zvec_batch_result_t* result);
                zvec_status_t zvec_collection_upsert_batch(zvec_collection_t coll, zvec_doc_t* docs, int count, zvec_batch_result_t* result);
                zvec_status_t zvec_collection_update_batch(zvec_collection_t coll, zvec_doc_t* docs, int count, zvec_batch_result_t* result);
                void zvec_batch_result_free(zvec_batch_result_t* result);

                zvec_status_t zvec_collection_fetch(zvec_collection_t coll, const char** pks, int count, zvec_query_result_t* result);

                zvec_status_t zvec_collection_query(zvec_collection_t coll, const char* field_name,
                                                     const float* query_vector, uint32_t dim,
                                                     int topk, int include_vector,
                                                     const char* filter,
                                                     zvec_query_result_t* result);
                zvec_status_t zvec_collection_query_ex(zvec_collection_t coll, const char* field_name,
                                                        const float* query_vector, uint32_t dim,
                                                        int topk, int include_vector,
                                                        const char* filter,
                                                        const char** output_fields, int output_fields_count,
                                                        int query_param_type,
                                                        int hnsw_ef,
                                                        int ivf_nprobe,
                                                        float radius,
                                                        int is_linear,
                                                        int is_using_refiner,
                                                        zvec_query_result_t* result);
                zvec_status_t zvec_collection_query_filter(zvec_collection_t coll, const char* filter,
                                                            int topk, zvec_query_result_t* result);
                zvec_status_t zvec_collection_query_filter_ex(zvec_collection_t coll, const char* filter,
                                                               int topk,
                                                               const char** output_fields, int output_fields_count,
                                                               zvec_query_result_t* result);

                typedef struct {
                    zvec_doc_t* docs;
                    int count;
                    const char* group_by_value;
                } zvec_group_result_t;

                typedef struct {
                    zvec_group_result_t* groups;
                    int count;
                } zvec_group_results_t;

                zvec_status_t zvec_collection_group_by_query(zvec_collection_t coll, const char* field_name,
                                                              const float* query_vector, uint32_t dim,
                                                              const char* group_by_field,
                                                              uint32_t group_count, uint32_t group_topk,
                                                              int include_vector,
                                                              const char* filter,
                                                              const char** output_fields, int output_fields_count,
                                                              int query_param_type,
                                                              int hnsw_ef,
                                                              int ivf_nprobe,
                                                              float radius,
                                                              int is_linear,
                                                              int is_using_refiner,
                                                              zvec_group_results_t* result);
                void zvec_group_results_free(zvec_group_results_t* result);

                void zvec_query_result_free(zvec_query_result_t* result);
                void zvec_query_result_free_array(zvec_query_result_t* result);

                zvec_status_t zvec_collection_stats(zvec_collection_t coll, char* buf, size_t buf_size);
            ', $libPath);
        }
        return self::$ffi;
    }

    private static function checkStatus(FFI\CData $status): void
    {
        if ($status->code !== 0) {
            throw new ZVecException(FFI::string($status->message), $status->code);
        }
    }

    private function checkClosed(): void
    {
        if ($this->closed) {
            throw new ZVecException("Collection is closed or destroyed");
        }
    }

    public static function create(string $path, ZVecSchema $schema, bool $readOnly = false, bool $enableMmap = true, int $maxBufferSize = 67108864): self
    {
        $ffi = self::ffi();
        $out = $ffi->new('zvec_collection_t');
        $status = $ffi->zvec_collection_create($path, $schema->getHandle(), $readOnly ? 1 : 0, $enableMmap ? 1 : 0, $maxBufferSize, FFI::addr($out));
        self::checkStatus($status);
        return new self($out);
    }

    public static function open(string $path, bool $readOnly = false, bool $enableMmap = true, int $maxBufferSize = 67108864): self
    {
        $ffi = self::ffi();
        $out = $ffi->new('zvec_collection_t');
        $status = $ffi->zvec_collection_open($path, $readOnly ? 1 : 0, $enableMmap ? 1 : 0, $maxBufferSize, FFI::addr($out));
        self::checkStatus($status);
        return new self($out);
    }

    private function __construct(FFI\CData $handle)
    {
        $this->handle = $handle;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close(): void
    {
        if (!$this->closed) {
            self::ffi()->zvec_collection_free($this->handle);
            $this->closed = true;
        }
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
        if (!$this->closed) {
            self::checkStatus(self::ffi()->zvec_collection_destroy($this->handle));
            $this->closed = true;
        }
    }

    public function schema(): string
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $buf = $ffi->new('char[8192]');
        self::checkStatus($ffi->zvec_collection_schema($this->handle, $buf, 8192));
        return FFI::string($buf);
    }

    public function path(): string
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $buf = $ffi->new('char[4096]');
        self::checkStatus($ffi->zvec_collection_path($this->handle, $buf, 4096));
        return FFI::string($buf);
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
        // Data type constants for alter column (scalar numeric only)
        // INT32 = 4, INT64 = 5, UINT32 = 6, UINT64 = 7, FLOAT = 8, DOUBLE = 9
        $dataType = $newDataType ?? 0; // 0 = UNDEFINED (no type change)
        $isNullable = $nullable === null ? 0 : ($nullable ? 1 : 0);
        $rename = $newName ?? '';
        
        self::checkStatus(self::ffi()->zvec_collection_alter_column($this->handle, $columnName, $rename, $dataType, $isNullable, $concurrency));
    }

    public function createInvertIndex(string $fieldName, bool $enableRange = true, bool $enableWildcard = false): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_create_invert_index($this->handle, $fieldName, $enableRange ? 1 : 0, $enableWildcard ? 1 : 0));
    }

    public function createHnswIndex(string $fieldName, int $metricType = ZVecSchema::METRIC_IP, int $m = 50, int $efConstruction = 500, int $quantizeType = 0, int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_create_hnsw_index($this->handle, $fieldName, $metricType, $m, $efConstruction, $quantizeType, $concurrency));
    }

    public function createFlatIndex(string $fieldName, int $metricType = ZVecSchema::METRIC_IP, int $quantizeType = 0, int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_create_flat_index($this->handle, $fieldName, $metricType, $quantizeType, $concurrency));
    }

    public function createIvfIndex(string $fieldName, int $metricType = ZVecSchema::METRIC_IP, int $nList = 1024, int $nIters = 10, bool $useSoar = false, int $quantizeType = 0, int $concurrency = 0): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_create_ivf_index($this->handle, $fieldName, $metricType, $nList, $nIters, $useSoar ? 1 : 0, $quantizeType, $concurrency));
    }

    public function dropIndex(string $fieldName): void
    {
        $this->checkClosed();
        self::checkStatus(self::ffi()->zvec_collection_drop_index($this->handle, $fieldName));
    }

    public function insert(ZVecDoc ...$docs): void
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $count = count($docs);
        $arr = $ffi->new("zvec_doc_t[$count]");
        foreach ($docs as $i => $doc) {
            $arr[$i] = $doc->getHandle();
        }
        self::checkStatus($ffi->zvec_collection_insert($this->handle, $arr, $count));
    }

    public function upsert(ZVecDoc ...$docs): void
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $count = count($docs);
        $arr = $ffi->new("zvec_doc_t[$count]");
        foreach ($docs as $i => $doc) {
            $arr[$i] = $doc->getHandle();
        }
        self::checkStatus($ffi->zvec_collection_upsert($this->handle, $arr, $count));
    }

    public function update(ZVecDoc ...$docs): void
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $count = count($docs);
        $arr = $ffi->new("zvec_doc_t[$count]");
        foreach ($docs as $i => $doc) {
            $arr[$i] = $doc->getHandle();
        }
        self::checkStatus($ffi->zvec_collection_update($this->handle, $arr, $count));
    }

    /**
     * @return array<int, array{pk: string, ok: bool, error: string|null}>
     */
    public function insertBatch(ZVecDoc ...$docs): array
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $count = count($docs);
        $arr = $ffi->new("zvec_doc_t[$count]");
        foreach ($docs as $i => $doc) {
            $arr[$i] = $doc->getHandle();
        }
        
        $result = $ffi->new('zvec_batch_result_t');
        self::checkStatus($ffi->zvec_collection_insert_batch($this->handle, $arr, $count, FFI::addr($result)));
        
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
    public function upsertBatch(ZVecDoc ...$docs): array
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $count = count($docs);
        $arr = $ffi->new("zvec_doc_t[$count]");
        foreach ($docs as $i => $doc) {
            $arr[$i] = $doc->getHandle();
        }
        
        $result = $ffi->new('zvec_batch_result_t');
        self::checkStatus($ffi->zvec_collection_upsert_batch($this->handle, $arr, $count, FFI::addr($result)));
        
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
    public function updateBatch(ZVecDoc ...$docs): array
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $count = count($docs);
        $arr = $ffi->new("zvec_doc_t[$count]");
        foreach ($docs as $i => $doc) {
            $arr[$i] = $doc->getHandle();
        }
        
        $result = $ffi->new('zvec_batch_result_t');
        self::checkStatus($ffi->zvec_collection_update_batch($this->handle, $arr, $count, FFI::addr($result)));
        
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

    public function delete(string ...$pks): void
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $count = count($pks);
        $cStrings = [];
        $arr = $ffi->new("char*[$count]");
        foreach ($pks as $i => $pk) {
            $len = strlen($pk) + 1;
            $cStr = $ffi->new("char[$len]", false);
            FFI::memcpy($cStr, $pk, strlen($pk));
            $cStr[$len - 1] = "\0";
            $cStrings[] = $cStr;
            $arr[$i] = $cStr;
        }
        self::checkStatus($ffi->zvec_collection_delete($this->handle, $arr, $count));
        foreach ($cStrings as $cStr) {
            FFI::free($cStr);
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
        $ffi = self::ffi();
        $count = count($pks);
        $cStrings = [];
        $arr = $ffi->new("char*[$count]");
        foreach ($pks as $i => $pk) {
            $len = strlen($pk) + 1;
            $cStr = $ffi->new("char[$len]", false);
            FFI::memcpy($cStr, $pk, strlen($pk));
            $cStr[$len - 1] = "\0";
            $cStrings[] = $cStr;
            $arr[$i] = $cStr;
        }

        $result = $ffi->new('zvec_query_result_t');
        $status = $ffi->zvec_collection_fetch($this->handle, $arr, $count, FFI::addr($result));
        foreach ($cStrings as $cStr) {
            FFI::free($cStr);
        }
        self::checkStatus($status);

        $docs = [];
        for ($i = 0; $i < $result->count; $i++) {
            $docs[] = new ZVecDoc($result->docs[$i], true);
        }
        $ffi->zvec_query_result_free_array(FFI::addr($result));

        return $docs;
    }

    public const QUERY_PARAM_NONE = 0;
    public const QUERY_PARAM_HNSW = 1;
    public const QUERY_PARAM_IVF = 2;
    public const QUERY_PARAM_FLAT = 3;

    public const LOG_CONSOLE = 0;
    public const LOG_FILE = 1;

    public const LOG_DEBUG = 0;
    public const LOG_INFO = 1;
    public const LOG_WARN = 2;
    public const LOG_ERROR = 3;
    public const LOG_FATAL = 4;

    // Data types for alterColumn (scalar numeric only)
    public const TYPE_INT32 = 4;
    public const TYPE_INT64 = 5;
    public const TYPE_UINT32 = 6;
    public const TYPE_UINT64 = 7;
    public const TYPE_FLOAT = 8;
    public const TYPE_DOUBLE = 9;
    public const TYPE_BOOL = 3;

    // Quantize types for index creation
    public const QUANTIZE_UNDEFINED = 0;
    public const QUANTIZE_FP16 = 1;
    public const QUANTIZE_INT8 = 2;
    public const QUANTIZE_INT4 = 3;

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
        int $memoryLimitMb = 0
    ): void {
        self::checkStatus(self::ffi()->zvec_init(
            $logType, $logLevel,
            $logDir, $logBasename,
            $logFileSize, $logOverdueDays,
            $queryThreads, $optimizeThreads,
            $invertToForwardScanRatio,
            $bruteForceByKeysRatio,
            $memoryLimitMb
        ));
    }

    /**
     * @param float[] $queryVector
     * @param string[]|null $outputFields
     * @return ZVecDoc[]
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

            if ($vq->docId !== null) {
                throw new ZVecException("query() with docId not yet implemented. Use queryById() or fetch the vector first.");
            }
        }

        $ffi = self::ffi();
        $dim = count($queryVector);
        $vecData = $ffi->new("float[$dim]");
        foreach ($queryVector as $i => $v) {
            $vecData[$i] = $v;
        }

        $result = $ffi->new('zvec_query_result_t');

        if ($outputFields !== null || $queryParamType !== self::QUERY_PARAM_NONE) {
            $ofArr = null;
            $ofCount = -1;
            $ofCStrings = [];
            if ($outputFields !== null) {
                $ofCount = count($outputFields);
                $ofArr = $ffi->new("char*[$ofCount]");
                foreach ($outputFields as $i => $f) {
                    $len = strlen($f) + 1;
                    $cStr = $ffi->new("char[$len]", false);
                    FFI::memcpy($cStr, $f, strlen($f));
                    $cStr[$len - 1] = "\0";
                    $ofCStrings[] = $cStr;
                    $ofArr[$i] = $cStr;
                }
            }

            $status = $ffi->zvec_collection_query_ex(
                $this->handle, $fieldName, $vecData, $dim,
                $topk, $includeVector ? 1 : 0, $filter ?? '',
                $ofArr, $ofCount,
                $queryParamType, $hnswEf, $ivfNprobe,
                $radius, $isLinear ? 1 : 0, $isUsingRefiner ? 1 : 0,
                FFI::addr($result)
            );

            foreach ($ofCStrings as $cStr) {
                FFI::free($cStr);
            }
        } else {
            $status = $ffi->zvec_collection_query(
                $this->handle, $fieldName, $vecData, $dim,
                $topk, $includeVector ? 1 : 0, $filter ?? '',
                FFI::addr($result)
            );
        }
        self::checkStatus($status);

        $docs = [];
        for ($i = 0; $i < $result->count; $i++) {
            $docs[] = new ZVecDoc($result->docs[$i], true);
        }
        $ffi->zvec_query_result_free_array(FFI::addr($result));

        return $docs;
    }

    /**
     * @param string[]|null $outputFields
     * @return ZVecDoc[]
     */
    public function queryByFilter(string $filter, int $topk = 100, ?array $outputFields = null): array
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $result = $ffi->new('zvec_query_result_t');

        if ($outputFields !== null) {
            $ofCount = count($outputFields);
            $ofArr = $ffi->new("char*[$ofCount]");
            $ofCStrings = [];
            foreach ($outputFields as $i => $f) {
                $len = strlen($f) + 1;
                $cStr = $ffi->new("char[$len]", false);
                FFI::memcpy($cStr, $f, strlen($f));
                $cStr[$len - 1] = "\0";
                $ofCStrings[] = $cStr;
                $ofArr[$i] = $cStr;
            }

            $status = $ffi->zvec_collection_query_filter_ex(
                $this->handle, $filter, $topk,
                $ofArr, $ofCount,
                FFI::addr($result)
            );

            foreach ($ofCStrings as $cStr) {
                FFI::free($cStr);
            }
        } else {
            $status = $ffi->zvec_collection_query_filter($this->handle, $filter, $topk, FFI::addr($result));
        }
        self::checkStatus($status);

        $docs = [];
        for ($i = 0; $i < $result->count; $i++) {
            $docs[] = new ZVecDoc($result->docs[$i], true);
        }
        $ffi->zvec_query_result_free_array(FFI::addr($result));

        return $docs;
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

            if ($vq->docId !== null) {
                throw new ZVecException("groupByQuery() with docId not yet implemented. Use queryById() or fetch the vector first.");
            }
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
        if ($outputFields !== null) {
            $ofCount = count($outputFields);
            $ofArr = $ffi->new("char*[$ofCount]");
            foreach ($outputFields as $i => $f) {
                $len = strlen($f) + 1;
                $cStr = $ffi->new("char[$len]", false);
                FFI::memcpy($cStr, $f, strlen($f));
                $cStr[$len - 1] = "\0";
                $ofCStrings[] = $cStr;
                $ofArr[$i] = $cStr;
            }
        }

        $result = $ffi->new('zvec_group_results_t');
        $status = $ffi->zvec_collection_group_by_query(
            $this->handle, $fieldName, $vecData, $dim,
            $groupByField, $groupCount, $groupTopk,
            $includeVector ? 1 : 0, $filter ?? '',
            $ofArr, $ofCount,
            $queryParamType, $hnswEf, $ivfNprobe,
            $radius, $isLinear ? 1 : 0, $isUsingRefiner ? 1 : 0,
            FFI::addr($result)
        );

        foreach ($ofCStrings as $cStr) {
            FFI::free($cStr);
        }
        self::checkStatus($status);

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

    public function stats(): string
    {
        $this->checkClosed();
        $ffi = self::ffi();
        $buf = $ffi->new('char[4096]');
        self::checkStatus($ffi->zvec_collection_stats($this->handle, $buf, 4096));
        return FFI::string($buf);
    }
}

class ZVecVectorQuery
{
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

    /**
     * @param float[] $vector Dense vector data
     */
    public function __construct(string $fieldName, array $vector)
    {
        $this->fieldName = $fieldName;
        $this->vector = $vector;
        $this->queryParamType = ZVec::QUERY_PARAM_NONE;
        $this->hnswEf = 200;
        $this->ivfNprobe = 10;
        $this->radius = 0.0;
        $this->isLinear = false;
        $this->isUsingRefiner = false;
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
        return $this;
    }

    public function setIvfParams(int $nprobe): self
    {
        $this->queryParamType = ZVec::QUERY_PARAM_IVF;
        $this->ivfNprobe = $nprobe;
        return $this;
    }

    public function setFlatParams(): self
    {
        $this->queryParamType = ZVec::QUERY_PARAM_FLAT;
        return $this;
    }

    public function setRadius(float $radius): self
    {
        $this->radius = $radius;
        return $this;
    }

    public function setLinear(bool $linear): self
    {
        $this->isLinear = $linear;
        return $this;
    }

    public function setUsingRefiner(bool $refiner): self
    {
        $this->isUsingRefiner = $refiner;
        return $this;
    }
}

class ZVecSchema
{
    private FFI\CData $handle;

    public function __construct(string $name)
    {
        $this->handle = self::ffi()->zvec_schema_create($name);
    }

    public function __destruct()
    {
        self::ffi()->zvec_schema_free($this->handle);
    }

    public function getHandle(): FFI\CData
    {
        return $this->handle;
    }

    public function setMaxDocCountPerSegment(int $count): self
    {
        self::ffi()->zvec_schema_set_max_doc_count_per_segment($this->handle, $count);
        return $this;
    }

    public function addInt64(string $name, bool $nullable = false, bool $withInvertIndex = false): self
    {
        self::ffi()->zvec_schema_add_field_int64($this->handle, $name, $nullable ? 1 : 0, $withInvertIndex ? 1 : 0);
        return $this;
    }

    public function addString(string $name, bool $nullable = false, bool $withInvertIndex = false): self
    {
        self::ffi()->zvec_schema_add_field_string($this->handle, $name, $nullable ? 1 : 0, $withInvertIndex ? 1 : 0);
        return $this;
    }

    public function addFloat(string $name, bool $nullable = true): self
    {
        self::ffi()->zvec_schema_add_field_float($this->handle, $name, $nullable ? 1 : 0);
        return $this;
    }

    public function addDouble(string $name, bool $nullable = true): self
    {
        self::ffi()->zvec_schema_add_field_double($this->handle, $name, $nullable ? 1 : 0);
        return $this;
    }

    public function addBool(string $name, bool $nullable = false, bool $withInvertIndex = false): self
    {
        self::ffi()->zvec_schema_add_field_bool($this->handle, $name, $nullable ? 1 : 0, $withInvertIndex ? 1 : 0);
        return $this;
    }

    public function addInt32(string $name, bool $nullable = false, bool $withInvertIndex = false): self
    {
        self::ffi()->zvec_schema_add_field_int32($this->handle, $name, $nullable ? 1 : 0, $withInvertIndex ? 1 : 0);
        return $this;
    }

    public function addUint32(string $name, bool $nullable = false, bool $withInvertIndex = false): self
    {
        self::ffi()->zvec_schema_add_field_uint32($this->handle, $name, $nullable ? 1 : 0, $withInvertIndex ? 1 : 0);
        return $this;
    }

    public function addUint64(string $name, bool $nullable = false, bool $withInvertIndex = false): self
    {
        self::ffi()->zvec_schema_add_field_uint64($this->handle, $name, $nullable ? 1 : 0, $withInvertIndex ? 1 : 0);
        return $this;
    }

    public const METRIC_L2 = 1;
    public const METRIC_IP = 2;
    public const METRIC_COSINE = 3;

    public function addVectorFp32(string $name, int $dimension, int $metricType = self::METRIC_IP): self
    {
        self::ffi()->zvec_schema_add_field_vector_fp32($this->handle, $name, $dimension, $metricType);
        return $this;
    }

    public function addSparseVectorFp32(string $name, int $metricType = self::METRIC_IP): self
    {
        self::ffi()->zvec_schema_add_field_sparse_vector_fp32($this->handle, $name, $metricType);
        return $this;
    }

    public function addVectorInt8(string $name, int $dimension, int $metricType = self::METRIC_IP): self
    {
        self::ffi()->zvec_schema_add_field_vector_int8($this->handle, $name, $dimension, $metricType);
        return $this;
    }

    private static function ffi(): FFI
    {
        return (new ReflectionClass(ZVec::class))->getMethod('ffi')->invoke(null);
    }
}

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

    private static function ffi(): FFI
    {
        return (new ReflectionClass(ZVec::class))->getMethod('ffi')->invoke(null);
    }
}
