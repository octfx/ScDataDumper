<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked\Concerns;

use Octfx\ScDataDumper\Services\ServiceFactory;

/**
 * Resolves an item's event/reward origin.
 * Wiki overrides (via DataOverrideService) are authoritative; heuristic rules fill un-curated items.
 */
trait ResolvesEventSource
{
    /** Heuristic rules (needle => label) matched against lowercased class name + tags. */
    private const array EVENT_SOURCE_RULES = [
        '_luminalia' => 'Luminalia',
        '_iae' => 'IAE',
        '_invictus' => 'Invictus Launch Week',
        '_fleetweek_blue_gold' => 'Invictus Launch Week',
        '_bis' => 'Best In Show',
        '_showdown' => 'Best In Show',
        '_lovestruck' => 'Coramor',
        'stellafortuna' => 'Stella Fortuna',
        'alienweek' => 'Alien Week',
        'halloween' => 'Day of the Vara',
        'ghoulish' => 'Day of the Vara',
        'redfestival' => 'Red Festival',
        'lunarnewyear' => 'Red Festival',
        '_lunar_' => 'Red Festival',
        '_unity' => 'Foundation Festival',
        '_reward_faction' => 'Faction Reward',
        '_cfp' => 'Faction Reward',
        '_headhunters' => 'Faction Reward',
        '_contestedzone' => 'Contested Zone',
    ];

    private static array $eventSourceCache = [];

    /**
     * @param  list<string>  $tags
     * @return list<string>
     */
    private static function resolveEventSource(string $className, array $tags, ?string $uuid = null): array
    {
        $uuid = is_string($uuid) && $uuid !== '' ? $uuid : null;
        $cacheKey = $uuid !== null ? 'u:'.$uuid : 'c:'.strtolower($className);

        if (isset(self::$eventSourceCache[$cacheKey])) {
            return self::$eventSourceCache[$cacheKey];
        }

        $result = [];

        if ($uuid !== null) {
            $event = ServiceFactory::getDataOverrideService()->factsFor($uuid)['event'] ?? null;

            if (is_string($event) && $event !== '') {
                $result[] = $event;
            }
        }

        // Heuristics are a fallback for items the wiki doesn't cover.
        if ($result === []) {
            $haystack = strtolower($className).' '.strtolower(implode(' ', $tags));

            foreach (self::EVENT_SOURCE_RULES as $needle => $label) {
                if (str_contains($haystack, $needle) && ! in_array($label, $result, true)) {
                    $result[] = $label;
                }
            }
        }

        return self::$eventSourceCache[$cacheKey] = $result;
    }

    public static function resetEventSourceCache(): void
    {
        self::$eventSourceCache = [];
    }
}
