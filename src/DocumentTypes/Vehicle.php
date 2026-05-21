<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

final class Vehicle extends RootDocument
{
    /**
     * Returns the vehicle-level identity tags from the itemPortTags attribute.
     *
     * These tags represent the vehicle's identity in the SC data model.
     * Bespoke items declare these in their RequiredTags to restrict equippability.
     * Example: "AEGS_Avenger_Base" for the Avenger Stalker.
     */
    public function getPortTags(): array
    {
        $raw = trim((string) ($this->documentElement->attributes->getNamedItem('itemPortTags')?->nodeValue ?? ''));

        if ($raw !== '') {
            return array_values(array_filter(explode(' ', $raw)));
        }

        // Many ships lack the itemPortTags attribute in their implementation XML.
        // Fall back to the "name" attribute on the <Vehicle> element, which is the
        // identity tag that bespoke items reference in their RequiredTags (e.g. ORIG_100i).
        $name = trim((string) ($this->documentElement->attributes->getNamedItem('name')?->nodeValue ?? ''));

        return $name !== '' ? [$name] : [];
    }
}
