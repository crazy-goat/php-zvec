<?php

declare(strict_types=1);

require_once __DIR__ . '/ZVecReRanker.php';
require_once __DIR__ . '/ZVecRerankedDoc.php';

/**
 * Reciprocal Rank Fusion (RRF) ReRanker.
 * 
 * Combines results from multiple vector queries using the RRF formula:
 * Score = 1 / (k + rank) where k is the rank constant.
 * 
 * RRF doesn't require normalized scores - it works purely on rankings,
 * making it a good default choice for multi-vector search.
 */
class ZVecRrfReRanker implements ZVecReRanker
{
    /**
     * Number of top results to return after reranking.
     */
    public int $topn;

    /**
     * Rank constant (k) for RRF formula.
     * Higher values give more weight to lower-ranked items.
     * Default: 60 (standard value from academic literature)
     */
    public int $rankConstant;

    public function __construct(int $topn = 10, int $rankConstant = 60)
    {
        $this->topn = $topn;
        $this->rankConstant = $rankConstant;
    }

    /**
     * Rerank results from multiple vector queries.
     * 
     * @param array $queryResults Results from each vector field query.
     *        Format: [fieldName => ZVecDoc[]] where each ZVecDoc[] is ordered by relevance.
     * @return ZVecRerankedDoc[] Reranked and merged results, sorted by combined score (highest first)
     */
    public function rerank(array $queryResults): array
    {
        if (empty($queryResults)) {
            return [];
        }

        // Map to track: pk => ['ranks' => [fieldName => rank], 'scores' => [fieldName => score], 'doc' => ZVecDoc]
        $docScores = [];

        // Process each field's results
        foreach ($queryResults as $fieldName => $docs) {
            if (!is_array($docs)) {
                continue;
            }

            foreach ($docs as $rank => $doc) {
                if (!($doc instanceof ZVecDoc)) {
                    continue;
                }

                $pk = $doc->getPk();
                $rank = $rank + 1; // Convert from 0-based to 1-based rank

                if (!isset($docScores[$pk])) {
                    $docScores[$pk] = [
                        'ranks' => [],
                        'scores' => [],
                        'doc' => $doc,
                    ];
                }

                $docScores[$pk]['ranks'][$fieldName] = $rank;
                $docScores[$pk]['scores'][$fieldName] = $doc->getScore();
            }
        }

        // Calculate RRF scores
        $reranked = [];
        foreach ($docScores as $pk => $data) {
            $rrfScore = 0.0;
            foreach ($data['ranks'] as $fieldName => $rank) {
                $rrfScore += 1.0 / ($this->rankConstant + $rank);
            }

            $reranked[] = new ZVecRerankedDoc(
                $data['doc'],
                $rrfScore,
                $data['ranks'],
                $data['scores']
            );
        }

        // Sort by combined score descending (higher is better)
        usort($reranked, fn(ZVecRerankedDoc $a, ZVecRerankedDoc $b) => $b->combinedScore <=> $a->combinedScore);

        // Return top N
        return array_slice($reranked, 0, $this->topn);
    }
}
