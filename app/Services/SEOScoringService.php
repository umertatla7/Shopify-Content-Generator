<?php

namespace App\Services;

class SEOScoringService
{
    public function score(array $content): array
    {
        $body = strip_tags($content['body'] ?? '');
        $primaryKeyword = strtolower($content['primary_keyword'] ?? '');
        $wordCount = str_word_count($body);
        $keywordHits = $primaryKeyword !== '' ? substr_count(strtolower($body), $primaryKeyword) : 0;

        $seoScore = 45;
        $seoScore += filled($content['meta_title'] ?? null) ? 10 : 0;
        $seoScore += filled($content['meta_description'] ?? null) ? 10 : 0;
        $seoScore += filled($content['slug'] ?? null) ? 5 : 0;
        $seoScore += $wordCount >= 700 ? 15 : min(15, (int) floor($wordCount / 50));
        $seoScore += $keywordHits > 0 ? min(15, $keywordHits * 3) : 0;

        $readabilityScore = 70;
        $readabilityScore += $wordCount >= 500 ? 10 : 0;
        $readabilityScore += str_contains($content['body'] ?? '', '<h2') ? 10 : 0;
        $readabilityScore += count($content['faq'] ?? []) > 0 ? 5 : 0;

        return [
            'seo_score' => min(100, $seoScore),
            'readability_score' => min(100, $readabilityScore),
        ];
    }
}
