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

        $mass = $this->normalizeMass($this->get('mass'));

        $part = [
            'Name' => $this->get('name'),
            'Parts' => [],
            'Port' => (new VehiclePartPort($this->get('/ItemPort')))->toArray(),
            'MaximumDamage' => $this->getDamageMax() > 0 ? $this->getDamageMax() : null,
            'Mass' => $mass,
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

    /**
     * Normalise mass values that occasionally include stray characters
     * (e.g. bidi markers or trailing letters like "501.51S"). Returns
     * a float on success, otherwise null.
     */
    private function normalizeMass(mixed $raw): float|int|null
    {
        if (is_null($raw) || $raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            return (float) $raw;
        }

        if (is_string($raw)) {
            $clean = preg_replace('/[^0-9+\\-eE\\.]/u', '', $raw);

            if (is_numeric($clean)) {
                if ($clean !== $raw) {
                    error_log(sprintf('Normalised part mass "%s" -> "%s" for part "%s"', $raw, $clean, $this->get('name')));
                }

                return (float) $clean;
            }
        }

        error_log(sprintf('Unable to parse part mass "%s" for part "%s"', is_scalar($raw) ? $raw : gettype($raw), $this->get('name')));

        return null;
    }
}
