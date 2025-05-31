<?php

namespace Octfx\ScDataDumper\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Generator;
use JsonException;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\DocumentTypes\Vehicle;
use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;
use Octfx\ScDataDumper\Helper\VehicleWrapper;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class VehicleService extends BaseService
{
    private array $vehicles;

    private array $implementations = [];

    // Avoid files containing tags
    private array $avoids = [
        'active1',
        'advocacy',
        'ai',
        'aiship',
        'bombless',
        'bounty',
        'c19b',
        'citizencon',
        'civ',
        'comms',
        'crim',
        'derelict',
        'drone',
        'drug',
        'dummy',
        'eaobjectivedestructable',
        'featuretest',
        'fleetweek',
        'fw22nfz',
        'hijacked',
        'human',
        'indestructible',
        'kopion',
        'microtech',
        'mlhm1',
        'modifiers',
        'nocrimesagainst',
        'nointerior',
        'npc',
        'outlaw',
        'outlaws',
        'piano',
        'player',
        'pu',
        'qig',
        's3bombs',
        's42',
        'securitynetwork',
        'shields',
        'shipshowdown',
        'shubin',
        'swarm',
        'template',
        'tow',
        'tutorial',
        'uee',
        'unmanned',
        'wreck',
    ];

    // Avoid these file suffixes
    private array $avoidSuffixes = [
        // '_rn',
        '_ac_engineer',
        '_cci2953',
        '_cinematic_only',
        '_ea_pir',
        '_fw22nfz',
        '_mfd',
        '_prison',
        '_pir_package',
        '_temp',
        '_test',
        '_instanced',
    ];

    public function count(): int
    {
        return count($this->vehicles);
    }

    /**
     * @throws JsonException
     */
    public function initialize(): void
    {
        $this->loadImplementations();
        $items = json_decode(file_get_contents($this->classToPathMapPath), true, 512, JSON_THROW_ON_ERROR)['EntityClassDefinition'] ?? [];

        $items = array_filter($items, static fn (string $path) => str_contains($path, 'entities/spaceships') || str_contains($path, 'entities/groundvehicles') || str_contains($path, 'actor/actors'));
        $items = array_filter($items, function (string $path): bool {
            $name = basename($path, '.xml');
            $parts = array_map('strtolower', explode('_', $name));

            return ! array_reduce($parts, fn ($carry, $cur) => $carry || in_array(strtolower($cur), $this->avoids, true), false);
        });

        $items = array_filter($items, function (string $path): bool {
            $name = basename($path, '.xml');

            return ! array_reduce($this->avoidSuffixes, static fn ($carry, $cur) => $carry || str_ends_with(strtolower($name), strtolower($cur)), false);
        });

        // Testing
        // $items = array_filter($items, static fn (string $path) => str_contains($path, 'atls'));

        $this->vehicles = $items;
    }

    public function iterator(): Generator
    {
        foreach ($this->vehicles as $path) {
            $vehicle = $this->load($path);
            if ($vehicle === null) {
                continue;
            }

            yield $vehicle;
        }
    }

    public function load(string $filePath): ?VehicleWrapper
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException(sprintf('File %s does not exist or is not readable.', $filePath));
        }

        $vehicle = new VehicleDefinition;
        $vehicle->load($filePath);

        $implementation = $vehicle->get('Components/VehicleComponentParams@vehicleDefinition');

        $doc = null;
        if ($implementation !== null) {
            $fileName = explode('/', $implementation);
            $fileName = array_pop($fileName);

            $implementation = $this->implementations[strtolower($fileName)];
            $modification = $vehicle->get('Components/VehicleComponentParams@modification');

            $doc = $this->parseVehicle($implementation, $modification);

            $doc->initXPath();
        } elseif ($vehicle->getAttachType() !== 'NOITEM_Vehicle') {
            return null;
        }

        $manualLoadout = $vehicle->get('Components/SEntityComponentDefaultLoadoutParams/loadout/SItemPortLoadoutManualParams');

        $loadout = [];
        if ($manualLoadout) {
            $loadout = $this->buildManualLoadout($manualLoadout);
        }

        $vehicle->initXPath();

        return new VehicleWrapper(
            $doc,
            $vehicle,
            $loadout
        );
    }

    private function parseVehicle(string $vehiclePath, string $modificationName): Vehicle
    {
        $doc = new Vehicle;
        $doc->load($vehiclePath);

        if (! empty(trim($modificationName))) {
            $this->processPatchFile($doc, $modificationName, $vehiclePath);
            $this->processModificationElems($doc, $modificationName);
        }

        return $doc;
    }

    private function processPatchFile($doc, $modificationName, $vehiclePath): void
    {
        $xpath = new DOMXPath($doc);
        $modificationNode = $xpath->query("//Modifications/Modification[@name='$modificationName']")->item(0);
        $patchFile = $modificationNode?->getAttribute('patchFile');

        if (empty($patchFile)) {
            return;
        }

        $patchFilename = sprintf(
            '%s%sModifications%s%s.xml',
            pathinfo($vehiclePath, PATHINFO_DIRNAME),
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            str_replace('/', DIRECTORY_SEPARATOR, pathinfo($patchFile, PATHINFO_FILENAME))
        );

        if (! file_exists($patchFilename)) {
            return;
        }

        $patchDoc = new DOMDocument;
        $patchDoc->load($patchFilename, LIBXML_NOCDATA | LIBXML_NOBLANKS | LIBXML_COMPACT);

        /** @var DOMElement $patchNode */
        foreach ($patchDoc->firstChild->childNodes as $patchNode) {
            if ($patchNode->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $id = $patchNode->getAttribute('id');
            if (empty($id)) {
                echo "Can't load modifications for {$patchNode->nodeName} - there is no id attribute".PHP_EOL;

                continue;
            }

            $nodes = $xpath->query("//*[@id='$id']");
            /** @var DOMElement $node */
            foreach ($nodes as $node) {
                $clonedNode = $doc->importNode($patchNode, true);
                $node->replaceWith($clonedNode);
            }
        }
    }

    private function processModificationElems($doc, $modificationName): void
    {
        $xpath = new DOMXPath($doc);
        $elems = $xpath->query("//Modifications/Modification[@name='$modificationName']/Elems/Elem");

        foreach ($elems as $elem) {
            $idRef = $elem->getAttribute('idRef');
            $attrName = $elem->getAttribute('name');
            $attrValue = $elem->getAttribute('value');

            $nodes = $xpath->query("//*[@id='$idRef']");
            /** @var DOMElement $node */
            foreach ($nodes as $node) {
                $attr = $node->getAttributeNode($attrName);
                if ($attr === null || $attr === false) {
                    $attr = $doc->createAttribute($attrName);
                    $node->appendChild($attr);
                }
                $attr->value = $attrValue;
            }
        }
    }

    private function loadImplementations(): void
    {
        $implementationsPath = [
            $this->scDataDir,
            'Data',
            'Scripts',
            'Entities',
            'Vehicles',
            'Implementations',
            'Xml',
        ];
        $implementationsPath = implode(DIRECTORY_SEPARATOR, $implementationsPath);

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($implementationsPath));

        $implementations = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'xml') {
                $parts = explode('/', $file->getBasename());
                $name = array_pop($parts);

                $implementations[strtolower($name)] = $file->getPathname();
            }
        }

        $this->implementations = $implementations;
    }

    public function buildManualLoadout(RootDocument|Element $manual): array
    {
        $entries = [];
        foreach ($manual->get('/entries')?->children() as $cigEntry) {
            $entries[] = $this->buildManualLoadoutEntry($cigEntry);
        }

        return $entries;
    }

    private function buildManualLoadoutEntry(RootDocument|Element $cigLoadoutEntry): array
    {
        $entry = [
            'portName' => $cigLoadoutEntry->get('itemPortName'),
            'className' => $cigLoadoutEntry->get('entityClassName'),
            'classReference' => $cigLoadoutEntry->get('entityClassReference'),
            'entries' => [],
        ];

        if (! empty($entry['className'])) {
            $entry['Item'] = ServiceFactory::getItemService()->getByClassName($entry['className'])?->toArray();
            $entry['Item']['Type'] = (new ItemClassifierService)->classify($entry['Item']);
        }

        if (! empty($entry['classReference']) && $entry['classReference'] !== '00000000-0000-0000-0000-000000000000') {
            $entry['Item'] = ServiceFactory::getItemService()->getByReference($entry['classReference'])?->toArray();
            $entry['Item']['Type'] = (new ItemClassifierService)->classify($entry['Item']);
        }

        if ($cigLoadoutEntry->get('loadout/SItemPortLoadoutManualParams/entries') !== null) {
            foreach ($cigLoadoutEntry->get('loadout/SItemPortLoadoutManualParams/entries')->children() as $e) {
                $entry['entries'][] = $this->buildManualLoadoutEntry($e);
            }
        }

        return $entry;
    }
}
