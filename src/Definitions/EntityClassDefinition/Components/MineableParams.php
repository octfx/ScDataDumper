<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components;

use DOMDocument;
use Octfx\ScDataDumper\Helper\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

/**
 * @deprecated Eager hydration is deprecated. This hydrator will be removed once ECD component hydration is migrated into EntityClassDefinition.
 */
final class MineableParams extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized()) {
            return;
        }

        parent::initialize($document);

        $svc = ServiceFactory::getFoundryLookupService();

        $this->hydrateFoundryReference(
            $document,
            referencePath: '@globalParams',
            childNodeName: 'MiningGlobalParams',
            record: $svc->getMiningGlobalParamsByReference($this->get('@globalParams'))
        );

        $this->hydrateFoundryReference(
            $document,
            referencePath: '@composition',
            childNodeName: 'MineableComposition',
            record: $svc->getMineableCompositionByReference($this->get('@composition'))
        );
    }
}
