<?php

namespace Octfx\ScDataDumper\Services;

use JsonException;
use Octfx\ScDataDumper\Services\Mining\MineableService;
use RuntimeException;

final class ServiceFactory
{
    private static bool $initialized = false;

    private static ?string $activeScDataPath = null;

    private static ?string $activeJsonOutPath = null;

    /**
     * @var BaseService[]
     */
    private static array $services = [];

    public function __construct(
        private readonly string $scDataPath,
        private readonly ?string $jsonOutPath = null,
    ) {}

    /**
     * @throws JsonException
     */
    public function initialize(): void
    {
        $jsonOutPath = $this->jsonOutPath ?? $this->scDataPath;

        if (
            self::$initialized
            && self::$activeScDataPath === $this->scDataPath
            && self::$activeJsonOutPath === $jsonOutPath
        ) {
            return;
        }

        if (
            self::$activeScDataPath !== null
            && (
                self::$activeScDataPath !== $this->scDataPath
                || self::$activeJsonOutPath !== $jsonOutPath
            )
        ) {
            self::$services = [];
        }

        self::$activeScDataPath = $this->scDataPath;
        self::$activeJsonOutPath = $jsonOutPath;

        self::$initialized = true;
    }

    public static function reset(): void
    {
        self::$initialized = false;
        self::$activeScDataPath = null;
        self::$activeJsonOutPath = null;
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

    public static function getBlueprintService(): BlueprintService
    {
        return self::getService('BlueprintService');
    }

    public static function getTagDatabaseService(): TagDatabaseService
    {
        return self::getService('TagDatabaseService');
    }

    public static function getItemClassifierService(): ItemClassifierService
    {
        return self::getService('ItemClassifierService');
    }

    public static function getAmmoParamsService(): AmmoParamsService
    {
        return self::getService('AmmoParamsService');
    }

    public static function getVehicleService(): VehicleService
    {
        return self::getService('VehicleService');
    }

    public static function getFoundryLookupService(): FoundryLookupService
    {
        return self::getService('FoundryLookupService');
    }

    public static function getMineableService(): MineableService
    {
        return self::getService('MineableService');
    }

    public static function getLoadoutFileService(): LoadoutFileService
    {
        return self::getService('LoadoutFileService');
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
            'BlueprintService' => new BlueprintService(self::$activeScDataPath),
            'LocalizationService' => new LocalizationService(self::$activeScDataPath),
            'ItemClassifierService' => new ItemClassifierService,
            'TagDatabaseService' => new TagDatabaseService(self::$activeScDataPath),
            'AmmoParamsService' => new AmmoParamsService(self::$activeScDataPath),
            'VehicleService' => new VehicleService(self::$activeScDataPath),
            'FoundryLookupService' => new FoundryLookupService(self::$activeScDataPath),
            'MineableService' => new MineableService(self::$activeJsonOutPath, self::$activeScDataPath),
            'LoadoutFileService' => new LoadoutFileService(self::$activeScDataPath),
            default => throw new RuntimeException('Unknown service: '.$serviceName),
        };

        if ($service instanceof BaseService) {
            $service->initialize();
        }

        self::$services[$serviceName] = $service;
    }
}
