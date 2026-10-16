<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use DOMNode;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Helper\Element;

final class DamageResistance extends BaseFormat
{
    protected ?string $elementKey = './damageResistance';

    public function __construct(DOMNode|Element|RootDocument|null $item, ?string $key = './damageResistance')
    {
        parent::__construct($item);

        $this->elementKey = $key;
    }

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $resistance = $this->get();

        $resistances = [];
        foreach (self::$resistanceKeys as $key) {
            $resistances[$key] = [
                'Multiplier' => $resistance->get($key.'Resistance@Multiplier'),
                'Threshold' => $resistance->get($key.'Resistance@Threshold'),
            ];
        }

        return [
            'Impact' => $this->item->get('impactForceResistance@impactForceResistance'),
            ...$resistances,
        ];
    }
}
