<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked\Concerns;

/**
 * Resolves the event/reward origin of an item from its class name and tags.
 *
 * Produces an `event_source` array of friendly labels, e.g. ["IAE", "Faction Reward"].
 * Returns an empty array for regular store items with no special origin.
 */
trait ResolvesEventSource
{
    /**
     * Match rules evaluated against the lowercased class name + tag string.
     *
     * Each rule: [needle, label]
     * - needle: underscore-prefixed to avoid false positives (e.g. "_iae")
     * - label:  friendly display string used directly in output
     *
     * Rules are evaluated independently; an item may receive multiple labels.
     * Order determines output order.
     */
    private const array EVENT_SOURCE_RULES = [
        '_luminalia' => 'Luminalia',
        '_iae' => 'IAE',
        '_invictus' => 'Invictus Launch Week',
        'redfestival' => 'Red Festival',
        'lunarnewyear' => 'Red Festival',
        '_unity' => 'Foundation Festival',
        '_reward_faction' => 'Faction Reward',
        '_contestedzone' => 'Contested Zone',
    ];

    private static array $eventSourceCache = [];

    /**
     * Resolve event/reward source labels from class name and tag list.
     *
     * @param  string  $className  The item's ClassName (e.g. "Paint_Cutter_Luminalia_2953_Green_Red")
     * @param  list<string>  $tags  The item's AttachDef Tags array
     * @return list<string> Friendly labels, e.g. ["IAE"], or [] for normal items
     */
    private static function resolveEventSource(string $className, array $tags): array
    {
        $key = strtolower($className);

        if (isset(self::$eventSourceCache[$key])) {
            return self::$eventSourceCache[$key];
        }

        $haystack = $key.' '.strtolower(implode(' ', $tags));
        $result = [];

        foreach (self::EVENT_SOURCE_RULES as $needle => $label) {
            if (str_contains($haystack, $needle)) {
                $result[] = $label;
            }
        }

        self::$eventSourceCache[$key] = $result;

        return $result;
    }

    public static function resetEventSourceCache(): void
    {
        self::$eventSourceCache = [];
    }
}
