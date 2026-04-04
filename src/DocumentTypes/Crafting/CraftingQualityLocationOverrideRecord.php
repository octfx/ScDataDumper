<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Crafting;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\DocumentTypes\Starmap\StarMapObject;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class CraftingQualityLocationOverrideRecord extends RootDocument
{
    /**
     * @return list<array{
     *     locationReference: string,
     *     locationName: ?string,
     *     distribution: array{min: int, max: int, mean: int, stddev: int}
     * }>
     */
    public function getLocationOverrides(): array
    {
        $overrides = [];

        foreach (
            $this->getAll('locationOverride/CraftingQualityLocationOverride/locationOverrideList/CraftingQualityLocationOverrideEntry')
            as $entryNode
        ) {
            if (! $entryNode instanceof Element) {
                continue;
            }

            $locationReference = $entryNode->get('@location');
            if (! is_string($locationReference) || $locationReference === '') {
                continue;
            }

            $distribution = $this->buildDistribution($entryNode);
            if ($distribution === null) {
                continue;
            }

            $overrides[] = [
                'locationReference' => $locationReference,
                'locationName' => $this->resolveLocationName($locationReference),
                'distribution' => $distribution,
            ];
        }

        return $overrides;
    }

    /**
     * @return array{min: int, max: int, mean: int, stddev: int}|null
     */
    public function getDistributionForLocation(string $locationName): ?array
    {
        $normalizedNeedle = $this->normalizeLocationName($locationName);

        foreach ($this->getLocationOverrides() as $override) {
            $locationReference = $override['locationReference'];
            $locationNameFromRecord = $override['locationName'];

            if (array_any($this->buildLocationCandidates($locationReference, $locationNameFromRecord), fn($candidate) => $candidate === $normalizedNeedle)) {
                return $override['distribution'];
            }
        }

        return null;
    }

    /**
     * @return array{min: int, max: int, mean: int, stddev: int}|null
     */
    private function buildDistribution(Element $entryNode): ?array
    {
        $min = $entryNode->get('qualityDistribution/CraftingQualityDistributionNormal' .'@min');
        $max = $entryNode->get('qualityDistribution/CraftingQualityDistributionNormal' .'@max');
        $mean = $entryNode->get('qualityDistribution/CraftingQualityDistributionNormal' .'@mean');
        $stddev = $entryNode->get('qualityDistribution/CraftingQualityDistributionNormal' .'@stddev');

        if (! is_numeric($min) || ! is_numeric($max) || ! is_numeric($mean) || ! is_numeric($stddev)) {
            return null;
        }

        return [
            'min' => (int) $min,
            'max' => (int) $max,
            'mean' => (int) $mean,
            'stddev' => (int) $stddev,
        ];
    }

    private function resolveLocationName(string $locationReference): ?string
    {
        $location = ServiceFactory::getFoundryLookupService()->getStarMapObjectByReference($locationReference);

        if (! $location instanceof StarMapObject) {
            return null;
        }

        $name = $location->getName();
        if (is_string($name) && $name !== '') {
            return ServiceFactory::getLocalizationService()->getTranslation($name) ?? $name;
        }

        return $location->getClassName();
    }

    /**
     * @return list<string>
     */
    private function buildLocationCandidates(string $locationReference, ?string $locationName): array
    {
        $candidates = [];

        foreach ([$locationReference, $locationName] as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            $candidates[] = $this->normalizeLocationName($candidate);
        }

        $location = ServiceFactory::getFoundryLookupService()->getStarMapObjectByReference($locationReference);

        if ($location instanceof StarMapObject) {
            $candidates[] = $this->normalizeLocationName($location->getClassName());
            $candidates[] = $this->normalizeLocationName(preg_replace('/SolarSystem$/', '', $location->getClassName()) ?? '');

            $translatedName = $location->getName();
            if (is_string($translatedName) && $translatedName !== '') {
                $translatedName = ServiceFactory::getLocalizationService()->getTranslation($translatedName) ?? $translatedName;
                $candidates[] = $this->normalizeLocationName($translatedName);
            }

            $tagName = $location->getLocationHierarchyTagName();
            if (is_string($tagName) && $tagName !== '') {
                $candidates[] = $this->normalizeLocationName($tagName);
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function normalizeLocationName(string $locationName): string
    {
        return strtolower(trim($locationName));
    }
}
