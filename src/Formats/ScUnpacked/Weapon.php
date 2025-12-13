<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Exception;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class Weapon extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemWeaponComponentParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $weapon = $this->get();

        $ammunition = new Ammunition($this->item);
        $ammunitionArray = $ammunition->toArray();

        $out = [
            'Size' => $this->item->get('Components/SAttachableComponentParams/AttachDef@Size'),
            'WeaponType' => $this->item->get('Components/SAttachableComponentParams/AttachDef@Type'),
            'WeaponClass' => $this->item->get('Components/SAttachableComponentParams/AttachDef@SubType'),
            'EffectiveRange' => $this->resolveEffectiveRange($weapon, $ammunitionArray),
            'RateOfFire' => null,
            'Capacity' => is_array($ammunitionArray) ? ($ammunitionArray['Capacity'] ?? null) : null,
            'Magazine' => $this->buildMagazine(),
            'Ammunition' => $ammunitionArray,
            'Attachments' => $this->buildAttachments(),
            'Modes' => [],
            'Consumption' => new WeaponConsumption($weapon)->toArray(),
            'Knife' => new MeleeWeapon($this->item),
        ];

        $damageReducer = static fn ($carry, $cur) => $carry + $cur;

        foreach ($weapon->get('/fireActions')?->children() as $action) {
            $mode = new WeaponMode($action);
            if (! $mode->canTransform()) {
                continue;
            }

            $mode = $mode->toArray();

            $impact = is_array($ammunitionArray['ImpactDamage'] ?? null)
                ? array_reduce($ammunitionArray['ImpactDamage'], $damageReducer, 0)
                : 0;

            $detonation = is_array($ammunitionArray['DetonationDamage'] ?? null)
                ? array_reduce($ammunitionArray['DetonationDamage'], $damageReducer, 0)
                : 0;

            $mode['DamagePerShot'] = ($impact + $detonation) * ($mode['PelletsPerShot'] ?? 0);
            $mode['DamagePerSecond'] = $mode['DamagePerShot'] * (($mode['RoundsPerMinute'] ?? 0) / 60.0);

            $out['RateOfFire'] ??= $mode['RoundsPerMinute'] ?? null;

            $out['Modes'][] = $mode;
        }

        // Calculate capacity from regen consumption if not available
        $consumption = $out['Consumption'];
        if (empty($out['Capacity']) && ! empty($consumption['RequestedAmmoLoad']) && ! empty($consumption['CostPerBullet'])) {
            $out['Capacity'] = (int) floor($consumption['RequestedAmmoLoad'] / $consumption['CostPerBullet']);
        }

        return $out;
    }

    private function resolveEffectiveRange(Element $weapon, ?array $ammunition): ?float
    {
        $range = $weapon->get('effectiveRange');

        return $range ?? $ammunition['Range'] ?? null;
    }

    private function buildMagazine(): array
    {
        $magazine = $this->item->get('Components/SCItemWeaponComponentParams/Magazine/Components/SAmmoContainerComponentParams');

        if ($magazine === null) {
            return [];
        }

        return [
            'InitialAmmoCount' => $magazine->get('initialAmmoCount', 0),
            'MaxAmmoCount' => $magazine->get('maxAmmoCount', 0),
        ];
    }

    private function buildAttachments(): array
    {
        $entries = $this->item->get('Components/SEntityComponentDefaultLoadoutParams/loadout/SItemPortLoadoutManualParams/entries');

        if ($entries === null) {
            return [];
        }

        $attachments = [];
        $itemService = \Octfx\ScDataDumper\Services\ServiceFactory::getItemService();

        foreach ($entries->children() as $entry) {
            $portName = $entry->get('@itemPortName');
            $className = $entry->get('@entityClassName');

            if (empty($portName)) {
                continue;
            }

            $uuid = null;
            if (! empty($className)) {
                try {
                    $item = $itemService->getByClassName($className);
                    $uuid = $item?->getUuid();
                } catch (Exception $e) {
                    // Item not found or error loading - leave UUID as null
                }
            }

            $attachments[] = array_filter([
                'UUID' => $uuid,
                'Port' => $portName,
                'ClassName' => $className ?: null,
            ]);
        }

        return $attachments;
    }
}
