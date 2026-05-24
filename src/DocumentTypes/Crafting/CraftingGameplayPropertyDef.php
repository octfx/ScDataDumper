<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Crafting;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Helper\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RuntimeException;

final class CraftingGameplayPropertyDef extends RootDocument
{
    public function getPropertyKey(): string
    {
        $source = pathinfo($this->getPath(), PATHINFO_FILENAME);

        if ($source === '') {
            $source = $this->getClassName();
        }

        $normalized = strtolower($source);

        if (str_starts_with($normalized, 'gpp_')) {
            $normalized = substr($normalized, 4);
        }

        return $normalized;
    }

    public function getNormalizedPropertyKey(): ?string
    {
        $key = $this->getPropertyKey();

        return $key === '' ? null : $key;
    }

    /**
     * Returns the unit format string for display purposes.
     *
     * Returns null when the value is '@LOC_EMPTY' or when the attribute is absent.
     */
    public function getUnitFormat(): ?string
    {
        $value = $this->getString('@unitFormat');

        if ($value === '@LOC_EMPTY' || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * Returns the display transformation scale factor, or null when not defined.
     *
     * mostly quantum_speed (1E-06), itemresource_coolantgeneration (1E-06), quantum_fuelrequirement (1000),
     * weapon_tractor_maxvolume (0.0001).
     */
    public function getDisplayScale(): ?float
    {
        $scale = $this->get('displayTransformation/CraftingDisplayTransformation_Scale@scale');

        if ($scale === null) {
            return null;
        }

        return (float) $scale;
    }

    /**
     * Returns structured name override data, or null when not defined.
     *
     * @return list<array{property_name: string, match_item_types: list<string>}>|null
     */
    public function getNameOverrides(): ?array
    {
        $overrides = $this->get('nameOverrides');

        if (! $overrides instanceof Element) {
            return null;
        }

        $results = [];

        foreach ($overrides->children() as $override) {
            $nodeName = $override->nodeName ?? '';

            if ($nodeName !== 'CraftingPropertyNameOverride') {
                continue;
            }

            $propertyName = $override->get('@propertyName');
            $matchTypes = [];

            $matchItemTypes = $override->get('condition/CraftingPropertyNameOverrideCondition_ItemType/matchItemTypes');

            if ($matchItemTypes instanceof Element) {
                foreach ($matchItemTypes->children() as $enum) {
                    if (($enum->nodeName ?? '') === 'Enum') {
                        $value = $enum->get('@value');

                        if (is_string($value) && $value !== '') {
                            $matchTypes[] = $value;
                        }
                    }
                }
            }

            $results[] = [
                'property_name' => $propertyName,
                'match_item_types' => $matchTypes,
            ];
        }

        return $results === [] ? null : $results;
    }

    /**
     * Returns the localized display name for this property.
     *
     * Resolves the @propertyName attribute via LocalizationService.
     * For @-prefixed keys, returns the translated text or null when unresolvable.
     * For plain text (no @ prefix), returns the text as-is.
     * Returns null when the attribute is absent.
     */
    public function getPropertyName(): ?string
    {
        $rawName = $this->getString('@propertyName');

        if ($rawName === null || $rawName === '') {
            return null;
        }

        try {
            return ServiceFactory::getLocalizationService()->translateValue($rawName);
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * Returns the resolved display name, applying name overrides based on item type.
     *
     * If the output item type matches a name override's matchItemTypes, the override's
     * localized property name is returned. Otherwise, falls back to the base property name.
     */
    public function getResolvedPropertyName(?string $itemType): ?string
    {
        if ($itemType !== null) {
            $overrides = $this->getNameOverrides();

            if ($overrides !== null) {
                foreach ($overrides as $override) {
                    if (in_array($itemType, $override['match_item_types'], true)) {
                        $rawOverrideName = $override['property_name'];

                        try {
                            $translated = ServiceFactory::getLocalizationService()->translateValue($rawOverrideName);
                        } catch (RuntimeException) {
                            break;
                        }

                        if ($translated !== null) {
                            return $translated;
                        }

                        break;
                    }
                }
            }
        }

        return $this->getPropertyName();
    }

    public static function resolveFromModifier(Element $modifier): ?self
    {
        $property = $modifier->get('GameplayProperty');

        if ($property instanceof Element) {
            $resolved = self::fromNode($property->getNode());

            if ($resolved instanceof self) {
                return $resolved;
            }
        }

        $reference = $modifier->get('@gameplayPropertyRecord');

        return is_string($reference) && $reference !== ''
            ? ServiceFactory::getFoundryLookupService()->getCraftingGameplayPropertyByReference($reference)
            : null;
    }
}
