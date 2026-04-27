<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;
use ZipArchive;

final class SocpakBedExtractor
{
    private const string BED_TYPE_PREFIX = 'Bed_';

    /** @var array<string, list<string>> */
    private array $dirCache = [];

    /** @var array<string, list<array{ClassName: string, InstanceName: string, Section: string, Layer: string|null}>> */
    private array $socpakBedCache = [];

    public function __construct(
        private readonly ?string $scDataPath,
    ) {}

    /**
     * Extract all bed entries from ObjectContainer socpak files referenced by a vehicle entity.
     *
     * @return list<array{ClassName: string, InstanceName: string, Section: string, Layer: string|null}>
     */
    public function extractBeds(VehicleDefinition $entity): array
    {
        if ($this->scDataPath === null) {
            return [];
        }

        $ocRefs = $entity->getAll('Components/VehicleComponentParams/objectContainers/SVehicleObjectContainerParams');

        if ($ocRefs === []) {
            return [];
        }

        $allBeds = [];

        foreach ($ocRefs as $ocRef) {
            if (! ($ocRef instanceof Element)) {
                continue;
            }

            $fileName = $ocRef->get('@fileName');
            $boneName = $ocRef->get('@boneName');

            if ($fileName === null || $boneName === null) {
                continue;
            }

            $socpakPath = $this->resolveSocpakPath((string) $fileName);

            if ($socpakPath === null) {
                continue;
            }

            $beds = $this->extractBedsFromSocpak($socpakPath, (string) $boneName);
            $allBeds = [...$allBeds, ...$beds];
        }

        return $allBeds;
    }

    /**
     * @return list<array{ClassName: string, InstanceName: string, Section: string, Layer: string|null}>
     */
    private function extractBedsFromSocpak(string $socpakPath, string $section): array
    {
        if (isset($this->socpakBedCache[$socpakPath])) {
            $cached = $this->socpakBedCache[$socpakPath];

            return array_map(static fn (array $bed) => [...$bed, 'Section' => $section], $cached);
        }

        $editorXml = $this->extractEditorXml($socpakPath);

        if ($editorXml === null) {
            return $this->socpakBedCache[$socpakPath] = [];
        }

        $dom = new DOMDocument;
        $dom->loadXML($editorXml);
        $xpath = new DOMXPath($dom);

        $bedObjects = $xpath->query('//Object[starts-with(@type, "'.self::BED_TYPE_PREFIX.'")]');

        if ($bedObjects === false || $bedObjects->length === 0) {
            return $this->socpakBedCache[$socpakPath] = [];
        }

        $beds = [];

        foreach ($bedObjects as $node) {
            if (! ($node instanceof DOMElement)) {
                continue;
            }

            $type = $node->getAttribute('type');
            $name = $node->getAttribute('name');
            $layer = $this->findParentLayerName($node);

            $beds[] = [
                'ClassName' => $type,
                'InstanceName' => $name,
                'Section' => $section,
                'Layer' => $layer,
            ];
        }

        $this->socpakBedCache[$socpakPath] = $beds;

        return $beds;
    }

    private function extractEditorXml(string $socpakPath): ?string
    {
        $zip = new ZipArchive;

        if ($zip->open($socpakPath) !== true) {
            return null;
        }

        $editorXml = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $entryName = str_replace('\\', '/', $stat['name']);

            if (str_ends_with($entryName, '_editor.xml')) {
                $editorXml = $zip->getFromIndex($i);
                break;
            }
        }

        $zip->close();

        return $editorXml !== false ? $editorXml : null;
    }

    private function findParentLayerName(DOMElement $node): ?string
    {
        $parent = $node->parentNode;

        while ($parent !== null) {
            if ($parent instanceof DOMElement && $parent->nodeName === 'Layer') {
                $name = $parent->getAttribute('name');

                return $name !== '' ? $name : null;
            }

            $parent = $parent->parentNode;
        }

        return null;
    }

    private function resolveSocpakPath(string $relativePath): ?string
    {
        $normalized = str_replace('\\', '/', $relativePath);
        $normalized = ltrim($normalized, '/');
        $normalized = strtolower($normalized);

        $dataDir = $this->scDataPath.DIRECTORY_SEPARATOR.'Data';

        $parts = explode('/', $normalized);
        $currentDir = $dataDir;

        foreach ($parts as $i => $part) {
            $isLast = $i === count($parts) - 1;

            if ($isLast) {
                $resolved = $this->findFileInDir($part, $currentDir);

                return $resolved !== null
                    ? $currentDir.DIRECTORY_SEPARATOR.$resolved
                    : null;
            }

            $resolved = $this->findDirInDir($part, $currentDir);

            if ($resolved === null) {
                return null;
            }

            $currentDir .= DIRECTORY_SEPARATOR.$resolved;
        }

        return null;
    }

    private function findFileInDir(string $lowerName, string $dir): ?string
    {
        $entries = $this->scanDir($dir);
        $lower = strtolower($lowerName);

        return array_find($entries, fn ($entry) => strtolower($entry) === $lower && ! is_dir($dir.DIRECTORY_SEPARATOR.$entry));
    }

    private function findDirInDir(string $lowerName, string $dir): ?string
    {
        $entries = $this->scanDir($dir);
        $lower = strtolower($lowerName);

        return array_find($entries, fn ($entry) => strtolower($entry) === $lower && is_dir($dir.DIRECTORY_SEPARATOR.$entry));
    }

    /**
     * @return list<string>
     */
    private function scanDir(string $dir): array
    {
        if (! isset($this->dirCache[$dir])) {
            $entries = @scandir($dir);
            $this->dirCache[$dir] = $entries !== false
                ? array_values(array_diff($entries, ['.', '..']))
                : [];
        }

        return $this->dirCache[$dir];
    }
}
