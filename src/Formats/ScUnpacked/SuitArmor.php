<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

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
        foreach ($armor->get('protectedBodyParts')?->children() ?? [] as $part) {
            $protectedParts[] = $part->get('@value');
        }

        $signatures = [];
        foreach ($armor->get('signatureParams')?->children() ?? [] as $part) {
            if ($part->getNode()->nodeName !== 'ItemSuitArmorSignatureParams') {
                continue;
            }

            $signatures[$part->get('@signatureType')] = $part->get('@signatureEmission');
        }

        $damageResistanceMacro = $this->item?->getDamageResistance();

        return [
            'DamageResistance' => $damageResistanceMacro?->documentElement
                ? new DamageResistance($damageResistanceMacro->documentElement)
                : null,
            'ProtectedBodyParts' => $protectedParts,
            'Signature' => $signatures,
        ];
    }
}
