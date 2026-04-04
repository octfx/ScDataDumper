<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

use Octfx\ScDataDumper\DocumentTypes\RadarSystemSharedParams;
use Octfx\ScDataDumper\DocumentTypes\DamageResistanceMacro;
use Octfx\ScDataDumper\DocumentTypes\MeleeCombatConfig;
use Octfx\ScDataDumper\DocumentTypes\Mining\MineableParams;
use Octfx\ScDataDumper\DocumentTypes\MiningLaserGlobalParams;
use Octfx\ScDataDumper\DocumentTypes\Loadout\LoadoutEntry;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RuntimeException;

class EntityClassDefinition extends RootDocument
{
    public function getAttachDef(): ?Element
    {
        return $this->get('Components/SAttachableComponentParams/AttachDef');
    }

    public function getClassification(): ?string
    {
        return ServiceFactory::getItemClassifierService()->classify($this);
    }

    public function getAttachType(): ?string
    {
        return $this->get('Components/SAttachableComponentParams/AttachDef@Type');
    }

    public function getAttachSubType(): ?string
    {
        return $this->get('Components/SAttachableComponentParams/AttachDef@SubType');
    }

    public function getTagList(): array
    {
        $tags = $this->get('Components/SAttachableComponentParams/AttachDef@Tags', '') ?? '';

        return array_filter(array_map(static fn ($tag) => trim($tag), explode(' ', $tags)));
    }

    public function getRequiredTagList(): array
    {
        $tags = $this->get('Components/SAttachableComponentParams/AttachDef@RequiredTags', '') ?? '';

        return array_filter(array_map(static fn ($tag) => trim($tag), explode(' ', $tags)));
    }

    public function getMineableParams(): ?MineableParams
    {
        $params = $this->getHydratedDocument('Components/MineableParams', MineableParams::class);

        return $params instanceof MineableParams ? $params : null;
    }

    public function getMagazineReference(): ?string
    {
        return $this->get('Components/SCItemWeaponComponentParams@ammoContainerRecord');
    }

    public function getMagazine(): ?self
    {
        $magazine = $this->resolveRelatedDocument(
            'Components/SCItemWeaponComponentParams/Magazine',
            self::class,
            $this->getMagazineReference(),
            static fn (string $reference): ?self => ServiceFactory::getItemService()->getByReference($reference)
        );

        return $magazine instanceof self ? $magazine : null;
    }

    public function getAmmoParamsReference(): ?string
    {
        return $this->get('Components/SAmmoContainerComponentParams@ammoParamsRecord');
    }

    public function getAmmoParams(): ?AmmoParams
    {
        $ammoParams = $this->resolveRelatedDocument(
            'Components/SAmmoContainerComponentParams/ammoParams',
            AmmoParams::class,
            $this->getAmmoParamsReference(),
            static fn (string $reference): ?AmmoParams => ServiceFactory::getAmmoParamsService()->getByReference($reference)
        );

        return $ammoParams instanceof AmmoParams ? $ammoParams : null;
    }

    public function getSecondaryAmmoParamsReference(): ?string
    {
        return $this->get('Components/SAmmoContainerComponentParams@secondaryAmmoParamsRecord');
    }

    public function getSecondaryAmmoParams(): ?AmmoParams
    {
        $ammoParams = $this->resolveRelatedDocument(
            'Components/SAmmoContainerComponentParams/secondaryAmmoParams',
            AmmoParams::class,
            $this->getSecondaryAmmoParamsReference(),
            static fn (string $reference): ?AmmoParams => ServiceFactory::getAmmoParamsService()->getByReference($reference)
        );

        return $ammoParams instanceof AmmoParams ? $ammoParams : null;
    }

    public function getRadarSystemReference(): ?string
    {
        return $this->get('Components/SCItemRadarComponentParams@sharedParams');
    }

    public function getRadarSystem(): ?RadarSystemSharedParams
    {
        $radar = $this->resolveRelatedDocument(
            'Components/SCItemRadarComponentParams/RadarSystem',
            RadarSystemSharedParams::class,
            $this->getRadarSystemReference(),
            static fn (string $reference): ?RadarSystemSharedParams => ServiceFactory::getFoundryLookupService()
                ->getRadarSystemParamsByReference($reference)
        );

        return $radar instanceof RadarSystemSharedParams ? $radar : null;
    }

    public function getMiningLaserGlobalParamsReference(): ?string
    {
        return $this->get('Components/SEntityComponentMiningLaserParams@globalParams');
    }

