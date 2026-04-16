<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class ContractGeneratorRecord extends RootDocument
{
    /**
     * @return list<ContractHandler>
     */
    public function getHandlers(): array
    {
        $generators = $this->get('generators');
        if ($generators === null) {
            return [];
        }

        $handlers = [];

        foreach ($generators->children() as $child) {
            $doc = ContractHandler::fromNode($child->getNode(), $this->isReferenceHydrationEnabled());
            if ($doc instanceof ContractHandler) {
                $handlers[] = $doc;
            }
        }

        return $handlers;
    }

    /**
     * @return list<MissionPropertyOverride>
     */
    public function getAllPropertyOverrides(): array
    {
        $results = [];
        $nodes = $this->getAll('.//propertyOverrides/MissionProperty');

        foreach ($nodes as $node) {
            $doc = MissionPropertyOverride::fromNode($node->getNode(), $this->isReferenceHydrationEnabled());
            if ($doc instanceof MissionPropertyOverride) {
                $results[] = $doc;
            }
        }

        return $results;
    }

    /**
     * @return list<string>
     */
    public function getAllMissionPropertyReferences(): array
    {
        $refs = [];
        $nodes = $this->getAll('.//HaulingOrderContent_MissionItem/item@value');

        foreach ($nodes as $node) {
            if (is_string($node) && preg_match('/^MissionProperty\[([0-9A-Fa-f]+)\]$/', $node)) {
                $refs[] = $node;
            }
        }

        return $refs;
    }

    public function computePropertyBaseOffset(): ?int
    {
        $refs = $this->getAllMissionPropertyReferences();
        if ($refs === []) {
            return null;
        }

        $allProps = $this->getAllPropertyOverrides();
        $propCount = count($allProps);

        $hexValues = array_map(function (string $ref): int {
            preg_match('/MissionProperty\[([0-9A-Fa-f]+)\]/', $ref, $m);

            return hexdec($m[1]);
        }, $refs);

        $minHex = min($hexValues);
        $missionItemIndices = [];
        foreach ($allProps as $i => $prop) {
            if ($prop->getValueTypeName() === 'MissionPropertyValue_MissionItem') {
                $missionItemIndices[] = $i;
            }
        }

        foreach ($missionItemIndices as $candidateIndex) {
            $baseOffset = $minHex - $candidateIndex;

            $valid = true;
            foreach ($hexValues as $hex) {
                $localIndex = $hex - $baseOffset;
                if ($localIndex < 0 || $localIndex >= $propCount) {
                    $valid = false;
                    break;
                }
            }

            if ($valid) {
                return $baseOffset;
            }
        }

        return null;
    }
}
