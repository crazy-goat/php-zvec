<?php

declare(strict_types=1);

require_once __DIR__ . '/EmbeddingInterfaces.php';

/**
 * OpenAI dense embedding function implementation.
 *
 * Supports text-embedding-3-small, text-embedding-3-large, and text-embedding-ada-002 models.
 *
 * Example usage:
 * ```php
 * $embedder = new OpenAIDenseEmbedding(
 *     apiKey: 'sk-...',
 *     model: 'text-embedding-3-small'
 * );
 *
 * $vector = $embedder->embed('Hello world');
 * // Returns float[1536] for text-embedding-3-small
 * ```
 */
class OpenAIDenseEmbedding extends ApiEmbeddingFunction implements DenseEmbeddingFunction
{
    public const MODEL_SMALL = 'text-embedding-3-small';
    public const MODEL_LARGE = 'text-embedding-3-large';
    public const MODEL_ADA = 'text-embedding-ada-002';

    private string $model;
    private ?int $dimensions;

    /**
     * Model dimensions mapping.
     */
    private const MODEL_DIMENSIONS = [
        self::MODEL_SMALL => 1536,
        self::MODEL_LARGE => 3072,
        self::MODEL_ADA => 1536,
    ];

    /**
     * Constructor.
     *
     * @param string $apiKey OpenAI API key
     * @param string $model Model name (default: text-embedding-3-small)
     * @param int|null $dimensions Number of dimensions (optional, only for v3 models)
     * @param string|null $baseUrl Custom base URL (default: https://api.openai.com/v1)
     * @param int $timeout Request timeout in seconds (default: 30)
     * @param string|null $proxy HTTP proxy URL (optional)
     */
    public function __construct(
        string $apiKey,
        string $model = self::MODEL_SMALL,
        ?int $dimensions = null,
        ?string $baseUrl = null,
        int $timeout = 30,
        ?string $proxy = null
    ) {
        parent::__construct($apiKey, $baseUrl, $timeout, $proxy);
        $this->model = $model;
        $this->dimensions = $dimensions;
    }

    protected function getDefaultBaseUrl(): string
    {
        return 'https://api.openai.com/v1';
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
        if ($this->dimensions !== null) {
            return $this->dimensions;
        }

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

        if (count($inputs) > 2048) {
            throw new ZVecException('Maximum batch size is 2048 inputs');
        }

        $payload = [
            'model' => $this->model,
            'input' => $inputs,
        ];

        if ($this->dimensions !== null) {
            $payload['dimensions'] = $this->dimensions;
        }

        $response = $this->post('/embeddings', $payload);

        if (!isset($response['data']) || !is_array($response['data'])) {
            throw new ZVecException('Invalid response format: missing data array');
        }

        $embeddings = [];
        foreach ($response['data'] as $item) {
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
