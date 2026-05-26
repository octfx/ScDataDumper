<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

/**
 * DocumentType for `EntityClassDefinition.JumpPoint_Permanent`.
 *
 * Contains jump point parameters from the `SJumpPointParams` component:
 * - `requiredFuel`: fuel cost to traverse the jump point
 * - `largestShipSize`: UUID reference to the maximum ship size GUID
 * - `linkingRange`: range at which the two endpoints link up
 */
final class JumpPointPermanent extends RootDocument
{
    public function getRequiredFuel(): ?int
    {
        return $this->getInt('Components/SJumpPointParams@requiredFuel');
    }

    public function getLargestShipSize(): ?string
    {
        return $this->getString('Components/SJumpPointParams@largestShipSize');
    }

    public function getLinkingRange(): ?int
    {
        return $this->getInt('Components/SJumpPointParams@linkingRange');
    }
}
