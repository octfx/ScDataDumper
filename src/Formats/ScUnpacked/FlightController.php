<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class FlightController extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemFlightControllerParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $component = $this->get();
        if (! $component instanceof Element) {
            return null;
        }

        $recallParams = $component->get('ShipRecall/ShipRecallParams');
        $collisionDetection = $component->get('./collisionDetection');

        return [
            'RecallParams' => $recallParams?->attributesToArray([
                'AIModuleTag',
            ], true),
            'CollisionDetection' => $collisionDetection?->attributesToArray(pascalCase: true),
        ];
    }
}
