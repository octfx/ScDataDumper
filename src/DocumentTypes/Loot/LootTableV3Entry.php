<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Loot;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class LootTableV3Entry extends RootDocument
{
    public function getName(): ?string
    {
        return $this->getString('@name');
    }

    public function getWeight(): ?float
    {
        return $this->getFloat('@weight');
    }

    public function getArchetypeReference(): ?string
    {
        return $this->getString('archetype/LootArchetypeV3_RecordRef@lootArchetypeRecord');
    }

    public function getArchetype(): ?LootArchetypeV3Record
    {
        $resolved = $this->resolveRelatedDocument(
            'archetype/LootArchetypeV3',
            LootArchetypeV3Record::class,
            $this->getArchetypeReference(),
            static fn (string $reference): ?LootArchetypeV3Record => ServiceFactory::getFoundryLookupService()
                ->getLootArchetypeV3ByReference($reference)
        );

        return $resolved instanceof LootArchetypeV3Record ? $resolved : null;
    }

    public function getChoiceLimit(): ?int
    {
        return $this->getInt('optionalData/LootTableOptionalDataV3_ChoiceLimit@choiceLimit');
    }

    public function getDupeLimit(): ?int
    {
        return $this->getInt('optionalData/LootTableOptionalDataV3_DupeLimit@dupeLimit');
    }
}
