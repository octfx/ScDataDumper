<?php

namespace Octfx\ScDataDumper\Services;

use JsonException;
use RuntimeException;

final class ServiceFactory
{
    private static bool $initialized = false;

    private static ?string $activeScDataPath = null;

    /**
     * @var BaseService[]
     */
    private static array $services = [];

    public function __construct(private readonly string $scDataPath) {}

    /**
     * @throws JsonException
     */
    public function initialize(): void
    {
        if (self::$initialized && self::$activeScDataPath === $this->scDataPath) {
            return;
        }

        if (self::$activeScDataPath !== null && self::$activeScDataPath !== $this->scDataPath) {
            self::$services = [];
        }

        self::$activeScDataPath = $this->scDataPath;

        self::$initialized = true;
    }

    public static function reset(): void
    {
        self::$initialized = false;
        self::$activeScDataPath = null;
        self::$services = [];

        BaseService::resetSharedState();
        BlueprintService::resetDocumentCache();
        ItemService::resetDocumentCache();
        VehicleService::resetDocumentCache();
        ItemClassifierService::resetCache();
    }

    public static function getInventoryContainerService(): InventoryContainerService
    {
        return self::getService('InventoryContainerService');
    }

    public static function getManufacturerService(): ManufacturerService
    {
        return self::getService('ManufacturerService');
    }

    public static function getItemService(): ItemService
    {
        return self::getService('ItemService');
    }

    public static function getLocalizationService(): LocalizationService
    {
        return self::getService('LocalizationService');
    }

    public static function getResourceTypeService(): ResourceTypeService
    {
        return self::getService('ResourceTypeService');
    }

    public static function getBlueprintService(): BlueprintService
    {
        return self::getService('BlueprintService');
    }

    public static function getCraftingGameplayPropertyService(): CraftingGameplayPropertyService
    {
        return self::getService('CraftingGameplayPropertyService');
    }

    public static function getTagDatabaseService(): TagDatabaseService
    {
        return self::getService('TagDatabaseService');
    }

    public static function getItemClassifierService(): ItemClassifierService
    {
        return self::getService('ItemClassifierService');
    }

    public static function getRadarSystemService(): RadarSystemService
    {
        return self::getService('RadarSystemService');
    }

    public static function getAmmoParamsService(): AmmoParamsService
    {
        return self::getService('AmmoParamsService');
    }

    public static function getDamageResistanceMacroService(): DamageResistanceMacroService
    {
        return self::getService('DamageResistanceMacroService');
    }

    public static function getMeleeCombatConfigService(): MeleeCombatConfigService
    {
        return self::getService('MeleeCombatConfigService');
    }

    public static function getMiningLaserGlobalParamsService(): MiningLaserGlobalParamsService
    {
        return self::getService('MiningLaserGlobalParamsService');
    }

    public static function getVehicleService(): VehicleService
    {
        return self::getService('VehicleService');
    }

    public static function getFactionService(): FactionService
    {
        return self::getService('FactionService');
    }

    public static function getLoadoutFileService(): LoadoutFileService
    {
        return self::getService('LoadoutFileService');
    }

    public static function getConsumableSubtypeService(): ConsumableSubtypeService
    {
        return self::getService('ConsumableSubtypeService');
    }

    private static function getService(string $serviceName): mixed
    {
        if (! self::$initialized) {
            throw new RuntimeException('Service factory is not initialized');
        }

        self::bootService($serviceName);

        return self::$services[$serviceName];
    }

    private static function bootService(string $serviceName): void
    {
        if (isset(self::$services[$serviceName])) {
            return;
        }

        if ($serviceName !== 'ItemClassifierService' && self::$activeScDataPath === null) {
            throw new RuntimeException('Service factory path is not initialized');
        }

        $service = match ($serviceName) {
            'InventoryContainerService' => new InventoryContainerService(self::$activeScDataPath),
            'ManufacturerService' => new ManufacturerService(self::$activeScDataPath),
            'ItemService' => new ItemService(self::$activeScDataPath),
            'ResourceTypeService' => new ResourceTypeService(self::$activeScDataPath),
            'CraftingGameplayPropertyService' => new CraftingGameplayPropertyService(self::$activeScDataPath),
            'BlueprintService' => new BlueprintService(self::$activeScDataPath),
            'LocalizationService' => new LocalizationService(self::$activeScDataPath),
            'ItemClassifierService' => new ItemClassifierService,
            'TagDatabaseService' => new TagDatabaseService(self::$activeScDataPath),
            'RadarSystemService' => new RadarSystemService(self::$activeScDataPath),
            'AmmoParamsService' => new AmmoParamsService(self::$activeScDataPath),
            'DamageResistanceMacroService' => new DamageResistanceMacroService(self::$activeScDataPath),
            'MeleeCombatConfigService' => new MeleeCombatConfigService(self::$activeScDataPath),
            'MiningLaserGlobalParamsService' => new MiningLaserGlobalParamsService(self::$activeScDataPath),
            'VehicleService' => new VehicleService(self::$activeScDataPath),
            'FactionService' => new FactionService(self::$activeScDataPath),
            'LoadoutFileService' => new LoadoutFileService(self::$activeScDataPath),
            'ConsumableSubtypeService' => new ConsumableSubtypeService(self::$activeScDataPath),
            default => throw new RuntimeException('Unknown service: '.$serviceName),
        };

        if ($service instanceof BaseService) {
            $service->initialize();
        }

        self::$services[$serviceName] = $service;
    }
}
