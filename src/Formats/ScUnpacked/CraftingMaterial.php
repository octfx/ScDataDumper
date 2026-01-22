<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class CraftingMaterial extends BaseFormat
{
    /**
     * @var string|null The UUID to resolve (if not provided via Element with __ref attribute)
     */
    private ?string $uuid = null;

    public function __construct(RootDocument|Element|\DOMNode|null|string $item)
    {
        if (is_string($item)) {
            $this->uuid = $item;
            $this->item = null;
        } else {
            parent::__construct($item);
        }
    }

    public function toArray(): ?array
    {
        // Get UUID from constructor parameter or from Element's __ref attribute
        $uuid = $this->uuid;
        if ($uuid === null && $this->item !== null) {
            $uuid = $this->item->get('@__ref');
        }

        if ($uuid === null) {
            return null;
        }

        $item = ServiceFactory::getItemService()->getByReference($uuid);

        if ($item === null) {
            return null;
        }

        $data = [
            'UUID' => $item->getUuid(),
            'ClassName' => $item->getClassName(),
            'Classification' => $item->getClassification(),
        ];

        $attachDef = $item->getAttachDef();

        if ($attachDef !== null) {
            $data['Name'] = $attachDef->get('Localization/English@Name');
            $data['Description'] = $attachDef->get('Localization/English@Description');
            $data['Type'] = $attachDef->get('Type');
            $data['SubType'] = $attachDef->get('SubType');
            $data['Size'] = $attachDef->get('Size');
        }

        // Resolve manufacturer
        $manufacturerUuid = $attachDef?->get('Manufacturer');
        if ($manufacturerUuid !== null) {
            $manufacturer = ServiceFactory::getManufacturerService()->getByReference($manufacturerUuid);
            if ($manufacturer !== null && $manufacturer->get('Localization@__Name') !== '@LOC_PLACEHOLDER') {
                $data['Manufacturer'] = [
                    'Code' => $manufacturer->getCode(),
                    'Name' => $manufacturer->get('Localization/English@Name'),
                    'UUID' => $manufacturer->getUuid(),
                ];
            }
        }

        return $this->removeNullValues($data);
    }
}
