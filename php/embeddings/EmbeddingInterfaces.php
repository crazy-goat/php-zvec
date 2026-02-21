<?php

declare(strict_types=1);

/**
 * Interface for dense embedding functions.
 * Converts text input into dense vector representations.
 */
interface DenseEmbeddingFunction
{
    /**
     * Generate embedding vector for a single text input.
     *
     * @param string $input Text to embed
     * @return float[] Dense vector representation
     * @throws ZVecException If embedding fails
     */
    public function embed(string $input): array;

    /**
     * Generate embedding vectors for multiple text inputs (batch).
     *
     * @param string[] $inputs Array of texts to embed
     * @return array<float[]> Array of dense vector representations
     * @throws ZVecException If embedding fails
     */
    public function embedBatch(array $inputs): array;

    /**
     * Get the dimension of the embedding vectors.
     *
     * @return int Vector dimension
     */
    public function getDimension(): int;
}

/**
 * Interface for sparse embedding functions.
 * Converts text input into sparse vector representations.
 */
interface SparseEmbeddingFunction
{
    /**
     * Generate sparse embedding for a single text input.
     *
     * Returns an associative array where keys are token indices and values are weights.
     *
     * @param string $input Text to embed
     * @return array<int, float> Sparse vector representation (index => weight)
     * @throws ZVecException If embedding fails
     */
    public function embed(string $input): array;

    /**
     * Generate sparse embedding vectors for multiple text inputs (batch).
     *
     * @param string[] $inputs Array of texts to embed
     * @return array<array<int, float>> Array of sparse vector representations
     * @throws ZVecException If embedding fails
     */
    public function embedBatch(array $inputs): array;
}

/**
 * Base class for API-based embedding functions.
 * Provides common HTTP client functionality.
 */
abstract class ApiEmbeddingFunction
{
    protected string $apiKey;
    protected string $baseUrl;
    protected int $timeout;
    protected ?string $proxy = null;

    public function __construct(
        string $apiKey,
        ?string $baseUrl = null,
        int $timeout = 30,
        ?string $proxy = null
    ) {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl ?? $this->getDefaultBaseUrl();
        $this->timeout = $timeout;
        $this->proxy = $proxy;
    }

    abstract protected function getDefaultBaseUrl(): string;

    /**
     * Make HTTP POST request to embedding API.
     *
     * @param string $endpoint API endpoint path
     * @param array $data Request payload
     * @return array Response data
     * @throws ZVecException If request fails
     */
    protected function post(string $endpoint, array $data): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders());
        
        if ($this->proxy !== null) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error !== '') {
            throw new ZVecException("HTTP request failed: $error");
        }
        
        if ($response === false) {
            throw new ZVecException('HTTP request returned false');
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode !== 200) {
            $errorMsg = $data['error']['message'] ?? "HTTP $httpCode";
            throw new ZVecException("API error: $errorMsg", $httpCode);
        }
        
        if ($data === null) {
            throw new ZVecException('Invalid JSON response: ' . $response);
        }
        
        return $data;
    }

    /**
     * Get HTTP headers for API requests.
     *
     * @return array Header strings
     */
    abstract protected function getHeaders(): array;
}