    public function getMiningLaserGlobalParams(): ?MiningLaserGlobalParams
    {
        $globalParams = $this->resolveRelatedDocument(
            'Components/SEntityComponentMiningLaserParams/MiningLaserGlobalParams',
            MiningLaserGlobalParams::class,
            $this->getMiningLaserGlobalParamsReference(),
            static fn (string $reference): ?MiningLaserGlobalParams => ServiceFactory::getFoundryLookupService()
                ->getMiningLaserGlobalParamsByReference($reference)
        );

        return $globalParams instanceof MiningLaserGlobalParams ? $globalParams : null;
    }

    public function getMeleeCombatConfigReference(): ?string
    {
        return $this->get('Components/SMeleeWeaponComponentParams@meleeCombatConfig');
    }

    public function getMeleeCombatConfig(): ?MeleeCombatConfig
    {
        $config = $this->resolveRelatedDocument(
            'Components/SMeleeWeaponComponentParams/MeleeCombatConfig',
            MeleeCombatConfig::class,
            $this->getMeleeCombatConfigReference(),
            static fn (string $reference): ?MeleeCombatConfig => ServiceFactory::getFoundryLookupService()
                ->getMeleeCombatConfigByReference($reference)
        );

        return $config instanceof MeleeCombatConfig ? $config : null;
    }

    public function getDamageResistanceReference(): ?string
    {
        return $this->get('Components/SCItemSuitArmorParams@damageResistance');
    }

    public function getDamageResistance(): ?DamageResistanceMacro
    {
        $damageResistance = $this->resolveRelatedDocument(
            'Components/SCItemSuitArmorParams/DamageResistance',
            DamageResistanceMacro::class,
            $this->getDamageResistanceReference(),
            static fn (string $reference): ?DamageResistanceMacro => ServiceFactory::getFoundryLookupService()
                ->getDamageResistanceMacroByReference($reference)
        );

        return $damageResistance instanceof DamageResistanceMacro ? $damageResistance : null;
    }

    public function getInventoryContainerReference(): ?string
    {
        return $this->get('Components/SCItemInventoryContainerComponentParams@containerParams')
            ?? $this->get('Components/VehicleComponentParams@inventoryContainerParams');
    }

    public function getInventoryContainer(): ?InventoryContainer
    {
        $container = $this->resolveRelatedDocument(
            'Components/SCItemInventoryContainerComponentParams/inventoryContainer',
            InventoryContainer::class,
            $this->getInventoryContainerReference(),
            static fn (string $reference): ?InventoryContainer => ServiceFactory::getInventoryContainerService()
                ->getByReference($reference)
        );

        return $container instanceof InventoryContainer ? $container : null;
    }

    public function getManufacturerReference(): ?string
    {
        return $this->get('Components/VehicleComponentParams@manufacturer')
            ?? $this->get('Components/VehicleComponentParams@Manufacturer')
            ?? $this->get('Components/SAttachableComponentParams/AttachDef@Manufacturer');
    }

    public function getManufacturer(): ?SCItemManufacturer
    {
        $manufacturer = $this->resolveRelatedDocument(
            'Components/SAttachableComponentParams/AttachDef/Manufacturer',
            SCItemManufacturer::class,
            $this->getManufacturerReference(),
            static fn (string $reference): ?SCItemManufacturer => ServiceFactory::getManufacturerService()
                ->getByReference($reference)
        ) ?? $this->resolveRelatedDocument(
            'Components/VehicleComponentParams/Manufacturer',
            SCItemManufacturer::class,
            $this->getManufacturerReference(),
            static fn (string $reference): ?SCItemManufacturer => ServiceFactory::getManufacturerService()
                ->getByReference($reference)
        );

        return $manufacturer instanceof SCItemManufacturer ? $manufacturer : null;
    }

    /**
     * @return list<LoadoutEntry>
     */
    public function getDefaultLoadoutEntries(): array
    {
        $manualEntriesNode = $this->get('Components/SEntityComponentDefaultLoadoutParams/loadout/SItemPortLoadoutManualParams/entries');

        if ($manualEntriesNode instanceof Element) {
            $manualEntries = LoadoutEntry::fromEntriesNode($manualEntriesNode, $this->isReferenceHydrationEnabled());

            if ($manualEntries !== []) {
                return $manualEntries;
            }
        }

        foreach ($this->getAll('Components/SEntityComponentDefaultLoadoutParams/loadout/SItemPortLoadoutXMLParams') as $xmlParamsNode) {
            if (! $xmlParamsNode instanceof Element) {
                continue;
            }

            $path = $xmlParamsNode->get('@loadoutPath');

            if (! is_string($path) || $path === '') {
                continue;
            }

            try {
                $loadout = ServiceFactory::getLoadoutFileService()->getByLoadoutPath($path);
            } catch (RuntimeException) {
                return [];
            }

            if ($loadout instanceof Loadout) {
                return $loadout->getEntries();
            }
        }

        return [];
    }

    /**
     * @return list<LoadoutEntry>
     */
}
