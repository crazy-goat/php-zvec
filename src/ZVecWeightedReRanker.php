<?php

declare(strict_types=1);

namespace CrazyGoat\ZVec;

if (extension_loaded('zvec')) return;

require_once __DIR__ . '/ZVec.php';
require_once __DIR__ . '/ZVecReRanker.php';
require_once __DIR__ . '/ZVecRerankedDoc.php';

/**
 * Weighted score fusion reranker.
 *
 * Combines results from multiple vector field queries using normalized
 * weighted scores. Supports both METRIC_IP and METRIC_L2 normalisation.
 * Each field has a configurable weight; scores are normalised to [0,1]
 * per field before fusion.
 *
 * @see ZVecRrfReRanker For rank-based fusion
 * @see ZVecReRanker Interface
 */
class ZVecWeightedReRanker implements ZVecReRanker
{
    private int $topn;
    private int $metricType;
    private array $weights;

    public function __construct(array $weights, int $topn = 10, int $metricType = ZVecSchema::METRIC_IP)
    {
        if (empty($weights)) {
            throw new ZVecException('ZVecWeightedReRanker requires at least one field weight');
        }
        $this->topn = $topn;
        $this->metricType = $metricType;
        $this->weights = $weights;
    }

    public function getTopn(): int
    {
        return $this->topn;
    }

    public function setTopn(int $topn): self
    {
        $this->topn = $topn;
        return $this;
    }

    public function getMetricType(): int
    {
        return $this->metricType;
    }

    public function setMetricType(int $metricType): self
    {
        $this->metricType = $metricType;
        return $this;
    }

    public function getWeights(): array
    {
        return $this->weights;
    }

    public function setWeights(array $weights): self
    {
        if (empty($weights)) {
            throw new ZVecException('ZVecWeightedReRanker requires at least one field weight');
        }
        $this->weights = $weights;
        return $this;
    }

    public function rerank(array $queryResults): array
    {
        if (empty($queryResults)) {
            return [];
        }

        $fieldStats = [];
        $allDocs = [];

        foreach ($queryResults as $fieldName => $docs) {
            if (!is_array($docs)) {
                continue;
            }

            $fieldStats[$fieldName] = ['min' => PHP_FLOAT_MAX, 'max' => -PHP_FLOAT_MAX];
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

                if ($score < $fieldStats[$fieldName]['min']) {
                    $fieldStats[$fieldName]['min'] = $score;
                }
                if ($score > $fieldStats[$fieldName]['max']) {
                    $fieldStats[$fieldName]['max'] = $score;
                }
            }
        }

        $combinedScores = [];

        foreach ($allDocs as $fieldName => $docs) {
            $weight = $this->weights[$fieldName] ?? 0.0;
            if ($weight == 0.0) {
                continue;
            }

            $stats = $fieldStats[$fieldName];
            $range = $stats['max'] - $stats['min'];
            
            if ($range == 0) {
                $range = 1.0;
            }

            foreach ($docs as $pk => $data) {
                $score = $data['score'];
                $doc = $data['doc'];
                $rank = $data['rank'];

                if ($this->metricType === ZVecSchema::METRIC_L2) {
                    $normalizedScore = ($stats['max'] - $score) / $range;
                } else {
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

        $reranked = [];
        foreach ($combinedScores as $pk => $data) {
            $reranked[] = new ZVecRerankedDoc(
                $data['doc'],
                $data['combined'],
                $data['ranks'],
                $data['scores']
            );
        }

        usort($reranked, fn(ZVecRerankedDoc $a, ZVecRerankedDoc $b) => $b->getCombinedScore() <=> $a->getCombinedScore());

        return array_slice($reranked, 0, $this->topn);
    }
}
