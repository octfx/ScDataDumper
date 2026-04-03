<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Generator;
use JsonException;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\AmmoParams;
use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use RuntimeException;

final class AmmoParamsService extends BaseService
{

    public function initialize(): void
    {
    }

    public function getByReference(?string $uuid): ?AmmoParams
    {
        return ServiceFactory::getFoundryLookupService()->getAmmoParamsByReference($uuid);
    }

    public function getByEntity(Element|EntityClassDefinition $item): ?AmmoParams
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
}
