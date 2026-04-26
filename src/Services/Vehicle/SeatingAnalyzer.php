<?php

namespace Octfx\ScDataDumper\Services\Vehicle;

final class SeatingAnalyzer implements VehicleDataCalculator
{
    private const TAG_SYNONYMS = [
        'Helm' => 'Helmsman',
        'Driver' => 'Helmsman',
        'CoHelm' => 'CoHelmsman',
        'GenericBridgeCrew' => 'Bridge',
    ];

    private const TAG_PRIORITY = [
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

    public function __construct(?ItemTypeResolver $itemTypeResolver = null)
    {
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
        $turretGunners = 0;

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

            if ($type === 'turretbase' && ! empty($item['stdItem']['Seat'])) {
                $turretGunners++;

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

        $summary = $this->buildSeatingSummary($seats, $escapePods, $jumpSeats, $turretGunners);

        $result = [];
        if ($seats !== []) {
            $result['Seats'] = $seats;
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
     */
    private function buildSeatingSummary(array $seats, int $escapePods, int $jumpSeats, int $turretGunners): array
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

        if ($turretGunners > 0) {
            $summary['TurretGunner'] = $turretGunners;
        }

        if ($jumpSeats > 0) {
            $summary['JumpSeats'] = $jumpSeats;
        }

        if ($ejectionCount > 0) {
            $summary['EjectionSeats'] = $ejectionCount;
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
}
