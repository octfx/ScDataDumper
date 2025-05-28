<?php

namespace Octfx\ScDataDumper\Services;

use JsonException;
use RuntimeException;

final class ServiceFactory
{
    private static bool $initialized = false;

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
        self::$services['InventoryContainerService'] = new InventoryContainerService($this->scDataPath);
        self::$services['InventoryContainerService']->initialize();

        self::$services['ManufacturerService'] = new ManufacturerService($this->scDataPath);
        self::$services['ManufacturerService']->initialize();

        self::$services['ItemService'] = new ItemService($this->scDataPath);
        self::$services['ItemService']->initialize();

        self::$services['LocalizationService'] = new LocalizationService($this->scDataPath);
        self::$services['LocalizationService']->initialize();

        self::$services['ItemClassifierService'] = new ItemClassifierService;

        self::$services['RadarSystemService'] = new RadarSystemService($this->scDataPath);
        self::$services['RadarSystemService']->initialize();

        self::$services['AmmoParamsService'] = new AmmoParamsService($this->scDataPath);
        self::$services['AmmoParamsService']->initialize();

        self::$services['DamageResistanceMacroService'] = new DamageResistanceMacroService($this->scDataPath);
        self::$services['DamageResistanceMacroService']->initialize();

        self::$services['MeleeCombatConfigService'] = new MeleeCombatConfigService($this->scDataPath);
        self::$services['MeleeCombatConfigService']->initialize();

        self::$services['VehicleService'] = new VehicleService($this->scDataPath);
        self::$services['VehicleService']->initialize();

        self::$services['FactionService'] = new FactionService($this->scDataPath);
        self::$services['FactionService']->initialize();

        self::$services['LoadoutFileService'] = new LoadoutFileService($this->scDataPath);
        self::$services['LoadoutFileService']->initialize();

        self::$initialized = true;
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

    private static function getService(string $serviceName): mixed
    {
        if (! self::$initialized) {
            throw new RuntimeException('Service factory is not initialized');
        }

        if (! isset(self::$services[$serviceName])) {
            throw new RuntimeException('Unknown service: '.$serviceName);
        }

        return self::$services[$serviceName];
    }
}
