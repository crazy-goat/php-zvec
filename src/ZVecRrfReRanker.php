<?php

declare(strict_types=1);

namespace CrazyGoat\ZVec;

if (extension_loaded('zvec')) return;

require_once __DIR__ . '/ZVecReRanker.php';
require_once __DIR__ . '/ZVecRerankedDoc.php';

class ZVecRrfReRanker implements ZVecReRanker
{
    private int $topn;
    private int $rankConstant;

    public function __construct(int $topn = 10, int $rankConstant = 60)
    {
        $this->topn = $topn;
        $this->rankConstant = $rankConstant;
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

    public function getRankConstant(): int
    {
        return $this->rankConstant;
    }

    public function setRankConstant(int $rankConstant): self
    {
        $this->rankConstant = $rankConstant;
        return $this;
    }

    public function rerank(array $queryResults): array
    {
        if (empty($queryResults)) {
            return [];
        }

        $docScores = [];

        foreach ($queryResults as $fieldName => $docs) {
            if (!is_array($docs)) {
                continue;
            }

            foreach ($docs as $rank => $doc) {
                if (!($doc instanceof ZVecDoc)) {
                    continue;
                }

                $pk = $doc->getPk();
                $rank = $rank + 1;

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

        usort($reranked, fn(ZVecRerankedDoc $a, ZVecRerankedDoc $b) => $b->getCombinedScore() <=> $a->getCombinedScore());

        return array_slice($reranked, 0, $this->topn);
    }
}
