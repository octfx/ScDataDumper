<?php

namespace Octfx\ScDataDumper\Services\Vehicle;

use Octfx\ScDataDumper\Services\ItemService;

final class SeatingAnalyzer implements VehicleDataCalculator
{
    private const string BED_TYPE_PREFIX = 'Bed_';

    /** @var list<string> */
    private const array NON_BED_PREFIXES = [
        'Console_',
        'ControlPanel_',
        'SCItemDisplayScreen_',
        'Cupboard_',
    ];

    private const array MED_BED_TIER_MAP = [
        'Hospital' => 'T1',
        'Clinic' => 'T2',
        'Ambulance' => 'T3',
    ];

    private const array TAG_SYNONYMS = [
        'Helm' => 'Helmsman',
        'Driver' => 'Helmsman',
        'CoHelm' => 'CoHelmsman',
        'GenericBridgeCrew' => 'Bridge',
    ];

    private const array TAG_PRIORITY = [
        'Helmsman',
        'CoHelmsman',
        'Captain',
        'Turret',
        'Engineering',
        'Bridge',
        'Science',
        'ATC',
        'SCAirTrafficControllerOperatorComponent',
        'Security',
        'Prisoner',
        'TractorBeam',
        'Passenger',
        'Comms',
        'Tactical',
        'Civilian',
        'Generic',
    ];

    private readonly ItemTypeResolver $itemTypeResolver;

    /** @var array<string, string|null> */
    private array $medicalTierCache = [];

    public function __construct(
        ?ItemTypeResolver $itemTypeResolver = null,
        private readonly ?ItemService $itemService = null,
    ) {
        $this->itemTypeResolver = $itemTypeResolver ?? new ItemTypeResolver;
    }

    public function canCalculate(VehicleDataContext $context): bool
    {
        return true;
    }

    public function calculate(VehicleDataContext $context): array
    {
        $walker = new StandardisedPartWalker;

        $seats = [];
        $escapePods = 0;
        $jumpSeats = 0;
        $loadoutBeds = [];

        foreach ($walker->walkItems($context->standardisedParts) as $entry) {
            $item = $entry['Item'];

            if (! is_array($item)) {
                continue;
            }

            $type = $this->extractMajorTypeToken($this->itemTypeResolver->resolveSemanticType($item));
            $portName = strtolower((string) ($entry['portName'] ?? ''));

            if ($type === 'usable' && $this->isJumpSeat($item)) {
                $jumpSeats++;

                continue;
            }

            if ($type === 'bed' || $this->isBedByClassName($item)) {
                $loadoutBeds[] = $this->buildBedEntry($item, (string) ($entry['portName'] ?? ''));

                continue;
            }

            if ($type === 'turretbase') {
                $seats[] = $this->buildSeatEntry($item, $portName);

                continue;
            }

            if ($type !== 'seat') {
                continue;
            }

            if (str_contains($portName, 'escape_pod')) {
                $escapePods++;

                continue;
            }

            $seats[] = $this->buildSeatEntry($item, $portName);
        }

        $beds = $this->resolveBeds($loadoutBeds, $context->socpakObjects);

        $result = [];

        $crewStations = count($seats);
        if ($crewStations > 0) {
            $result['CrewStations'] = $crewStations;
        }

        $ejectionCount = count(array_filter($seats, static fn (array $s) => ! empty($s['HasEjection'])));
        if ($ejectionCount > 0) {
            $result['EjectionSeats'] = $ejectionCount;
        }

        $bedCount = count($beds);
        if ($bedCount > 0) {
            $result['TotalBeds'] = $bedCount;
        }

        $medicalTiers = [];
        foreach ($beds as $bed) {
            if (! ($bed['IsMedical'] ?? false)) {
                continue;
            }
            $tier = $bed['MedicalTier'] ?? 'Unknown';
            $medicalTiers[$tier] = ($medicalTiers[$tier] ?? 0) + 1;
        }

        if ($medicalTiers !== []) {
            ksort($medicalTiers);
            $result['MedicalBeds'] = array_map(
                static fn (string $tier, int $count) => ['tier' => $tier, 'count' => $count],
                array_keys($medicalTiers),
                array_values($medicalTiers),
            );
        }

        if ($escapePods > 0) {
            $result['EscapePods'] = $escapePods;
        }

        if ($jumpSeats > 0) {
            $result['JumpSeats'] = $jumpSeats;
        }

        if ($seats !== []) {
            $result['Seats'] = $seats;
        }

        if ($beds !== []) {
            $result['Beds'] = $beds;
        }

        return ['Seating' => $result];
    }

    public function getPriority(): int
    {
        return 40;
    }

    private function buildSeatEntry(array $item, string $portName): array
    {
        $seatData = $item['stdItem']['Seat'] ?? [];
        $hasEjection = (bool) ($seatData['HasEjection'] ?? false);

        $entry = [
            'HardpointName' => $portName,
            'ClassName' => $item['stdItem']['ClassName'] ?? $item['className'] ?? '',
            'Role' => $this->classifySeatRole($item),
        ];

        if (isset($seatData['SeatType']) && $seatData['SeatType'] !== '') {
            $entry['SeatType'] = $seatData['SeatType'];
        }

        if ($hasEjection) {
            $entry['HasEjection'] = true;
        }

        return $entry;
    }

