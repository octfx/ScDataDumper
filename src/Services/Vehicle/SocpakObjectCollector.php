<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Arr;
use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;
use Octfx\ScDataDumper\Helper\Element;
use Octfx\ScDataDumper\Services\DataDumper\SocpakReader;

final class SocpakObjectCollector
{
    /** @var array<string, list<array{className: string, instanceName: string, layer: string|null}>> */
    private array $templateCache = [];

    public function __construct(
        private readonly SocpakReader $reader,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $loadout
     * @return list<SocpakObject>
     */
    public function collectAll(VehicleDefinition $entity, array $loadout): array
    {
        $objects = [];

        foreach ($this->discoverOccurrences($entity, $loadout) as $occurrence) {
            $socpakPath = $occurrence['socpakPath'];
            $section = $occurrence['section'];

            if (! array_key_exists($socpakPath, $this->templateCache)) {
                $this->templateCache[$socpakPath] = $this->parseTemplates($socpakPath);
            }

            foreach ($this->templateCache[$socpakPath] as $template) {
                $objects[] = new SocpakObject(
                    className: $template['className'],
                    instanceName: $template['instanceName'],
                    section: $section,
                    layer: $template['layer'],
                    socpakPath: $socpakPath,
                );
            }
        }

        return $objects;
    }

    /**
     * @param  array<int, array<string, mixed>>  $loadout
     * @return list<array{socpakPath: string, section: string}>
     */
    private function discoverOccurrences(VehicleDefinition $entity, array $loadout): array
    {
        return [
            ...$this->discoverEntityObjectContainerOccurrences($entity),
            ...$this->discoverLoadoutObjectContainerOccurrences($loadout),
        ];
    }

    /**
     * @return list<array{socpakPath: string, section: string}>
     */
    private function discoverEntityObjectContainerOccurrences(VehicleDefinition $entity): array
    {
        $occurrences = [];
        $ocRefs = $entity->getAll('Components/VehicleComponentParams/objectContainers/SVehicleObjectContainerParams');

        foreach ($ocRefs as $ocRef) {
            if (! ($ocRef instanceof Element)) {
                continue;
            }

            $fileName = $ocRef->get('@fileName');
            $boneName = $ocRef->get('@boneName');

            if (! is_string($fileName) || $fileName === '' || ! is_string($boneName) || $boneName === '') {
                continue;
            }

            $socpakPath = $this->reader->resolveSocpakPath($fileName);

            if ($socpakPath === null) {
                continue;
            }

            $occurrences[] = [
                'socpakPath' => $socpakPath,
                'section' => $boneName,
            ];
        }

        return $occurrences;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return list<array{socpakPath: string, section: string}>
     */
    private function discoverLoadoutObjectContainerOccurrences(array $entries): array
    {
        $occurrences = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $socpakRef = Arr::get($entry, 'ItemRaw.Components.SObjectContainerComponentParams.objectContainer');

            if (is_string($socpakRef) && $socpakRef !== '') {
                $socpakPath = $this->reader->resolveSocpakPath($socpakRef);

                if ($socpakPath !== null) {
                    $portName = Arr::get($entry, 'portName', '');

                    $occurrences[] = [
                        'socpakPath' => $socpakPath,
                        'section' => is_string($portName) ? $portName : '',
                    ];
                }
            }

            $children = Arr::get($entry, 'entries', []);

            if (is_array($children) && $children !== []) {
                $occurrences = [
                    ...$occurrences,
                    ...$this->discoverLoadoutObjectContainerOccurrences($children),
                ];
            }
        }

        return $occurrences;
    }

    /**
     * @return list<array{className: string, instanceName: string, layer: string|null}>
     */
    private function parseTemplates(string $socpakPath): array
    {
        $editorXml = $this->reader->extractEditorXml($socpakPath);

        if ($editorXml === null || $editorXml === '') {
            return [];
        }

        $dom = new DOMDocument;

        if (@$dom->loadXML($editorXml) === false) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//Object');

        if ($nodes === false || $nodes->length === 0) {
            return [];
        }

        $templates = [];

        foreach ($nodes as $node) {
            if (! ($node instanceof DOMElement)) {
                continue;
            }

            $templates[] = [
                'className' => $node->getAttribute('type'),
                'instanceName' => $node->getAttribute('name'),
                'layer' => $this->findParentLayerName($node),
            ];
        }

        return $templates;
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
