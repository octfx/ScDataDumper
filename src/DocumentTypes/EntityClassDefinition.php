<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

use Octfx\ScDataDumper\DocumentTypes\Loadout\LoadoutEntry;
use Octfx\ScDataDumper\DocumentTypes\Mining\MineableParams;
use Octfx\ScDataDumper\Helper\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RuntimeException;

class EntityClassDefinition extends RootDocument
{
    private const int RS_SIGNATURE_INDEX = 4;

    public function hasIntegratedSeat(): bool
    {
        return $this->get('Components/SCItemSeatParams') !== null;
    }

    public function hasEMPParams(): bool
    {
        return $this->get('Components/SCItemEMPParams') !== null;
    }

    public function isPlayerOperableSeat(): bool
    {
        return $this->hasIntegratedSeat() && $this->get("Components/SActorUsableParams/useSlots/UsableUseSlot[@available='1']/useChannelInstances/UsableUseChannelInstance[@isEnabled='1']/alignmentSlots/OperatorSeatAlignmentSlot[@available='1' and @canPlayerUse='1']") !== null;
    }

    public function requiresRemoteTurretView(): bool
    {
        return (string) $this->get('Components/SCItemTurretParams/remoteTurret/SCItemTurretRemoteParams@turretOnlyUsableInRemoteCamera') === '1';
    }

    public function hasRemoteTurretArticulation(): bool
    {
        $turret = $this->get('Components/SCItemTurretParams');

        if ($turret?->get('remoteTurret/SCItemTurretRemoteParams') === null) {
            return false;
        }

        $yaw = 'yawAxis/SCItemTurretJointMovementAxisParams';
        $pitch = 'pitchAxis/SCItemTurretJointMovementAxisParams';
        $joint = 'SCItemTurretJointMovementParams';

        return $turret->get("movementList/{$joint}[$pitch and normalize-space(@jointName) and @jointName != ../{$joint}[$yaw and normalize-space(@jointName)]/@jointName]") !== null;
    }

    public function hasTurretParameters(): bool
    {
        return $this->get('Components/SCItemTurretParams') !== null;
    }

    public function hasDoorParameters(): bool
    {
        return $this->get('Components/SCItemDoorParams') !== null;
    }

    public function hasFixedWeaponMountGeometry(): bool
    {
        return $this->get('Components/SGeometryResourceParams/Geometry/Geometry/Geometry[contains(@path, "/weapon_mounts/fixed/")]') !== null;
    }

    /** @return array<string, list<array{Tag: string, Grants: array, Targets: array}>> */
    public function getPortControlBindings(): array
    {
        $bindings = [];

        foreach ($this->get('Components/SItemPortContainerComponentParams/Ports')?->children() ?? [] as $port) {
            $tag = (string) $port->get('@controllableTag');
            $grants = $targets = [];

            if ($tag !== '') {
                // Default and conditional priorities cannot prove a route between installed ports.
                $groups = $port->get('control/SCItemControlSeatParams/userControl/SCItemControllableGroupParams/priorityGroups');

                foreach ($groups?->children() ?? [] as $group) {
                    if ($group->get('self::SCItemPriorityGroupParam[not(@*[name() != "itemType" and name() != "defaultPriority"]) and count(*) = 1]') === null) {
                        continue;
                    }

                    $type = strtolower((string) $group->get('@itemType'));

                    foreach ($group->get('tags')?->children() ?? [] as $target) {
                        if ($target->get('self::SCItemPriorityTagParam[count(@*) = 2 and @tag and not(*) and @priority >= 0 and @priority < 255]') !== null) {
                            $grants[$type][$target->get('@tag')] = true;
                        }
                    }
                }

                $tables = $port->get('control/SCItemControlPriorities/itemControlPriorities');

                foreach ($tables?->children() ?? [] as $table) {
                    if ($table->get('self::SCItemControlPriorityTable[count(@*) = 1 and @itemType and count(*) = 1]') === null) {
                        continue;
                    }

                    $type = strtolower((string) $table->get('@itemType'));

                    foreach ($table->get('tagPrioritiesTable')?->children() ?? [] as $target) {
                        if ($target->get('self::SCItemControlPriorityTag[count(@*) = 1 and @tag and not(*)]') !== null) {
                            $targets[$type][$target->get('@tag')] = true;
                        }
                    }
                }
            }

            $bindings[$port->get('@Name')][] = ['Tag' => $tag, 'Grants' => $grants, 'Targets' => $targets];
        }

        return $bindings;
    }

