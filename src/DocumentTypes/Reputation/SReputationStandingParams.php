<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Reputation;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class SReputationStandingParams extends RootDocument
{
    public function getName(): ?string
    {
        return $this->getString('@name');
    }

    public function getDescription(): ?string
    {
        return $this->getString('@description');
    }

    public function getDisplayName(): ?string
    {
        return $this->getString('@displayName');
    }

    public function getPerkDescription(): ?string
    {
        return $this->getString('@perkDescription');
    }

    public function getIcon(): ?string
    {
        return $this->getString('@icon');
    }

    public function getMinReputation(): ?int
    {
        return $this->getInt('@minReputation');
    }

    public function getDriftReputation(): ?int
    {
        return $this->getInt('@driftReputation');
    }

    public function getDriftTimeHours(): ?int
    {
        return $this->getInt('@driftTimeHours');
    }

    public function isGated(): bool
    {
        return $this->getBool('@gated');
    }
}
