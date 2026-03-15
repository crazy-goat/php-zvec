<?php

declare(strict_types=1);

if (extension_loaded('zvec')) return;

/**
 * Reranked document result containing the original document and combined score.
 * 
 * This class wraps a ZVecDoc with additional information about how it was
 * ranked when combining multiple vector search results.
 */
class ZVecRerankedDoc
{
    /**
     * The original document from the vector search.
     */
    public ZVecDoc $doc;

    /**
     * Combined score from the reranking algorithm.
     * For RRF: reciprocal rank fusion score
     * For Weighted: normalized and weighted sum of scores
     */
    public float $combinedScore;

    /**
     * Rankings from each individual vector field query.
     * Format: [fieldName => rank] where rank starts from 1
     */
    public array $sourceRanks;

    /**
     * Original scores from each vector field query.
     * Format: [fieldName => score]
     */
    public array $sourceScores;

    public function __construct(
        ZVecDoc $doc,
        float $combinedScore,
        array $sourceRanks = [],
        array $sourceScores = []
    ) {
        $this->doc = $doc;
        $this->combinedScore = $combinedScore;
        $this->sourceRanks = $sourceRanks;
        $this->sourceScores = $sourceScores;
    }

    /**
     * Get the primary key of the document.
     */
    public function getPk(): string
    {
        return $this->doc->getPk();
    }

    /**
     * Get the original score from the first vector field query.
     * This is the score from C++ (e.g., distance/similarity).
     */
    public function getOriginalScore(): float
    {
        return $this->doc->getScore();
    }
}
