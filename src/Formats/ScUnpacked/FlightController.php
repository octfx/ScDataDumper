<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class FlightController extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemFlightControllerParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $recallParams = $this->get('ShipRecall/ShipRecallParams', local: true);
        $collisionDetection = $this->get('collisionDetection', local: true);

        return [
            'RecallParams' => $recallParams?->attributesToArray([
                'AIModuleTag',
            ], true),
            'CollisionDetection' => $collisionDetection?->attributesToArray(pascalCase: true),
        ];
    }
}
