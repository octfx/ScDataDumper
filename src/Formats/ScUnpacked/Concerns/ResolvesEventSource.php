<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked\Concerns;

use Illuminate\Support\Str;

/**
 * Resolves an item's event/reward origin into friendly labels.
 *
 * Merges two sources: a curated lookup from Faucino (thanks!) in import/event_sources.csv and heuristic needle rules against the class name + tags.
 *
 * @see https://docs.google.com/spreadsheets/d/1bPvRVTf6e6_Tst2cR7UFuneiy25Z49q73FhOd3suS08/edit?pli=1&gid=0#gid=0
 */
trait ResolvesEventSource
{
    /** Canonical labels for known CSV event names; unknown values fall back to title case. */
    private const array EVENT_LABELS = [
        'concierge' => 'Concierge',
        'concierge concept' => 'Concierge Concept',
        'subscriber' => 'Subscriber',
        'best in show' => 'Best In Show',
        'alien week' => 'Alien Week',
        'monthly bundle' => 'Monthly Bundle',
        'pirate week' => 'Pirate Week',
        'day of the vara' => 'Day of the Vara',
        'limited' => 'Limited',
        'coramor' => 'Coramor',
        'stella fortuna' => 'Stella Fortuna',
        'invictus launch week' => 'Invictus Launch Week',
        'red festival' => 'Red Festival',
        'event reward' => 'Event Reward',
        'foundation fest' => 'Foundation Festival',
        'faction reward' => 'Faction Reward',
    ];

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

    private static ?array $eventSourceMap = null;

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

        $map = self::loadEventSourceMap();

        $result = [];
        if ($uuid !== null && isset($map['uuid'][$uuid])) {
            $result = $map['uuid'][$uuid];
        } elseif (isset($map['class'][strtolower($className)])) {
            $result = $map['class'][strtolower($className)];
        }

        $haystack = strtolower($className).' '.strtolower(implode(' ', $tags));
        foreach (self::EVENT_SOURCE_RULES as $needle => $label) {
            if (str_contains($haystack, $needle) && ! in_array($label, $result, true)) {
                $result[] = $label;
            }
        }

        return self::$eventSourceCache[$cacheKey] = $result;
    }

    /**
     * @return array{uuid: array<string, list<string>>, class: array<string, list<string>>}
     */
    private static function loadEventSourceMap(): array
    {
        if (self::$eventSourceMap !== null) {
            return self::$eventSourceMap;
        }

        $map = ['uuid' => [], 'class' => []];
        $path = dirname(__DIR__, 4).'/import/event_sources.csv';
        $handle = is_file($path) ? fopen($path, 'r') : false;

        if ($handle === false) {
            return self::$eventSourceMap = $map;
        }

        try {
            $isHeader = true;

            while (($row = fgetcsv($handle, length: 0, escape: '\\')) !== false) {
                if ($isHeader) {
                    $isHeader = false;

                    continue;
                }

                if (count($row) < 4) {
                    continue;
                }

                $rawEvent = trim($row[1]);
                $rawClass = trim($row[2]);
                $rawUuid = trim($row[3]);

                if ($rawEvent === '' || ! Str::isUuid($rawUuid)) {
                    continue;
                }

                $label = self::EVENT_LABELS[strtolower($rawEvent)] ?? ucwords(strtolower($rawEvent));

                $map['uuid'][$rawUuid][] = $label;

                if ($rawClass !== '') {
                    $map['class'][strtolower($rawClass)][] = $label;
                }
            }
        } finally {
            fclose($handle);
        }

        return self::$eventSourceMap = $map;
    }

    public static function resetEventSourceCache(): void
    {
        self::$eventSourceCache = [];
    }
}
