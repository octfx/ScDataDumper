<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use JsonException;

/**
 * Loads the wiki override tables (import/wiki_items.json, wiki_vehicles.json) and answers fact lookups by UUID
 */
final class DataOverrideService
{
    /** @var array<string, array<string, mixed>> uuid -> item facts */
    private static array $items = [];

    /** @var array<string, array<string, mixed>> uuid -> vehicle facts */
    private static array $vehicles = [];

    /** Set once files are read (or confirmed missing) */
    private static bool $loaded = false;

    /** Override paths (tests inject their own data; config survives reset). */
    private static ?string $itemsPath = null;

    private static ?string $vehiclesPath = null;

    /** Point the item facts lookup at a custom path. Null restores the default import/ location. */
    public static function useItemsPath(?string $path): void
    {
        self::$itemsPath = $path;
    }

    /** Point the vehicle facts lookup at a custom path. Null restores the default import/ location. */
    public static function useVehiclesPath(?string $path): void
    {
        self::$vehiclesPath = $path;
    }

    public static function reset(): void
    {
        self::$items = [];
        self::$vehicles = [];
        self::$loaded = false;
    }

    /** @return array<string, mixed> fact-bag, or [] if unknown */
    public function factsFor(string $uuid): array
    {
        $this->load();

        return self::$items[$uuid] ?? self::$vehicles[$uuid] ?? [];
    }

    private function load(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        self::$items = $this->readFile('wiki_items.json');
        self::$vehicles = $this->readFile('wiki_vehicles.json');
    }

    /** @return array<string, array<string, mixed>> */
    private function readFile(string $name): array
    {
        $path = match ($name) {
            'wiki_items.json' => self::$itemsPath,
            'wiki_vehicles.json' => self::$vehiclesPath,
            default => null,
        } ?? dirname(__DIR__, 2).'/import/'.$name;

        if (! is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return [];
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($data) ? $data : [];
    }
}
