<?php

declare(strict_types=1);

require_once __DIR__ . '/ZVecReRanker.php';
require_once __DIR__ . '/ZVecRerankedDoc.php';

/**
 * Weighted ReRanker for combining multi-vector search results.
 * 
 * Normalizes scores per metric type and computes weighted sum.
 * Useful when you want to give different importance to different vector fields.
 * 
 * Score normalization per metric:
 * - IP (Inner Product): min-max to [0, 1], higher is better
 * - COSINE: min-max to [0, 1], higher is better  
 * - L2: inverted min-max, lower distance is better
 * 
 * Final score = Σ(weight_i × normalized_score_i)
 */
class ZVecWeightedReRanker implements ZVecReRanker
{
    /**
     * Number of top results to return after reranking.
     */
    public int $topn;

    /**
     * Metric type for score normalization.
     * Use ZVecSchema::METRIC_L2, METRIC_IP, or METRIC_COSINE
     */
    public int $metricType;

    /**
     * Weights for each vector field.
     * Format: [fieldName => weight] where weights sum should typically be 1.0
     * Example: ['dense_embedding' => 0.7, 'sparse_embedding' => 0.3]
     */
    public array $weights;

    public function __construct(int $topn = 10, int $metricType = 2, array $weights = [])
    {
        $this->topn = $topn;
        $this->metricType = $metricType;
        $this->weights = $weights;
    }

    /**
     * Rerank results using weighted score combination.
     * 
     * @param array $queryResults Results from each vector field query.
     *        Format: [fieldName => ZVecDoc[]] where each ZVecDoc[] is ordered by relevance.
     *        Each field should have the same metric type (or you need separate rerankers per metric).
     * @return ZVecRerankedDoc[] Reranked and merged results, sorted by combined score (highest first)
     */
    public function rerank(array $queryResults): array
    {
        if (empty($queryResults)) {
            return [];
        }

        // First pass: collect all documents and find min/max scores per field
        $fieldStats = []; // [fieldName => ['min' => float, 'max' => float]]
        $allDocs = [];    // [fieldName => [pk => ['doc' => ZVecDoc, 'score' => float, 'rank' => int]]]

        foreach ($queryResults as $fieldName => $docs) {
            if (!is_array($docs)) {
                continue;
            }

            $fieldStats[$fieldName] = ['min' => PHP_FLOAT_MAX, 'max' => PHP_FLOAT_MIN];
            $allDocs[$fieldName] = [];

            foreach ($docs as $rank => $doc) {
                if (!($doc instanceof ZVecDoc)) {
                    continue;
                }

                $pk = $doc->getPk();
                $score = $doc->getScore();

                $allDocs[$fieldName][$pk] = [
                    'doc' => $doc,
                    'score' => $score,
                    'rank' => $rank + 1,
                ];

                // Update min/max
                if ($score < $fieldStats[$fieldName]['min']) {
                    $fieldStats[$fieldName]['min'] = $score;
                }
                if ($score > $fieldStats[$fieldName]['max']) {
                    $fieldStats[$fieldName]['max'] = $score;
                }
            }
        }

        // Second pass: normalize scores and compute weighted sum
        $combinedScores = []; // [pk => ['combined' => float, 'ranks' => [], 'scores' => [], 'doc' => ZVecDoc]]

        foreach ($allDocs as $fieldName => $docs) {
            $weight = $this->weights[$fieldName] ?? 0.0;
            if ($weight == 0.0) {
                continue; // Skip fields with no weight
            }

            $stats = $fieldStats[$fieldName];
            $range = $stats['max'] - $stats['min'];
            
            // Avoid division by zero
            if ($range == 0) {
                $range = 1.0;
            }

            foreach ($docs as $pk => $data) {
                $score = $data['score'];
                $doc = $data['doc'];
                $rank = $data['rank'];

                // Normalize score based on metric type
                // 1 = L2 (distance, lower is better), 2 = IP, 3 = COSINE (both higher is better)
                if ($this->metricType === 1) {
                    // L2: invert so lower distance => higher normalized score
                    $normalizedScore = ($stats['max'] - $score) / $range;
                } else {
                    // IP, COSINE: higher score => higher normalized score
                    $normalizedScore = ($score - $stats['min']) / $range;
                }

                if (!isset($combinedScores[$pk])) {
                    $combinedScores[$pk] = [
                        'combined' => 0.0,
                        'ranks' => [],
                        'scores' => [],
                        'doc' => $doc,
                    ];
                }

                $combinedScores[$pk]['combined'] += $weight * $normalizedScore;
                $combinedScores[$pk]['ranks'][$fieldName] = $rank;
                $combinedScores[$pk]['scores'][$fieldName] = $score;
            }
        }

        // Convert to ZVecRerankedDoc objects
        $reranked = [];
        foreach ($combinedScores as $pk => $data) {
            $reranked[] = new ZVecRerankedDoc(
                $data['doc'],
                $data['combined'],
                $data['ranks'],
                $data['scores']
            );
        }

        // Sort by combined score descending
        usort($reranked, fn(ZVecRerankedDoc $a, ZVecRerankedDoc $b) => $b->combinedScore <=> $a->combinedScore);

        // Return top N
        return array_slice($reranked, 0, $this->topn);
    }
}
