<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
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

        $signatures = [];
        foreach ($armor->get('/signatureParams')?->children() ?? [] as /** @var Element $part */ $part) {
            if ($part->getNode()->nodeName !== 'ItemSuitArmorSignatureParams') {
                continue;
            }

            $signatures[] = [
                'Signature' => $part->get('signatureType'),
                'Emission' => $part->get('signatureEmission'),
            ];
        }

        $damageResistanceMacro = ServiceFactory::getDamageResistanceMacroService()->getByReference(
            $armor->get('@damageResistance')
        );

        return [
            'DamageResistance' => $damageResistanceMacro?->documentElement
                ? new DamageResistance($damageResistanceMacro->documentElement)
                : null,
            'ProtectedBodyParts' => $protectedParts,
            'Signature' => $signatures,
        ];
    }
}
