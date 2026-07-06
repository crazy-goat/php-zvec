<?php

declare(strict_types=1);

namespace CrazyGoat\ZVec;

if (extension_loaded('zvec')) return;

/**
 * Reranked document wrapper with combined score.
 *
 * Returned by reranker implementations (ZVecRrfReRanker,
 * ZVecWeightedReRanker). Wraps the original ZVecDoc and adds
 * combinedScore, sourceRanks (per-field rank), and sourceScores
 * (per-field original score) from multi-vector queries.
 *
 * @see ZVecReRanker
 * @see ZVecRrfReRanker
 * @see ZVecWeightedReRanker
 */
class ZVecRerankedDoc
{
    private ZVecDoc $doc;
    private float $combinedScore;
    private array $sourceRanks;
    private array $sourceScores;

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

    public function getPk(): string
    {
        return $this->doc->getPk();
    }

    public function getOriginalScore(): float
    {
        return $this->doc->getScore();
    }

    public function getDoc(): ZVecDoc
    {
        return $this->doc;
    }

    public function getCombinedScore(): float
    {
        return $this->combinedScore;
    }

    public function getSourceRanks(): array
    {
        return $this->sourceRanks;
    }

    public function getSourceScores(): array
    {
        return $this->sourceScores;
    }
}
