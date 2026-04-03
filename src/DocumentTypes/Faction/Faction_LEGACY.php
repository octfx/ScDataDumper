<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Faction;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class Faction_LEGACY extends RootDocument {
    public function getDisplayName(): string
    {
        return $this->getString('@displayName');
    }

    public function getDescription(): string
    {
        return $this->getString('@description');
    }

    public function getDefaultReaction(): string
    {
        return $this->getString('@defaultReaction');

    }
}
