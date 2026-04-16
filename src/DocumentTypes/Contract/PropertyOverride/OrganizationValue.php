<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class OrganizationValue extends RootDocument
{
    /**
     * @return list<string>
     */
    public function getOrganizations(): array
    {
        return $this->queryAttributeValues(
            'matchConditions/DataSetMatchCondition_SpecificOrganizationsDef/organizations/Reference',
            'value'
        );
    }
}
