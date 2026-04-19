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
}
