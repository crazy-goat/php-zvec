<?php

declare(strict_types=1);

if (extension_loaded('zvec')) return;

/**
 * ZVec PHP Embeddings
 *
 * This file provides embedding function implementations for converting
 * text to dense and sparse vector representations.
 *
 * Dense embeddings:
 * - OpenAIDenseEmbedding: OpenAI API (text-embedding-3-small/large, ada-002)
 * - QwenDenseEmbedding: DashScope API (text-embedding-v4)
 *
 * Sparse embeddings:
 * - BM25SparseEmbedding: BM25 algorithm for sparse vectors
 *
 * Usage:
 * ```php
 * require_once __DIR__ . '/embeddings.php';
 *
 * // OpenAI embedding
 * $embedder = new OpenAIDenseEmbedding('sk-your-key');
 * $vector = $embedder->embed('Hello world');
 *
 * // Batch embedding
 * $vectors = $embedder->embedBatch(['Text 1', 'Text 2', 'Text 3']);
 * ```
 */

// Require the ZVec base exception if not already loaded
if (!class_exists('ZVecException')) {
    require_once __DIR__ . '/ZVec.php';
}

// Load all embedding components
require_once __DIR__ . '/embeddings/EmbeddingInterfaces.php';
require_once __DIR__ . '/embeddings/OpenAIDenseEmbedding.php';
require_once __DIR__ . '/embeddings/QwenDenseEmbedding.php';
