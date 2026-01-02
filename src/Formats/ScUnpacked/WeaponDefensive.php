<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Services\ServiceFactory;

/**
 * Extracts countermeasure launcher data (WeaponDefensive) such as signatures and ammo info.
 */
final class WeaponDefensive extends BaseFormat
{
    protected ?string $elementKey = 'Components/SAttachableComponentParams/AttachDef@Type';

    public function toArray(): ?array
    {
        if (! $this->canTransform() || $this->item?->get($this->elementKey) !== 'WeaponDefensive') {
            return null;
        }

        $ammo = ServiceFactory::getAmmoParamsService()->getByEntity($this->item);
        if ($ammo === null) {
            return null;
        }

        /** @var Element|null $typeParams */
        $typeParams = $ammo->get('projectileParams/CounterMeasureProjectileParams/typeParams');
        if (! $typeParams instanceof Element) {
            return null;
        }

        $countermeasureNode = $this->firstChildElement($typeParams);
        if (! $countermeasureNode instanceof Element) {
            return null;
        }

        $type = $this->resolveType($countermeasureNode->nodeName);

        $signatures = [
            'Infrared' => $this->buildSignature($countermeasureNode, 'Infrared'),
            'Electromagnetic' => $this->buildSignature($countermeasureNode, 'Electromagnetic'),
            'CrossSection' => $this->buildSignature($countermeasureNode, 'CrossSection'),
            'Decibel' => $this->buildSignature($countermeasureNode, 'Decibel'),
        ];

        $data = [
            'Type' => $type,
            'Signatures' => $this->removeNullValues($signatures),
            'InitialCapacity' => $this->castNumeric($this->item->get('Components/SAmmoContainerComponentParams@initialAmmoCount')),
            'Capacity' => $this->castNumeric(
                $this->item->get('Components/SAmmoContainerComponentParams@maxAmmoCount')
                ?? $this->item->get('Components/SAmmoContainerComponentParams@maxRestockCount')
            ),
        ];

        return $this->removeNullValues($data);
    }

    private function firstChildElement(Element $element): ?Element
    {
        foreach ($element->children() as $child) {
            return $child;
        }

        return null;
    }

    private function resolveType(string $nodeName): ?string
    {
        if (str_ends_with($nodeName, 'Params')) {
            $nodeName = substr($nodeName, 0, -6);
        }

        return str_starts_with($nodeName, 'CounterMeasure')
            ? substr($nodeName, strlen('CounterMeasure'))
            : $nodeName;
    }

    private function buildSignature(Element $element, string $name): ?array
    {
        $start = $this->castNumeric($element->get('@Start'.$name));
        $end = $this->castNumeric($element->get('@End'.$name));

        if ($start === null && $end === null) {
            return null;
        }

        return $this->removeNullValues([
            'Start' => $start,
            'End' => $end,
        ]);
    }

    private function castNumeric(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return is_numeric($value) ? (float) $value : $value;
    }
}
