<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Recovery;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

/**
 * Represents the global ItemRecoveryConfigurationParams record.
 *
 * Parsed from `libs/foundry/records/entitlementpolicies/globalitemrecovery.xml`.
 * Defines which item types are insurable, bricked, or force-transferred,
 * and per-ship-class cost/cooldown multiplier overrides.
 *
 * Root element: `ItemRecoveryConfigurationParams.GlobalItemRecovery`
 */
final class ItemRecoveryConfigurationParams extends RootDocument
{
    /**
     * Recovery categories that map to top-level XML element names.
     */
    private const array CATEGORIES = [
        'Insurable',
        'ReplenishOnRearm',
        'ReplenishOnClaimAndRepair',
        'NeverRepair',
        'NeverLTP',
        'ForceLTP',
        'DontEquipWhenBricked',
    ];

    /**
     * Parsed type/subType pairs per category.
     * Populated lazily on first access.
     *
     * @var array<string, list<array{type: string, subType: string}>>
     */
    private array $categories = [];

    /**
     * Parsed economy params.
     *
     * @var array{globalBrickTimer: int, aUECperSecond: float, claimCostMultiplier: float, defaultLoadoutCooldownMultiplier: float}|null
     */
    private ?array $economyParams = null;

    /**
     * Parsed cooldown override groups.
     *
     * @var list<array{multiplier: float, classReferences: list<string>}>|null
     */
    private ?array $cooldownOverrides = null;

    /**
     * Parsed cost override groups.
     *
     * @var list<array{multiplier: float, classReferences: list<string>}>|null
     */
    private ?array $costOverrides = null;

    /**
     * Check if a given type (and optional subType) matches the named recovery category.
     *
     * A category entry with `subType="UNDEFINED"` matches any subType of that type.
     * A category entry with a specific subType only matches that exact subType.
     */
    public function matchesCategory(string $category, string $type, ?string $subType = null): bool
    {
        $entries = $this->getCategoryEntries($category);

        foreach ($entries as $entry) {
            if ($entry['type'] !== $type) {
                continue;
            }

            // UNDEFINED subType in the config means "match any subType"
            if ($entry['subType'] === 'UNDEFINED') {
                return true;
            }

            // Specific subType match
            if ($subType !== null && $entry['subType'] === $subType) {
                return true;
            }
        }

        return false;
    }

    public function isInsurable(string $type, ?string $subType = null): bool
    {
        return $this->matchesCategory('Insurable', $type, $subType);
    }

    public function isForceLTP(string $type, ?string $subType = null): bool
    {
        return $this->matchesCategory('ForceLTP', $type, $subType);
    }

    public function isNeverLTP(string $type, ?string $subType = null): bool
    {
        return $this->matchesCategory('NeverLTP', $type, $subType);
    }

    public function isDontEquipWhenBricked(string $type, ?string $subType = null): bool
    {
        return $this->matchesCategory('DontEquipWhenBricked', $type, $subType);
    }

    public function isReplenishOnRearm(string $type, ?string $subType = null): bool
    {
        return $this->matchesCategory('ReplenishOnRearm', $type, $subType);
    }

    public function isReplenishOnClaimAndRepair(string $type, ?string $subType = null): bool
    {
        return $this->matchesCategory('ReplenishOnClaimAndRepair', $type, $subType);
    }

    public function isNeverRepair(string $type, ?string $subType = null): bool
    {
        return $this->matchesCategory('NeverRepair', $type, $subType);
    }

    /**
     * Look up the cooldown multiplier for a ship class UUID.
     *
     * @return float|null The multiplier, or null if no override exists (defaults to 1.0).
     */
    public function getCooldownMultiplierForClass(string $classUuid): ?float
    {
        return $this->getOverrideMultiplier($this->getCooldownOverrides(), $classUuid);
    }

    /**
     * Look up the cost multiplier for a ship class UUID.
     *
     * @return float|null The multiplier, or null if no override exists (defaults to 1.0).
     */
    public function getCostMultiplierForClass(string $classUuid): ?float
    {
        return $this->getOverrideMultiplier($this->getCostOverrides(), $classUuid);
    }

