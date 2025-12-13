<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

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

        return [
            ...$ordnance->attributesToArray([
                'maxArmableOverride',
                'projectileProximity',
                'dragAreaRadius',
                'centreOfPressureOffsetY',
                'maxAltitudeForAudioRtpc',
            ], true),
            'GCS' => new GCSParams($this->item),
            'IsCluster' => $isCluster,
            'Cluster' => $clusterData,
            'Targeting' => new TargetingParams($this->item),
            'Damage' => new Damage($ordnance->get('explosionParams/damage/DamageInfo')),
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
