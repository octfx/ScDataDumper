<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Harvestable;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class HarvestableProviderPreset extends RootDocument
{
    private ?int $elementOffset = null;

    /**
     * @return list<array{name: ?string, globalModifier: ?float, modifiers: list<array{harvestableModifier: int, elementIndex: ?int, geometries: list<string>}}>}
     */
    public function getAreas(): array
    {
        $areas = [];

        foreach ($this->getAll('areas/*') as $node) {
            if (! $node instanceof Element) {
                continue;
            }

            $name = $node->get('@name') ?? $node->get('@debugGroupName');
            $globalModifier = $node->get('@globalModifier');

            $modifiers = [];
            foreach ($node->getAll('modifiers/HarvestableElementModifier') as $modNode) {
                if (! $modNode instanceof Element) {
                    continue;
                }

                $modifierValue = $modNode->get('@harvestableModifier');
                $elementRef = $modNode->get('harvestableElement/@value');

                $hexIndex = null;
                if (is_string($elementRef) && preg_match('/^HarvestableElement\[([0-9A-Fa-f]+)\]$/', $elementRef, $m)) {
                    $hexIndex = (int) hexdec($m[1]);
                }

                $geometries = [];
                foreach ($modNode->getAll('geometries/HarvestableGeometry/@tag') as $tag) {
                    if (is_string($tag) && $tag !== '') {
                        $geometries[] = $tag;
                    }
                }

                $modifiers[] = [
                    'harvestableModifier' => is_numeric($modifierValue) ? (int) $modifierValue : 0,
                    'elementIndex' => $hexIndex,
                    'geometries' => $geometries,
                ];
            }

            $areas[] = [
                'name' => is_string($name) && $name !== '' ? $name : null,
                'globalModifier' => is_numeric($globalModifier) ? (float) $globalModifier : null,
                'modifiers' => $modifiers,
            ];
        }

        return $areas;
    }

    /**
     * @return array{element: HarvestableElement, groupName: string}|null
     */
    public function getElementInfoByGlobalIndex(int $globalIndex): ?array
    {
        $offset = $this->computeElementOffset();

        if ($offset === null) {
            return null;
        }

        $localIndex = $globalIndex - $offset;

        if ($localIndex < 0) {
            return null;
        }

        $currentIndex = 0;
        foreach ($this->getHarvestableGroups() as $group) {
            $groupName = $group->getName() ?? '';
            foreach ($group->getHarvestableElements() as $element) {
                if ($currentIndex === $localIndex) {
                    return ['element' => $element, 'groupName' => $groupName];
                }
                $currentIndex++;
            }
        }

        return null;
    }

    private function computeElementOffset(): ?int
    {
        if ($this->elementOffset !== null) {
            return $this->elementOffset;
        }

        $minIndex = null;
        foreach ($this->getAll('areas/*/modifiers/HarvestableElementModifier/harvestableElement/@value') as $value) {
            if (is_string($value) && preg_match('/^HarvestableElement\[([0-9A-Fa-f]+)\]$/', $value, $m)) {
                $index = (int) hexdec($m[1]);
                if ($minIndex === null || $index < $minIndex) {
                    $minIndex = $index;
                }
            }
        }

        $this->elementOffset = $minIndex;

        return $this->elementOffset;
    }

    /**
     * @return list<HarvestableElementGroup>
     */
    public function getHarvestableGroups(): array
    {
        $groups = [];

        foreach ($this->getAll('harvestableGroups/HarvestableElementGroup') as $node) {
            if (! $node instanceof Element) {
                continue;
            }

            $group = HarvestableElementGroup::fromNode($node->getNode(), $this->isReferenceHydrationEnabled());

            if ($group instanceof HarvestableElementGroup) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    /**
     * @return list<HarvestableElement>
     */
    public function getHarvestableElements(): array
    {
        $elements = [];

        foreach ($this->getHarvestableGroups() as $group) {
            foreach ($group->getHarvestableElements() as $element) {
                $elements[] = $element;
            }
        }

        return $elements;
    }
}
