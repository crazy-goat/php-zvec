<?php

declare(strict_types=1);

if (extension_loaded('zvec')) return;

require_once __DIR__ . '/EmbeddingInterfaces.php';

/**
 * Qwen (DashScope) dense embedding function implementation.
 *
 * Supports text-embedding-v4 and other DashScope text embedding models.
 *
 * Example usage:
 * ```php
 * $embedder = new QwenDenseEmbedding(
 *     apiKey: 'sk-...',
 *     model: 'text-embedding-v4'
 * );
 *
 * $vector = $embedder->embed('Hello world');
 * ```
 */
class QwenDenseEmbedding extends ApiEmbeddingFunction implements DenseEmbeddingFunction
{
    public const MODEL_V4 = 'text-embedding-v4';
    public const MODEL_V3 = 'text-embedding-v3';
    public const MODEL_V2 = 'text-embedding-v2';
    public const MODEL_V1 = 'text-embedding-v1';

    private string $model;

    /**
     * Model dimensions mapping.
     */
    private const MODEL_DIMENSIONS = [
        self::MODEL_V4 => 1792,
        self::MODEL_V3 => 1024,
        self::MODEL_V2 => 1536,
        self::MODEL_V1 => 1536,
    ];

    /**
     * Constructor.
     *
     * @param string $apiKey DashScope API key
     * @param string $model Model name (default: text-embedding-v4)
     * @param string|null $baseUrl Custom base URL (default: https://dashscope.aliyuncs.com/api/v1)
     * @param int $timeout Request timeout in seconds (default: 30)
     * @param string|null $proxy HTTP proxy URL (optional)
     */
    public function __construct(
        string $apiKey,
        string $model = self::MODEL_V4,
        ?string $baseUrl = null,
        int $timeout = 30,
        ?string $proxy = null
    ) {
        parent::__construct($apiKey, $baseUrl, $timeout, $proxy);
        $this->model = $model;
    }

    protected function getDefaultBaseUrl(): string
    {
        return 'https://dashscope.aliyuncs.com/api/v1';
    }

    protected function getHeaders(): array
    {
        return [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
        ];
    }

    public function getDimension(): int
    {
        return self::MODEL_DIMENSIONS[$this->model] ?? 1536;
    }

    public function embed(string $input): array
    {
        $result = $this->embedBatch([$input]);
        return $result[0];
    }

    public function embedBatch(array $inputs): array
    {
        if (count($inputs) === 0) {
            return [];
        }

        if (count($inputs) > 25) {
            throw new ZVecException('Maximum batch size is 25 inputs for DashScope');
        }

        $texts = array_map(fn($input) => ['text' => $input], $inputs);

        $payload = [
            'model' => $this->model,
            'input' => [
                'texts' => $texts,
            ],
        ];

        $response = $this->post('/services/embeddings/text-embedding', $payload);

        if (!isset($response['output']['embeddings']) || !is_array($response['output']['embeddings'])) {
            throw new ZVecException('Invalid response format: missing embeddings array');
        }

        $embeddings = [];
        foreach ($response['output']['embeddings'] as $item) {
            if (!isset($item['embedding']) || !is_array($item['embedding'])) {
                throw new ZVecException('Invalid response format: missing embedding array');
            }
            $embeddings[] = array_map(fn($v) => (float) $v, $item['embedding']);
        }

        return $embeddings;
    }

    /**
     * Get the model name.
     *
     * @return string Current model
     */
    public function getModel(): string
    {
        return $this->model;
    }
}
