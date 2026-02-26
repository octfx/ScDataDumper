<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

use Illuminate\Support\Arr;
use Octfx\ScDataDumper\Services\ItemClassifierService;

final class ItemTypeResolver
{
    private const array CLASSIFIER_PREFIXES = ['Ship.', 'FPS.', 'Mining.'];

    private readonly ItemClassifierService $itemClassifierService;

    public function __construct(?ItemClassifierService $itemClassifierService = null)
    {
        $this->itemClassifierService = $itemClassifierService ?? new ItemClassifierService;
    }

    public function resolveSemanticType(?array $payload): ?string
    {
        $item = $this->extractItem($payload);

        if ($item === null) {
            return null;
        }

        $stdType = $this->normalizeString(Arr::get($item, 'stdItem.Type'));
        if ($stdType !== null) {
            return $stdType;
        }

        $inlineType = $this->buildSemanticType($item['type'] ?? null, $item['subType'] ?? null);
        if ($inlineType !== null) {
            return $inlineType;
        }

        $attachType = $this->buildSemanticType(
            Arr::get($item, 'Components.SAttachableComponentParams.AttachDef.Type'),
            Arr::get($item, 'Components.SAttachableComponentParams.AttachDef.SubType')
        );

        if ($attachType !== null) {
            return $attachType;
        }

        $legacyType = $this->normalizeString($item['Type'] ?? null);

        if ($legacyType !== null && ! self::isClassifierShaped($legacyType)) {
            return $legacyType;
        }

        return null;
    }

    public function resolveClassifier(?array $payload): ?string
    {
        $item = $this->extractItem($payload);

        if ($item === null) {
            return null;
        }

        $classification = $this->normalizeString($item['classification'] ?? null);
        if ($classification !== null) {
            return $classification;
        }

        $legacyClassification = $this->normalizeString($item['Classification'] ?? null);
        if ($legacyClassification !== null) {
            return $legacyClassification;
        }

        $legacyType = $this->normalizeString($item['Type'] ?? null);
        if ($legacyType !== null && self::isClassifierShaped($legacyType)) {
            return $legacyType;
        }

        $classified = $this->itemClassifierService->classify($item);

        return $this->normalizeString($classified);
    }

    public static function isClassifierShaped(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        foreach (self::CLASSIFIER_PREFIXES as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function extractItem(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $installedItem = $payload['InstalledItem'] ?? null;
        if (is_array($installedItem)) {
            return $installedItem;
        }

        return $payload;
    }

    private function buildSemanticType(mixed $type, mixed $subType): ?string
    {
        $majorType = $this->normalizeString($type);

        if ($majorType === null) {
            return null;
        }

        $minorType = $this->normalizeString($subType);

        if ($minorType === null) {
            return $majorType;
        }

        return $majorType.'.'.$minorType;
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
