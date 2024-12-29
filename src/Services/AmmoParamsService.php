<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Generator;
use JsonException;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\AmmoParams;
use RuntimeException;

final class AmmoParamsService extends BaseService
{
    private array $ammoParams = [];

    /**
     * @throws JsonException
     */
    public function initialize(): void
    {
        $classes = json_decode(file_get_contents($this->classToPathMapPath), true, 512, JSON_THROW_ON_ERROR);

        $this->ammoParams = $classes['AmmoParams'] ?? [];
    }

    public function iterator(): Generator
    {
        foreach ($this->ammoParams as $path) {
            yield $this->load($path);
        }
    }

    public function getByReference(?string $uuid): ?AmmoParams
    {
        if ($uuid === null || ! isset(self::$uuidToPathMap[$uuid])) {
            return null;
        }

        return $this->load(self::$uuidToPathMap[$uuid]);
    }

    public function getByEntity(Element $item): ?AmmoParams
    {
        // If this is a weapon that contains its own ammo, or if it is a magazine, then it will have an SCAmmoContainerComponentParams component.
        $ammoRef = $item->get('Components/SAmmoContainerComponentParams@ammoParamsRecord');
        if ($ammoRef !== null) {
            return $this->getByReference($ammoRef);
        }

        // Otherwise if this is a weapon then SCItemWeaponComponentParams->ammoContainerRecord should be the reference of a magazine entity
        $magRef = $item->get('Components/SCItemWeaponComponentParams@ammoContainerRecord');
        if ($magRef === null) {
            return null;
        }

        $mag = ServiceFactory::getItemService()->getByReference($magRef);
        if ($mag === null) {
            return null;
        }

        // And the magazine's SAmmoContainerComponentParams will tell us about the ammo
        return $this->getByReference($mag->get('Components/SAmmoContainerComponentParams@ammoParamsRecord'));
    }

    public function load(string $filePath): ?AmmoParams
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException(sprintf('File %s does not exist or is not readable.', $filePath));
        }

        $ammoParams = new AmmoParams;
        $ammoParams->load($filePath);
        $ammoParams->checkValidity();

        return $ammoParams;
    }
}
