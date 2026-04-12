<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Resource;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\DocumentTypes\MissionLocationTemplate;
use Octfx\ScDataDumper\DocumentTypes\ResourceType;
use Octfx\ScDataDumper\DocumentTypes\Starmap\StarMapObject;
use Octfx\ScDataDumper\Services\FoundryLookupService;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class CommodityTradeLocationResolver
{
    /**
     * @var array<string, list<string>> normalizedResourceTypeKey → tag UUIDs
     */
    private array $commodityTagsByNormalizedKey = [];

    /**
     * @var array<string, string> normalizedResourceTypeKey → resourceType UUID
     */
    private array $uuidByNormalizedKey = [];

    /**
     * @var array<string, string> normalizedResourceTypeKey → resourceType className
     */
    private array $classNameByNormalizedKey = [];

    /**
     * @var list<array{uuid: string, className: string, displayName: ?string, disabled: bool, producesPositiveTags: list<string>, consumesPositiveTags: list<string>, generalTags: list<string>}>
     */
    private array $tradeLocations = [];

    /**
     * @var array<string, string> starmap className → starmap UUID
     */
    private array $starmapByClassName = [];

    /**
     * @var array<string, string> normalized translated SMO name → starmap UUID
     */
    private array $starmapByNormalizedName = [];

    /**
     * @var array<string, string> trade location UUID → resolved starmap UUID
     */
    private array $resolvedStarmapUuids = [];

    public function __construct()
    {
        $this->buildCommodityTagIndex();
        $this->buildTradeLocationIndex();
        $this->buildStarmapIndex();
        $this->resolveStarmapLinks();
    }

    /**
     * @return list<array{CommodityUUID: string, CommodityKey: string, CommodityName: string, SoldAt: list<array{TradeLocationUUID: string, TradeLocationClassName: string, TradeLocationDisplayName: ?string, StarmapObjectUUID: ?string, MatchedTagUUID: string, MatchedTagName: string}>, BoughtAt: list<array{TradeLocationUUID: string, TradeLocationClassName: string, TradeLocationDisplayName: ?string, StarmapObjectUUID: ?string, MatchedTagUUID: string, MatchedTagName: string}>}>
     */
    public function resolveAll(): array
    {
        $tagService = ServiceFactory::getTagDatabaseService();
        $results = [];

        foreach ($this->commodityTagsByNormalizedKey as $normalizedKey => $tagUuids) {
            $commodityUuid = $this->uuidByNormalizedKey[$normalizedKey];
            $commodityClassName = $this->classNameByNormalizedKey[$normalizedKey];
            $commodityName = $tagService->getTagName($tagUuids[0]) ?? $commodityClassName;

            $expandedTags = $tagService->expandTagsWithAncestors($tagUuids);
            $expandedNames = [];
            foreach ($expandedTags as $expandedUuid) {
                $name = $tagService->getTagName($expandedUuid);
                if ($name !== null) {
                    $expandedNames[strtolower($name)] = $expandedUuid;
                }
            }

            $soldAt = [];
            $boughtAt = [];

            foreach ($this->tradeLocations as $location) {
                $starmapUuid = $this->resolvedStarmapUuids[$location['uuid']] ?? null;

                foreach ($location['producesPositiveTags'] as $produceTag) {
                    $produceName = $tagService->getTagName($produceTag);

                    if ($produceName !== null && isset($expandedNames[strtolower($produceName)])) {
                        $soldAt[] = [
                            'TradeLocationUUID' => $location['uuid'],
                            'TradeLocationClassName' => $location['className'],
                            'TradeLocationDisplayName' => $location['displayName'],
                            'StarmapObjectUUID' => $starmapUuid,
                            'MatchedTagUUID' => $produceTag,
                            'MatchedTagName' => $produceName,
                        ];
                        break;
                    }
                }

                foreach ($location['consumesPositiveTags'] as $consumeTag) {
                    $consumeName = $tagService->getTagName($consumeTag);

                    if ($consumeName !== null && isset($expandedNames[strtolower($consumeName)])) {
                        $boughtAt[] = [
                            'TradeLocationUUID' => $location['uuid'],
                            'TradeLocationClassName' => $location['className'],
                            'TradeLocationDisplayName' => $location['displayName'],
                            'StarmapObjectUUID' => $starmapUuid,
                            'MatchedTagUUID' => $consumeTag,
                            'MatchedTagName' => $consumeName,
                        ];
                        break;
                    }
                }
            }

            $results[] = [
                'CommodityUUID' => $commodityUuid,
                'CommodityKey' => $commodityClassName,
                'CommodityName' => $commodityName,
                'SoldAt' => $soldAt,
                'BoughtAt' => $boughtAt,
            ];
        }

        return $results;
    }

    private function buildCommodityTagIndex(): void
    {
        $lookup = ServiceFactory::getFoundryLookupService();

        $entityIndex = $this->buildCommodityEntityIndex($lookup);

        foreach ($lookup->getDocumentType('ResourceType', ResourceType::class) as $resourceType) {
            $rtClassName = $resourceType->getClassName();
            $normalizedKey = $this->normalizeResourceTypeKey($rtClassName);

            $entity = $entityIndex[$normalizedKey] ?? null;
            if ($entity === null) {
                continue;
            }

            $tagUuids = $entity->getEntityTagReferences();
            if ($tagUuids === []) {
                continue;
            }

            $this->commodityTagsByNormalizedKey[$normalizedKey] = array_map('strtolower', $tagUuids);
            $this->uuidByNormalizedKey[$normalizedKey] = $resourceType->getUuid();
            $this->classNameByNormalizedKey[$normalizedKey] = $rtClassName;
        }
    }

    /**
     * @return array<string, EntityClassDefinition>
     */
    private function buildCommodityEntityIndex(FoundryLookupService $lookup): array
    {
        $index = [];

        foreach ($lookup->getDocumentType('EntityClassDefinition', EntityClassDefinition::class) as $entity) {
            $path = strtolower($entity->getPath());
            if (! str_contains($path, 'commodit')) {
                continue;
            }

            $className = strtolower($entity->getClassName());
            if ($className !== '' && ! isset($index[$className])) {
                $index[$className] = $entity;
            }
        }

        return $index;
    }

    private function buildTradeLocationIndex(): void
    {
        $lookup = ServiceFactory::getFoundryLookupService();
        $localization = ServiceFactory::getLocalizationService();

        foreach ($lookup->getDocumentType('MissionLocationTemplate', MissionLocationTemplate::class) as $template) {
            if (! $template->hasTradeTags()) {
                continue;
            }

            $producesPositive = $template->getProducesPositiveTagReferences();
            $consumesPositive = $template->getConsumesPositiveTagReferences();

            if ($producesPositive === [] && $consumesPositive === []) {
                continue;
            }

            $displayName = $template->getDisplayName();
            if ($displayName !== null) {
                $displayName = $localization->translateValue($displayName);
            }

            $this->tradeLocations[] = [
                'uuid' => $template->getUuid(),
                'className' => $template->getClassName(),
                'displayName' => $displayName,
                'disabled' => $template->isDisabled(),
                'producesPositiveTags' => $producesPositive,
                'consumesPositiveTags' => $consumesPositive,
                'generalTags' => $template->getGeneralTagReferences(),
            ];
        }
    }

    private function buildStarmapIndex(): void
    {
        $lookup = ServiceFactory::getFoundryLookupService();
        $localization = ServiceFactory::getLocalizationService();

        foreach ($lookup->getDocumentType('StarMapObject', StarMapObject::class) as $object) {
            $className = strtolower($object->getClassName());
            if (! isset($this->starmapByClassName[$className])) {
                $this->starmapByClassName[$className] = $object->getUuid();
            }

            $smoName = $object->getName();
            if ($smoName !== null) {
                $translated = $localization->translateValue($smoName);
                if ($translated !== null) {
                    $normalizedName = $this->normalizeKey($translated);
                    if ($normalizedName !== '' && ! isset($this->starmapByNormalizedName[$normalizedName])) {
                        $this->starmapByNormalizedName[$normalizedName] = $object->getUuid();
                    }
                }
            }
        }
    }

    private function resolveStarmapLinks(): void
    {
        $mltUuidByFacilityTag = [];

        foreach ($this->tradeLocations as $location) {
            $mltUuid = $location['uuid'];

            $starmapUuid = $this->resolveByDisplayName($location);
            if ($starmapUuid !== null) {
                $this->resolvedStarmapUuids[$mltUuid] = $starmapUuid;
            }

            foreach ($location['generalTags'] as $generalTag) {
                $mltUuidByFacilityTag[strtolower($generalTag)][] = $mltUuid;
            }
        }

        foreach ($mltUuidByFacilityTag as $mltUuids) {
            $anyResolved = null;
            foreach ($mltUuids as $mltUuid) {
                if (isset($this->resolvedStarmapUuids[$mltUuid])) {
                    $anyResolved = $this->resolvedStarmapUuids[$mltUuid];
                    break;
                }
            }

            if ($anyResolved !== null) {
                foreach ($mltUuids as $mltUuid) {
                    if (! isset($this->resolvedStarmapUuids[$mltUuid])) {
                        $this->resolvedStarmapUuids[$mltUuid] = $anyResolved;
                    }
                }
            }
        }

        foreach ($this->tradeLocations as $location) {
            $mltUuid = $location['uuid'];
            if (isset($this->resolvedStarmapUuids[$mltUuid])) {
                continue;
            }

            $className = strtolower($location['className']);
            $stripped = $className;
            if (str_starts_with($stripped, 'outpost_')) {
                $stripped = substr($stripped, 8);
            }

            if (isset($this->starmapByClassName[$stripped])) {
                $this->resolvedStarmapUuids[$mltUuid] = $this->starmapByClassName[$stripped];
            }
        }
    }

    private function resolveByDisplayName(array $location): ?string
    {
        $displayName = $location['displayName'];
        if ($displayName === null) {
            return null;
        }

        $normalized = $this->normalizeKey($displayName);
        if ($normalized === '') {
            return null;
        }

        return $this->starmapByNormalizedName[$normalized] ?? null;
    }

    private function normalizeResourceTypeKey(string $className): string
    {
        $key = strtolower($className);

        if (str_starts_with($key, 'ore_')) {
            $key = substr($key, 4).'_ore';
        } elseif (str_starts_with($key, 'raw_')) {
            $key = substr($key, 4).'_raw';
        }

        return $key;
    }

    private function normalizeKey(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $value));
    }
}
