<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Resource;

final class QualityTierResolver
{
    public function extractCategoryFromPath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $segments = explode('/', $normalized);

        for ($i = 0; $i < count($segments) - 1; $i++) {
            if ($segments[$i] === 'qualitydistribution' && isset($segments[$i + 1])) {
                $candidate = strtolower($segments[$i + 1]);

                if (! str_contains($candidate, '.')) {
                    return $candidate;
                }
            }
        }

        return '';
    }

    public function extractTierFromClassName(string $className, string $category): string
    {
        $lower = strtolower($className);

        if (str_starts_with($lower, 'common')) {
            return 'common';
        }

        if (str_starts_with($lower, 'uncommon')) {
            return 'uncommon';
        }

        if (str_starts_with($lower, 'rare')) {
            return 'rare';
        }

        if (str_starts_with($lower, 'epic')) {
            return 'epic';
        }

        if (str_starts_with($lower, 'legendary')) {
            return 'legendary';
        }

        return 'default';
    }
}
