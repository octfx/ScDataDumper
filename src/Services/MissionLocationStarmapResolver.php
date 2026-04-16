<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Octfx\ScDataDumper\DocumentTypes\Starmap\StarMapObject;

final class MissionLocationStarmapResolver
{
    private bool $initialized = false;

    /**
     * @var array<string, string> strtolower(starmap className) → starmap UUID
     */
    private array $starmapByClassName = [];

    /**
     * @var array<string, string> normalized translated SMO name → starmap UUID
     */
    private array $starmapByNormalizedName = [];

    /**
     * @var array<string, string> MLT UUID → resolved StarMapObject UUID
     */
    private array $resolvedStarmapUuids = [];

    /**
     * @param  list<array{uuid: string, className: string, displayName: ?string, generalTags: list<string>}>  $locations
     */
    public function resolveAll(array $locations): void
    {
        $this->ensureInitialized();

        $mltUuidByFacilityTag = [];

        foreach ($locations as $location) {
            $mltUuid = $location['uuid'];

            $starmapUuid = $this->resolveByDisplayName($location['displayName']);
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

        foreach ($locations as $location) {
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

    public function getStarmapUuid(string $mltUuid): ?string
    {
        return $this->resolvedStarmapUuids[$mltUuid] ?? null;
    }

    private function ensureInitialized(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;

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

    private function resolveByDisplayName(?string $displayName): ?string
    {
        if ($displayName === null) {
            return null;
        }

        $normalized = $this->normalizeKey($displayName);
        if ($normalized === '') {
            return null;
        }

        return $this->starmapByNormalizedName[$normalized] ?? null;
    }

    private function normalizeKey(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $value));
    }
}
