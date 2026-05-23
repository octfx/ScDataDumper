<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use DOMDocument;
use DOMElement;
use JsonException;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Walks XML files for each document type and builds a schema snapshot:
 * every unique element path, its attributes, child element names, and cardinality.
 */
final class SchemaSnapshotService
{
    /** @var array<string, array<string, mixed>> className -> path */
    private array $classToPathMap;

    public function __construct(string $scDataDir)
    {
        $mapPath = sprintf('%s/classToPathMap-%s.json', $scDataDir, PHP_OS_FAMILY);

        if (! file_exists($mapPath)) {
            throw new RuntimeException(sprintf(
                'Class-to-path map not found at %s. Run generate:cache first.',
                $mapPath,
            ));
        }

        $this->classToPathMap = json_decode(
            file_get_contents($mapPath),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Build a full census for all document types (or a filtered subset).
     *
     * @param  list<string>|null  $typeFilter  Only process these document type keys (e.g. ['CraftingBlueprintRecord'])
     * @return array<string, array<string, array<string, mixed>>> docType -> path -> fingerprint
     */
    public function buildSnapshot(?array $typeFilter = null, ?SymfonyStyle $io = null): array
    {
        $globalIgnoreType = [
            'AIMeleeCombatConfig',
            'AIMotiveList',
            'BuildingBlocks_Canvas',
            'BuildingBlocks_ExternalColorReference',
            'BuildingBlocks_Style',
            'BuildingBlocks_Timeline',
            'Camera',
            'GPUParticleAudio',
            'MusicLogicSuite',
            'SUnifiedShakeParamsRecord',
            'TransportIconTypes',
            'VibrationAudioPointDef',
        ];

        $census = [];

        $types = $typeFilter !== null
            ? array_intersect_key($this->classToPathMap, array_flip($typeFilter))
            : $this->classToPathMap;

        $totalTypes = count($types);
        $processed = 0;

        foreach ($types as $docType => $paths) {
            if (in_array($docType, $globalIgnoreType, true)) {
                continue;
            }

            $processed++;

            if (! is_array($paths)) {
                continue;
            }

            $io?->text(sprintf('[%d/%d] %s (%d files)', $processed, $totalTypes, $docType, count($paths)));

            $census[$docType] = $this->censusDocumentType($paths);
        }

        return $census;
    }

    /**
     * Build census for one document type by walking all its XML files.
     *
     * @param  array<string, string>  $paths  className -> absolute file path
     * @return array<string, array<string, mixed>>
     */
    private function censusDocumentType(array $paths): array
    {
        /** @var array<string, array{path: string, xml_tag: string, attributes: array<string, array{values: array<string, int>, frequency: int}>, child_elements: array<string, array{frequency: int, max_count: int, sample_paths: list<string>}>, sample_values: array<string, int>, max_depth: int}> $fingerprints */
        $fingerprints = [];
        $fileCount = 0;

        foreach ($paths as $filePath) {
            if (! file_exists($filePath)) {
                continue;
            }

            $fileCount++;
            $dom = new DOMDocument;
            @$dom->load($filePath);

            if ($dom->documentElement === null) {
                continue;
            }

            // Skip the root element itself (it's the per-file class name like
            // CraftingBlueprintRecord.BP_CRAFT_xyz) and start from its children.
            // This normalizes paths across all files of the same document type.
            foreach ($dom->documentElement->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $this->walkElement($child, '', $fingerprints, 0);
                }
            }
        }

        // Post-process: add metadata
        foreach ($fingerprints as &$fingerprint) {
            $fingerprint['_files_sampled'] = $fileCount;
        }

        return $fingerprints;
    }

    /**
     * Recursively walk a DOM element and record its schema fingerprint.
     *
     * @param  array<string, array{path: string, xml_tag: string, attributes: array<string, array{values: array<string, int>, frequency: int}>, child_elements: array<string, array{frequency: int, max_count: int, sample_paths: list<string>}>, sample_values: array<string, int>, max_depth: int}>  &$fingerprints
     */
    private function walkElement(DOMElement $element, string $parentPath, array &$fingerprints, int $depth): void
    {
        // Normalize the element tag: strip namespace-like prefixes, keep local name
        $tagName = $element->localName ?? $element->nodeName;
        $path = $parentPath !== '' ? $parentPath . '/' . $tagName : $tagName;

        // Initialize fingerprint for this path if needed
        if (! isset($fingerprints[$path])) {
            $fingerprints[$path] = [
                'xml_tag' => $tagName,
                'attributes' => [],
                'child_elements' => [],
                'max_depth' => $depth,
            ];
        }

        $fp = &$fingerprints[$path];

        // Track max depth seen at this path
        if ($depth > ($fp['max_depth'] ?? 0)) {
            $fp['max_depth'] = $depth;
        }

        // Record attributes
        if ($element->attributes !== null) {
            foreach ($element->attributes as $attr) {
                $attrName = $attr->nodeName;

                // internal metadata
                if (in_array($attrName, ['__type', '__polymorphicType'], true)) {
                    continue;
                }

                if (! isset($fp['attributes'][$attrName])) {
                    $fp['attributes'][$attrName] = [
                        'frequency' => 0,
                        'sample_values' => [],
                    ];
                }

                $fp['attributes'][$attrName]['frequency']++;

                $value = $attr->nodeValue;
                if ($value !== null && $value !== '') {
                    $truncated = mb_substr($value, 0, 120);
                    $samples = $fp['attributes'][$attrName]['sample_values'];
                    // Keep up to 10 unique sample values
                    if (! in_array($truncated, $samples, true) && count($samples) < 10) {
                        $fp['attributes'][$attrName]['sample_values'][] = $truncated;
                    }
                }
            }
        }

        $childCounts = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $childTag = $child->localName ?? $child->nodeName;
                $childCounts[$childTag] = ($childCounts[$childTag] ?? 0) + 1;
            }
        }

        foreach ($childCounts as $childTag => $count) {
            if (! isset($fp['child_elements'][$childTag])) {
                $fp['child_elements'][$childTag] = [
                    'frequency' => 0,
                    'max_count' => 0,
                ];
            }

            $fp['child_elements'][$childTag]['frequency']++;
            if ($count > $fp['child_elements'][$childTag]['max_count']) {
                $fp['child_elements'][$childTag]['max_count'] = $count;
            }
        }

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $this->walkElement($child, $path, $fingerprints, $depth + 1);
            }
        }

        unset($fp);
    }

    /**
     * @throws JsonException
     */
    public function saveSnapshot(array $snapshot, string $outputPath): void
    {
        $dir = dirname($outputPath);
        if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new RuntimeException(sprintf('Cannot create directory %s', $dir));
        }

        $normalized = $this->normalizeForJson($snapshot);

        file_put_contents(
            $outputPath,
            json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Recursively convert empty associative-array fields to stdClass
     * so they serialize as {} instead of [].
     */
    private function normalizeForJson(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        if (array_is_list($data)) {
            return array_map($this->normalizeForJson(...), $data);
        }

        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value) && empty($value)) {
                // Empty array -> empty object
                $result[$key] = new stdClass;
            } else {
                $result[$key] = $this->normalizeForJson($value);
            }
        }

        return $result;
    }

    /**
     * @throws JsonException
     */
    public static function loadSnapshot(string $path): array
    {
        if (! file_exists($path)) {
            throw new RuntimeException(sprintf('Snapshot file not found: %s', $path));
        }

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }
}
