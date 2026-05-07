<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;
use Octfx\ScDataDumper\Services\DataDumper\SocpakReader;

final class SocpakBedExtractor
{
    private const string BED_TYPE_PREFIX = 'Bed_';

    /** @var array<string, list<array{ClassName: string, InstanceName: string, Section: string, Layer: string|null}>> */
    private array $socpakBedCache = [];

    public function __construct(
        private readonly SocpakReader $reader,
    ) {}

    /**
     * Extract all bed entries from ObjectContainer socpak files referenced by a vehicle entity.
     *
     * @return list<array{ClassName: string, InstanceName: string, Section: string, Layer: string|null}>
     */
    public function extractBeds(VehicleDefinition $entity): array
    {
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

            $socpakPath = $this->reader->resolveSocpakPath((string) $fileName);

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

        $editorXml = $this->reader->extractEditorXml($socpakPath);

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
}
