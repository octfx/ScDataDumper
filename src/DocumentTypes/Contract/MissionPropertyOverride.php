<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract;

use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\AINameValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\BooleanValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\CombinedDataSetEntriesValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\EntitySpawnDescriptionsValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\FloatValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\HaulingOrdersValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\IntegerValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\LocationsValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\LocationValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\MissionItemValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\NPCSpawnDescriptionsValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\OrganizationValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\RewardValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\ShipSpawnDescriptionsValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\StringHashValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\TagsValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\TimeTrialRaceValue;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class MissionPropertyOverride extends RootDocument
{
    private static array $typeClassMap = [
        'MissionPropertyValue_Boolean' => BooleanValue::class,
        'MissionPropertyValue_Integer' => IntegerValue::class,
        'MissionPropertyValue_Float' => FloatValue::class,
        'MissionPropertyValue_StringHash' => StringHashValue::class,
        'MissionPropertyValue_AIName' => AINameValue::class,
        'MissionPropertyValue_Tags' => TagsValue::class,
        'MissionPropertyValue_Location' => LocationValue::class,
        'MissionPropertyValue_Locations' => LocationsValue::class,
        'MissionPropertyValue_Organization' => OrganizationValue::class,
        'MissionPropertyValue_MissionItem' => MissionItemValue::class,
        'MissionPropertyValue_Reward' => RewardValue::class,
        'MissionPropertyValue_ShipSpawnDescriptions' => ShipSpawnDescriptionsValue::class,
        'MissionPropertyValue_NPCSpawnDescriptions' => NPCSpawnDescriptionsValue::class,
        'MissionPropertyValue_EntitySpawnDescriptions' => EntitySpawnDescriptionsValue::class,
        'MissionPropertyValue_HaulingOrders' => HaulingOrdersValue::class,
        'MissionPropertyValue_CombinedDataSetEntries' => CombinedDataSetEntriesValue::class,
        'MissionPropertyValue_TimeTrialRace' => TimeTrialRaceValue::class,
    ];

    public function getMissionVariableName(): ?string
    {
        return $this->getString('@missionVariableName');
    }

    public function getExtendedTextToken(): ?string
    {
        return $this->getString('@extendedTextToken');
    }

    public function getValueTypeName(): ?string
    {
        $valueNode = $this->get('value');
        if ($valueNode === null) {
            return null;
        }

        foreach ($valueNode->children() as $child) {
            return $child->nodeName;
        }

        return null;
    }

    public function getValue(): ?RootDocument
    {
        $valueNode = $this->get('value');
        if ($valueNode === null) {
            return null;
        }

        foreach ($valueNode->children() as $child) {
            $typeName = $child->nodeName;

            $class = self::$typeClassMap[$typeName] ?? null;
            if ($class === null) {
                return null;
            }

            $doc = $class::fromNode($child->getNode(), $this->isReferenceHydrationEnabled());

            return $doc instanceof $class ? $doc : null;
        }

        return null;
    }
}
