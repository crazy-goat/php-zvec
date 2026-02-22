<?php

declare(strict_types=1);

/**
 * Interface for rerankers that post-process vector search results.
 * 
 * Rerankers are used for:
 * - Multi-vector fusion (combining results from multiple vector fields)
 * - Two-stage retrieval (recall top-K, then rerank to top-N)
 * - Semantic reranking with external models
 * 
 * Implementations: ZVecRrfReRanker, ZVecWeightedReRanker
 */
interface ZVecReRanker
{
    /**
     * Rerank results from one or more vector queries.
     * 
     * @param array $queryResults Results from each vector field query.
     *        Format: [fieldName => ZVecDoc[]] where each ZVecDoc[] is ordered by relevance.
     *        For single-vector queries, this will be [fieldName => ZVecDoc[]].
     *        For multi-vector queries, this will have multiple field entries.
     * @return ZVecRerankedDoc[] Reranked and merged results, sorted by combined score (highest first)
     */
    public function rerank(array $queryResults): array;
}
