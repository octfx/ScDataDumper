<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use DOMDocument;
use JsonException;
use Octfx\ScDataDumper\DocumentTypes\ConsumableSubtype;
use RuntimeException;
use XMLReader;

final class ConsumableSubtypeService extends BaseService
{
    /**
     * Raw consumable subtype data from cache
     */
    private array $consumableSubtypeData = [];

    /**
     * Cache of ConsumableSubtype instances to avoid re-creating them
     *
     * @var array<string, ConsumableSubtype>
     */
    private array $instances = [];

    /**
     * @throws JsonException
     */
    public function initialize(): void
    {
        $cachePath = $this->makeCachePath();

        if (! file_exists($cachePath)) {
            $this->buildCache();
        } else {
            $this->loadCache($cachePath);
        }
    }

    /**
     * Get ConsumableSubtype by UUID
     */
    public function getByUuid(string $uuid): ?ConsumableSubtype
    {
        if (isset($this->instances[$uuid])) {
            return $this->instances[$uuid];
        }

        if (! isset($this->consumableSubtypeData[$uuid])) {
            return null;
        }

        $instance = new ConsumableSubtype($uuid, $this->consumableSubtypeData[$uuid]);
        $this->instances[$uuid] = $instance;

        return $instance;
    }

    /**
     * Get all ConsumableSubtype UUIDs
     *
     * @return string[]
     */
    public function getAllUuids(): array
    {
        return array_keys($this->consumableSubtypeData);
    }

    /**
     * Get count of loaded consumable subtypes
     */
    public function getCount(): int
    {
        return count($this->consumableSubtypeData);
    }

    /**
     * Build cache by parsing extracted ConsumableSubtype XML files referenced in classToPathMap.
     *
     * @throws JsonException
     */
    private function buildCache(): void
    {
        $this->consumableSubtypeData = $this->loadConsumableSubtypeData();
        $this->writeCache();
    }

    /**
     * @return array<string, array{typeName: string, consumableName: string, effects: array<int, array<string, mixed>>}>
     *
     * @throws JsonException
     */
    private function loadConsumableSubtypeData(): array
    {
        $classToPathMap = json_decode(
            file_get_contents($this->classToPathMapPath),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $consumableSubtypePaths = $classToPathMap['ConsumableSubtype'] ?? [];
        if (! is_array($consumableSubtypePaths)) {
            throw new RuntimeException(sprintf(
                'Missing consumable subtype paths in %s. Run generate:cache first.',
                $this->classToPathMapPath
            ));
        }

        $consumableSubtypes = [];

        foreach ($consumableSubtypePaths as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $consumableSubtype = $this->readConsumableSubtypeFile($path);
            if ($consumableSubtype === null) {
                continue;
            }

            $consumableSubtypes[$consumableSubtype['uuid']] = $consumableSubtype['data'];
        }

        return $consumableSubtypes;
    }

    /**
     * Parse ConsumableSubtype XML DOM into array structure
     */
    private function parseConsumableSubtypeXml(DOMDocument $dom): array
    {
        $root = $dom->documentElement;
        $data = [
            'typeName' => $root->getAttribute('typeName'),
            'consumableName' => $root->getAttribute('consumableName'),
            'effects' => [],
        ];

        $effectsNodes = $dom->getElementsByTagName('effectsPerMicroSCU');
        if ($effectsNodes->length > 0) {
            $effectsContainer = $effectsNodes->item(0);

            foreach ($effectsContainer?->childNodes as $effectNode) {
                if ($effectNode->nodeType !== XML_ELEMENT_NODE) {
                    continue;
                }

                $effectType = $effectNode->getAttribute('__polymorphicType') ?: $effectNode->nodeName;

                if ($effectType === 'ConsumableEffectModifyActorStatus') {
                    // Stat modification (Hunger, Thirst, BloodDrugLevel)
                    $data['effects'][] = [
                        'type' => 'ModifyActorStatus',
                        'statType' => $effectNode->getAttribute('statType'),
                        'statPointChange' => (float) $effectNode->getAttribute('statPointChange'),
                        'statCooldownChange' => (float) ($effectNode->getAttribute('statCooldownChange') ?: 0),
                    ];
                } elseif ($effectType === 'ConsumableEffectHealth') {
                    // Direct health modification
                    $data['effects'][] = [
                        'type' => 'Health',
                        'healthChange' => (float) $effectNode->getAttribute('healthChange'),
                    ];
                } elseif ($effectType === 'ConsumableEffectAddBuffEffect') {
                    // Buff/Debuff effect
                    $buffType = $effectNode->getAttribute('buffType');
                    $duration = null;

                    // Check for duration override
                    $durationNodes = $effectNode->getElementsByTagName('BuffDurationOverride');
                    if ($durationNodes->length > 0) {
                        $durationNode = $durationNodes->item(0);
                        $duration = (int) $durationNode->getAttribute('durationOverride');
                    }

                    $data['effects'][] = [
                        'type' => 'AddBuffEffect',
                        'buffType' => $buffType,
                        'duration' => $duration,
                    ];
                }
            }
        }

        return $data;
    }

    /**
     * @return array{
     *     uuid: string,
     *     data: array{typeName: string, consumableName: string, effects: array<int, array<string, mixed>>}
     * }|null
     */
    private function readConsumableSubtypeFile(string $path): ?array
    {
        $reader = XMLReader::open($path, null, LIBXML_NONET | LIBXML_COMPACT);
        if ($reader === false) {
            return null;
        }

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT) {
                    continue;
                }

                $uuid = $reader->getAttribute('__ref') ?? '';
                $xml = $reader->readOuterXml();
                if ($uuid === '' || $xml === '') {
                    return null;
                }

                $dom = new DOMDocument;
                $dom->preserveWhiteSpace = false;

                $prevErrorSetting = libxml_use_internal_errors(true);
                try {
                    if (! $dom->loadXML($xml)) {
                        return null;
                    }
                } finally {
                    libxml_use_internal_errors($prevErrorSetting);
                }

                return [
                    'uuid' => $uuid,
                    'data' => $this->parseConsumableSubtypeXml($dom),
                ];
            }
        } finally {
            $reader->close();
        }

        return null;
    }

    /**
     * Write cache to disk
     *
     * @throws JsonException
     */
    private function writeCache(): void
    {
        $cachePath = $this->makeCachePath();

        $ref = fopen($cachePath, 'wb');
        fwrite($ref, json_encode($this->consumableSubtypeData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        fclose($ref);
    }

    /**
     * Load cache from disk
     *
     * @throws JsonException
     */
    private function loadCache(string $path): void
    {
        $this->consumableSubtypeData = json_decode(
            file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    /**
     * Get cache file path
     */
    private function makeCachePath(): string
    {
        return sprintf(
            '%s%sconsumable-subType-cache-%s.json',
            $this->scDataDir,
            DIRECTORY_SEPARATOR,
            PHP_OS_FAMILY
        );
    }
}
