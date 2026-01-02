<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Illuminate\Support\Arr;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;

class Missile extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemMissileParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $ordnance = $this->get();

        $cluster = new OrdnanceCluster($ordnance);
        $isCluster = $cluster->canTransform();
        $clusterData = $cluster->toArray();
        $clusterSize = $this->computeClusterSize();

        if ($isCluster) {
            $clusterData = $clusterData ?? [];
            $clusterData['Size'] = $clusterSize;
        } else {
            $clusterData = null;
        }

        $base = $ordnance->attributesToArray([
            'maxArmableOverride',
            'dragAreaRadius',
            'centreOfPressureOffsetY',
            'maxAltitudeForAudioRtpc',
        ], true);

        $damage = new Damage($ordnance->get('explosionParams/damage/DamageInfo'))->toArray();

        $damageTotal = array_reduce($damage, static fn ($carry, $item) => $carry + $item, 0.0);

        $gcs = new GCSParams($this->item)->toArray();

        $distance = Arr::get($base, 'MaxLifetime', 0.0) * Arr::get($gcs, 'LinearSpeed', 0.0);

        return [
            ...$base,
            'Distance' => ((bool) Arr::get($base, 'EnableLifetime', false)) ? $distance : null,
            'GCS' => $gcs,
            'IsCluster' => $isCluster,
            'Cluster' => $clusterData,
            'Targeting' => new TargetingParams($this->item),
            'Damage' => $damage,
            'DamageTotal' => $damageTotal,
            'ExplosionMinRadius' => $ordnance->get('explosionParams@minRadius'),
            'ExplosionMaxRadius' => $ordnance->get('explosionParams@maxRadius'),
        ];
    }

    private function computeClusterSize(): ?int
    {
        $ports = $this->item->get('Components/SItemPortContainerComponentParams/Ports');

        if (! $ports instanceof Element) {
            return null;
        }

        $count = 0;

        foreach ($ports->children() as $port) {
            foreach ($port->get('/Types')?->children() ?? [] as $portType) {
                $major = $portType->get('@Type') ?? $portType->get('@type');

                if ($major !== null && strcasecmp($major, 'Missile') === 0) {
                    $count++;
                    break;
                }
            }
        }

        return $count === 0 ? null : $count;
    }
}
