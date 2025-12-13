<?php

namespace Octfx\ScDataDumper\ValueObjects;

/**
 * Configuration for health aggregation
 *
 * Defines which parts should be included in ship-level health calculations.
 */
final class HealthAggregationConfig
{
    /**
     * @param  bool  $skipItemPorts  Skip ItemPort parts with excluded flags
     * @param  string[]  $excludedPortFlags  Port flags to exclude (e.g. 'uneditable', 'invisible')
     */
    public function __construct(
        public bool $skipItemPorts = true,
        public array $excludedPortFlags = ['uneditable', '$uneditable', 'invisible']
    ) {}
}
