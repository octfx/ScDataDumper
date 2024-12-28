<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class SuitArmor extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemSuitArmorParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $armor = $this->get();

        $protectedParts = [];
        foreach ($armor->get('/protectedBodyParts')?->children() ?? [] as $part) {
            $protectedParts[] = $part->get('value');
        }

        return [
            'DamageResistance' => new DamageResistance(ServiceFactory::getDamageResistanceMacroService()->getByReference($armor->get('damageResistance'))),
            'ProtectedBodyParts' => $protectedParts,
        ];
    }
}
