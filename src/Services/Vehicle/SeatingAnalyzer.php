<?php

namespace Octfx\ScDataDumper\Services\Vehicle;

use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;
use Octfx\ScDataDumper\Services\ItemService;

final class SeatingAnalyzer implements VehicleDataCalculator
{
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
        private readonly ?SocpakBedExtractor $bedExtractor = null,
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

            if ($type !== 'seat') {
                continue;
            }

            if (str_contains($portName, 'escape_pod')) {
                $escapePods++;

                continue;
            }

            $seats[] = $this->buildSeatEntry($item, $portName);
        }

        $beds = $this->resolveBeds($loadoutBeds, $context->entity);
        $summary = $this->buildSeatingSummary($seats, $escapePods, $jumpSeats, $beds);

        $result = [];
        if ($seats !== []) {
            $result['Seats'] = $seats;
        }

        if ($beds !== []) {
            $result['Beds'] = $beds;
        }

        if ($summary !== []) {
            $result['Summary'] = $summary;
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

    /**
     * @param  array<int, array{Role: string}>  $seats
     * @param  array<int, array{IsMedical: bool}>  $beds
     */
    private function buildSeatingSummary(array $seats, int $escapePods, int $jumpSeats, array $beds): array
    {
        $ejectionCount = 0;
        $roleCounts = [];

        foreach ($seats as $seat) {
            $role = $seat['Role'];
            $roleCounts[$role] = ($roleCounts[$role] ?? 0) + 1;
            if (! empty($seat['HasEjection'])) {
                $ejectionCount++;
            }
        }

        $summary = [];

        if (isset($roleCounts['Helmsman'])) {
            $summary['Helmsman'] = $roleCounts['Helmsman'];
        }

        if (isset($roleCounts['CoHelmsman'])) {
            $summary['CoHelmsman'] = $roleCounts['CoHelmsman'];
        }

        $stations = array_filter(
            $roleCounts,
            static fn (string $role) => ! in_array($role, ['Helmsman', 'CoHelmsman', 'Unknown'], true),
            ARRAY_FILTER_USE_KEY
        );

        if ($stations !== []) {
            ksort($stations);
            $summary['Stations'] = $stations;
        }

        if (isset($roleCounts['Unknown'])) {
            $summary['Unknown'] = $roleCounts['Unknown'];
        }

        if ($escapePods > 0) {
            $summary['EscapePods'] = $escapePods;
        }

        if ($jumpSeats > 0) {
            $summary['JumpSeats'] = $jumpSeats;
        }

        if ($ejectionCount > 0) {
            $summary['EjectionSeats'] = $ejectionCount;
        }

        $bedCount = count($beds);
        if ($bedCount > 0) {
            $summary['Beds'] = $bedCount;
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
            $summary['MedicalBeds'] = $medicalTiers;
        }

        return $summary;
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

    private function buildBedEntryFromSocpak(array $socpakBed): array
    {
        $className = $socpakBed['ClassName'];
        $tier = $this->getMedicalTier($className);

        return [
            'HardpointName' => $socpakBed['Section'],
            'ClassName' => $className,
            'BedType' => $this->classifyBedType($className),
            'IsMedical' => $tier !== null,
            'MedicalTier' => $tier,
        ];
    }

    /**
     * @return list<array{ClassName: string, InstanceName: string, Section: string, Layer: string|null}>
     */
    private function extractSocpakBeds(?VehicleDefinition $entity): array
    {
        if ($this->bedExtractor === null || $entity === null) {
            return [];
        }

        return $this->bedExtractor->extractBeds($entity);
    }

    private function resolveBeds(array $loadoutBeds, ?VehicleDefinition $entity): array
    {
        $socpakBeds = $this->extractSocpakBeds($entity);

        if ($socpakBeds !== []) {
            return array_map(fn (array $b) => $this->buildBedEntryFromSocpak($b), $socpakBeds);
        }

        return $loadoutBeds;
    }

    private function classifyBedType(string $className): string
    {
        $lower = strtolower($className);

        if (str_contains($lower, 'medical')) {
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