    private function classifySeatRole(array $item): string
    {
        return $this->classifySeatRoleByTags($item) ?? 'Unknown';
    }

    private function classifySeatRoleByTags(array $item): ?string
    {
        $tagMap = $item['entity_tag_map'] ?? [];

        if ($tagMap === []) {
            return null;
        }

        $tagNames = array_map(static fn (array $t) => $t['name'] ?? '', $tagMap);

        foreach (self::TAG_PRIORITY as $tagName) {
            if (in_array($tagName, $tagNames, true)) {
                return self::TAG_SYNONYMS[$tagName] ?? $tagName;
            }
        }

        return null;
    }

    private function isJumpSeat(array $item): bool
    {
        $className = strtolower($item['className'] ?? '');

        return str_contains($className, 'jump') || str_contains($className, 'drop') || str_contains($className, 'passenger');
    }

    private function extractMajorTypeToken(?string $semanticType): ?string
    {
        if ($semanticType === null || $semanticType === '') {
            return null;
        }

        [$majorType] = explode('.', $semanticType, 2);
        $majorType = strtolower(trim($majorType));

        return $majorType === '' ? null : $majorType;
    }

    private function isBedByClassName(array $item): bool
    {
        $className = $item['className'] ?? $item['stdItem']['ClassName'] ?? '';

        return str_starts_with($className, 'Bed_') || stripos($className, 'bed_') === 0;
    }

    private function buildBedEntry(array $item, string $portName): array
    {
        $className = $item['stdItem']['ClassName'] ?? $item['className'] ?? '';
        $tier = $this->getMedicalTier($className);

        return [
            'HardpointName' => $portName,
            'ClassName' => $className,
            'BedType' => $this->classifyBedType($className),
            'IsMedical' => $tier !== null,
            'MedicalTier' => $tier,
        ];
    }

    private function buildBedEntryFromSocpak(SocpakObject $socpakBed): array
    {
        $className = $socpakBed->className;
        $tier = $this->getMedicalTier($className);

        return [
            'HardpointName' => $socpakBed->section,
            'ClassName' => $className,
            'BedType' => $this->classifyBedType($className),
            'IsMedical' => $tier !== null,
            'MedicalTier' => $tier,
        ];
    }

    /**
     * @param  list<SocpakObject>  $socpakObjects
     */
    private function resolveBeds(array $loadoutBeds, array $socpakObjects): array
    {
        if ($loadoutBeds !== []) {
            return $loadoutBeds;
        }

        $socpakBeds = array_values(array_filter(
            $socpakObjects,
            fn (SocpakObject $object): bool => $this->isSocpakBed($object->className),
        ));

        return array_map(fn (SocpakObject $bed): array => $this->buildBedEntryFromSocpak($bed), $socpakBeds);
    }

    private function isSocpakBed(string $type): bool
    {
        if (str_starts_with($type, self::BED_TYPE_PREFIX)) {
            return true;
        }

        return str_contains($type, 'Med_Bed') && ! $this->isNonBedType($type);
    }

    private function isNonBedType(string $type): bool
    {
        return array_any(self::NON_BED_PREFIXES, fn ($prefix) => str_starts_with($type, $prefix));
    }

    private function classifyBedType(string $className): string
    {
        $lower = strtolower($className);

        if (str_contains($lower, 'medical') || str_contains($lower, 'med_bed') || str_contains($lower, 'medbed')) {
            return 'Medical';
        }

        if (str_contains($lower, 'bunk')) {
            return 'Bunk';
        }

        if (str_contains($lower, 'double')) {
            return 'Double';
        }

        if (str_contains($lower, 'capsule')) {
            return 'Capsule';
        }

        if (str_contains($lower, 'frameless')) {
            return 'Frameless';
        }

        return 'Single';
    }

    private function getMedicalTier(string $className): ?string
    {
        if (isset($this->medicalTierCache[$className])) {
            return $this->medicalTierCache[$className];
        }

        $tier = $this->resolveMedicalTierFromEntity($className)
            ?? $this->resolveMedicalTierFromClassName($className);

        $this->medicalTierCache[$className] = $tier;

        return $tier;
    }

    private function resolveMedicalTierFromEntity(string $className): ?string
    {
        if ($this->itemService === null) {
            return null;
        }

        $entity = $this->itemService->getByClassName($className);

        if ($entity === null) {
            return null;
        }

        $tier = $entity->get('Components/MedBedComponentParams/@medBedTier');

        if ($tier !== null && $tier !== '') {
            return self::MED_BED_TIER_MAP[(string) $tier] ?? (string) $tier;
        }

        return null;
    }

    private function resolveMedicalTierFromClassName(string $className): ?string
    {
        $lower = strtolower($className);

        if (preg_match('/_t([123])_/i', $lower, $m)) {
            return 'T'.$m[1];
        }

        if (preg_match('/_tier_*([123])$/i', $lower, $m)) {
            return 'T'.$m[1];
        }

        return null;
    }
}
