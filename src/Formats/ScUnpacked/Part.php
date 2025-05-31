<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class Part extends BaseFormat
{
    protected ?string $elementKey = '';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $part = [
            'Name' => $this->get('name'),
            'Parts' => [],
            'Port' => (new VehiclePartPort($this->get('/ItemPort')))->toArray(),
            'MaximumDamage' => $this->getDamageMax() > 0 ? $this->getDamageMax() : null,
            'Mass' => $this->get('mass'),
            'ShipDestructionDamage' => $this->calculateDamageToDestroyShip(),
            'PartDetachDamage' => $this->calculateDamageToDetach(),
        ];

        foreach ($this->get('/Parts')?->children() ?? [] as $subPart) {
            if ($subPart->get('@skipPart') === '1') {
                continue;
            }

            $part['Parts'][] = (new Part($subPart))->toArray();
        }

        return $part;
    }

    private function calculateDamageToDestroyShip()
    {
        foreach ($this->get('/DamageBehaviors')?->children() ?? [] as $behavior) {
            if ($behavior->get('/Group@name') !== 'Destroy') {
                continue;
            }

            $ratio = $behavior->get('damageRatioMin');

            if ($ratio) {
                return $ratio * $this->getDamageMax();
            }

            return $this->getDamageMax();
        }

        return null;
    }

    private function calculateDamageToDetach(): float|int|null
    {
        if ($this->getDamageMax() === 0 || $this->get('detachRatio') === 0) {
            return null;
        }

        return $this->getDamageMax() * $this->get('detachRatio');
    }

    private function getDamageMax()
    {
        return $this->get('damageMax') ?? $this->get('damagemax') ?? 0;
    }

    public function canTransform(): bool
    {
        return $this->item->nodeName === 'Part';
    }
}
