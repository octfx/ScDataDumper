<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Harvestable;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class HarvestableElementGroup extends RootDocument
{
    public function getName(): ?string
    {
        return $this->getString('@groupName');
    }

    public function getProbability(): ?float
    {
        return $this->getFloat('@groupProbability');
    }

    /**
     * @return list<HarvestableElement>
     */
    public function getHarvestableElements(): array
    {
        $elements = [];

        foreach ($this->getAll('harvestables/HarvestableElement') as $node) {
            if (! $node instanceof Element) {
                continue;
            }

            $element = HarvestableElement::fromNode($node->getNode());

            if ($element instanceof HarvestableElement) {
                $elements[] = $element;
            }
        }

        return $elements;
    }

    public function getTotalRelativeProbability(): ?float
    {
        $total = 0.0;
        $hasProbability = false;

        foreach ($this->getHarvestableElements() as $element) {
            $probability = $element->getRelativeProbability();

            if ($probability === null) {
                continue;
            }

            $total += $probability;
            $hasProbability = true;
        }

        return $hasProbability ? $total : null;
    }

    public function getHarvestableElementProbability(HarvestableElement $element): ?float
    {
        $elements = $this->getHarvestableElements();
        $probabilities = $this->getHarvestableElementProbabilities();
        $targetXml = $element->saveXML($element->documentElement);

        foreach ($elements as $index => $groupElement) {
            if ($groupElement->saveXML($groupElement->documentElement) === $targetXml) {
                return $probabilities[$index] ?? null;
            }
        }

        return null;
    }

    /**
     * @return list<float>
     */
    public function getHarvestableElementProbabilities(): array
    {
        $elements = $this->getHarvestableElements();
        $total = $this->getTotalRelativeProbability();
        $probabilities = [];

        if ($total === null || $total <= 0.0) {
            return $probabilities;
        }

        foreach ($elements as $element) {
            $relativeProbability = $element->getRelativeProbability();

            if ($relativeProbability === null) {
                continue;
            }

            $probabilities[] = $relativeProbability / $total;
        }

        return $probabilities;
    }
}