    /**
     * Get the global brick timer in seconds.
     */
    public function getGlobalBrickTimer(): int
    {
        return $this->getEconomyParams()['globalBrickTimer'];
    }

    /**
     * Get all economy parameters.
     *
     * @return array{globalBrickTimer: int, aUECperSecond: float, claimCostMultiplier: float, defaultLoadoutCooldownMultiplier: float}
     */
    public function getEconomyParams(): array
    {
        if ($this->economyParams !== null) {
            return $this->economyParams;
        }

        $node = $this->get('economyParams');

        $this->economyParams = [
            'globalBrickTimer' => (int) ($node?->get('@globalBrickTimer') ?? 0),
            'aUECperSecond' => (float) ($node?->get('@aUECperSecond') ?? 0),
            'claimCostMultiplier' => (float) ($node?->get('@claimCostMultiplier') ?? 0),
            'defaultLoadoutCooldownMultiplier' => (float) ($node?->get('@defaultLoadoutCooldownMultiplier') ?? 0),
        ];

        return $this->economyParams;
    }

    /**
     * Get the full list of parsed category entries for a given category name.
     *
     * @return list<array{type: string, subType: string}>
     */
    public function getCategoryEntries(string $category): array
    {
        if (isset($this->categories[$category])) {
            return $this->categories[$category];
        }

        $entries = [];

        foreach ($this->getAll($category.'/ItemRecoveryCondition_ItemType') as $node) {
            $entries[] = [
                'type' => $node->get('@type') ?? '',
                'subType' => $node->get('@subType') ?? 'UNDEFINED',
            ];
        }

        $this->categories[$category] = $entries;

        return $entries;
    }

    /**
     * Get all category names.
     *
     * @return list<string>
     */
    public function getCategoryNames(): array
    {
        return self::CATEGORIES;
    }

    /**
     * Get all cooldown override groups.
     *
     * @return list<array{multiplier: float, classReferences: list<string>}>
     */
    public function getCooldownOverrides(): array
    {
        if ($this->cooldownOverrides !== null) {
            return $this->cooldownOverrides;
        }

        $this->cooldownOverrides = $this->parseOverrideGroups('economyParams/cooldownOverrides/ItemRecoveryOverrideGroupDef');

        return $this->cooldownOverrides;
    }

    /**
     * Get all cost override groups.
     *
     * @return list<array{multiplier: float, classReferences: list<string>}>
     */
    public function getCostOverrides(): array
    {
        if ($this->costOverrides !== null) {
            return $this->costOverrides;
        }

        $this->costOverrides = $this->parseOverrideGroups('economyParams/costOverrides/ItemRecoveryOverrideGroupDef');

        return $this->costOverrides;
    }

    /**
     * Parse override group elements from a given XPath.
     *
     * @return list<array{multiplier: float, classReferences: list<string>}>
     */
    private function parseOverrideGroups(string $path): array
    {
        $groups = [];

        foreach ($this->getAll($path) as $groupNode) {
            $refs = [];

            $classesNode = $groupNode->get('classes');

            if ($classesNode !== null) {
                foreach ($classesNode->children() as $child) {
                    $ref = $child->get('@value');
                    if (is_string($ref) && $ref !== '') {
                        $refs[] = $ref;
                    }
                }
            }

            $groups[] = [
                'multiplier' => (float) ($groupNode->get('@multiplier') ?? 1.0),
                'classReferences' => $refs,
            ];
        }

        return $groups;
    }

    /**
     * Look up a multiplier from override groups by class UUID.
     *
     * @param  list<array{multiplier: float, classReferences: list<string>}>  $groups
     */
    private function getOverrideMultiplier(array $groups, string $classUuid): ?float
    {
        foreach ($groups as $group) {
            if (in_array($classUuid, $group['classReferences'], true)) {
                return $group['multiplier'];
            }
        }

        return null;
    }
}