    public function getAttachDef(): ?Element
    {
        return $this->get('Components/SAttachableComponentParams/AttachDef');
    }

    public function getRadarSignatureByIndex(int $index): ?float
    {
        $signatureParams = $this->get(
            'Components/SSCSignatureSystemParams/radarProperties/SSCRadarContactProperites/baseSignatureParams/SSCSignatureSystemBaseSignatureParams'
        );

        if (! $signatureParams instanceof Element) {
            return null;
        }

        foreach ($signatureParams->get('signatures')?->children() ?? [] as $signatureIndex => $signature) {
            if ($signatureIndex !== $index) {
                continue;
            }

            $value = $signature->get('@value');

            return is_numeric($value) ? (float) $value : null;
        }

        return null;
    }

    public function getRsSignature(): ?float
    {
        return $this->getRadarSignatureByIndex(self::RS_SIGNATURE_INDEX);
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

        return collect(explode(' ', $tags))
            ->map(static fn ($tag) => trim($tag))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<string> Tag UUIDs from the top-level <tags> element.
     */
    public function getEntityTagReferences(): array
    {
        return $this->queryAttributeValues('tags/Reference', 'value');
    }

    public function getRequiredTagList(): array
    {
        $tags = $this->get('Components/SAttachableComponentParams/AttachDef@RequiredTags', '') ?? '';

        return collect(explode(' ', $tags))
            ->map(static fn ($tag) => trim($tag))
            ->filter()
            ->values()
            ->all();
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

    /**
     * Whether this item (e.g. an ammo-feeder backpack) carries an SCItemAmmoContainerFeederComponentParams that feeds an external ammo container.
     *
     * @since 4.9.0 (cds_combat_superheavy_backpack)
     */
    public function hasAmmoContainerFeeder(): bool
    {
        return $this->get('Components/SCItemAmmoContainerFeederComponentParams') !== null;
    }

    public function getAmmoFeedMagazineReference(): ?string
    {
        return $this->get('Components/SCItemAmmoContainerFeederComponentParams@ammoContainerRecord');
    }

    /**
     * The magazine/ammo-container entity this feeder points at,
     * or null when the item has no feeder or the reference cannot be resolved.
     *
     * @since 4.9.0
     */
    public function getAmmoFeedMagazine(): ?self
    {
        $reference = $this->getAmmoFeedMagazineReference();

        if ($reference === null || ! ServiceFactory::isInitialized()) {
            return null;
        }

        $magazine = ServiceFactory::getItemService()->getByReference($reference);

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

    public function getGrade(): ?string
    {
        $grade = $this->getAttachDef()?->get('@Grade');

        return $grade !== null ? (string) $grade : null;
    }

    public function getDisplayName(): ?string
    {
        $attachDef = $this->getAttachDef();

        return $attachDef?->get('Localization/English@Name')
            ?? $attachDef?->get('Localization@Name');
    }

    public static function extractClassNameFromPath(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        return pathinfo($path, PATHINFO_FILENAME);
    }

    public static function resolveFromCraftingCost(Element $cost): ?self
    {
        $inputEntity = $cost->get('InputEntity');

        if ($inputEntity instanceof Element) {
            $entity = self::fromNode($inputEntity->getNode());

            if ($entity instanceof self) {
                return $entity;
            }
        }

        $reference = $cost->get('@entityClass');

        return is_string($reference) && $reference !== ''
            ? ServiceFactory::getItemService()->getByReference($reference)
            : null;
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
     * Entity mass in kg from the rigid physics controller.
     */
    public function getMass(): float
    {
        return (float) $this->get(
            'Components/SEntityPhysicsControllerParams/PhysType/SEntityRigidPhysicsControllerParams@Mass',
            0,
        );
    }

    /**
     * Item size from the AttachDef.
     */
    public function getAttachSize(): ?int
    {
        $size = $this->getAttachDef()?->get('@Size');

        return $size !== null ? (int) $size : null;
    }

    /**
     * Resolve the "Online" state element from ItemResourceComponentParams.
     *
     * Centralises the find-by-name iteration that was duplicated across
     * Item.php, ResourceNetwork.php, and QuantumDrive.php.
     */
    public function getResourceOnlineState(): ?Element
    {
        $states = $this->get('Components/ItemResourceComponentParams/states');

        if ($states === null) {
            return null;
        }

        foreach ($states->children() as $state) {
            if ($state->get('@name') === 'Online') {
                return $state;
            }
        }

        return null;
    }
}
