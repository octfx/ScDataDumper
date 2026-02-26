<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle;

use Octfx\ScDataDumper\Services\Vehicle\ItemTypeResolver;
use PHPUnit\Framework\TestCase;

final class ItemTypeResolverTest extends TestCase
{
    private ItemTypeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new ItemTypeResolver;
    }

    public function test_semantic_type_prefers_std_item_type_first(): void
    {
        $payload = [
            'stdItem' => ['Type' => 'Turret.GunTurret'],
            'type' => 'WeaponGun',
            'subType' => 'Gun',
            'Components' => [
                'SAttachableComponentParams' => [
                    'AttachDef' => [
                        'Type' => 'MainThruster',
                        'SubType' => 'Retro',
                    ],
                ],
            ],
            'Type' => 'Seat',
        ];

        self::assertSame('Turret.GunTurret', $this->resolver->resolveSemanticType($payload));
    }

    public function test_semantic_type_uses_inline_type_and_sub_type_when_std_item_type_is_missing(): void
    {
        $payload = [
            'type' => 'WeaponGun',
            'subType' => 'Gun',
            'Components' => [
                'SAttachableComponentParams' => [
                    'AttachDef' => [
                        'Type' => 'MainThruster',
                        'SubType' => 'Retro',
                    ],
                ],
            ],
            'Type' => 'Seat',
        ];

        self::assertSame('WeaponGun.Gun', $this->resolver->resolveSemanticType($payload));
    }

    public function test_semantic_type_uses_inline_type_without_sub_type(): void
    {
        $payload = [
            'type' => 'Seat',
        ];

        self::assertSame('Seat', $this->resolver->resolveSemanticType($payload));
    }

    public function test_semantic_type_uses_attach_def_type_with_sub_type_when_inline_type_is_missing(): void
    {
        $payload = [
            'Components' => [
                'SAttachableComponentParams' => [
                    'AttachDef' => [
                        'Type' => 'MainThruster',
                        'SubType' => 'Retro',
                    ],
                ],
            ],
            'Type' => 'Seat',
        ];

        self::assertSame('MainThruster.Retro', $this->resolver->resolveSemanticType($payload));
    }

    public function test_semantic_type_uses_attach_def_type_without_sub_type(): void
    {
        $payload = [
            'Components' => [
                'SAttachableComponentParams' => [
                    'AttachDef' => [
                        'Type' => 'CargoGrid',
                    ],
                ],
            ],
        ];

        self::assertSame('CargoGrid', $this->resolver->resolveSemanticType($payload));
    }

    public function test_semantic_type_uses_legacy_type_only_when_not_classifier_shaped(): void
    {
        self::assertSame('Seat', $this->resolver->resolveSemanticType(['Type' => 'Seat']));
        self::assertSame('ship.weapon.gun', $this->resolver->resolveSemanticType(['Type' => 'ship.weapon.gun']));
        self::assertNull($this->resolver->resolveSemanticType(['Type' => 'Ship.Weapon.Gun']));
    }

    public function test_semantic_type_unwraps_installed_item_from_port_payload(): void
    {
        $portPayload = [
            'Type' => 'PortLevelType',
            'InstalledItem' => [
                'stdItem' => ['Type' => 'WeaponGun.Gun'],
                'Type' => 'Ship.Weapon.Gun',
            ],
        ];

        self::assertSame('WeaponGun.Gun', $this->resolver->resolveSemanticType($portPayload));
    }

    public function test_classifier_prefers_lowercase_classification_first(): void
    {
        $payload = [
            'classification' => 'ship.weapon.gun',
            'Classification' => 'Ship.Other',
            'Type' => 'Ship.Turret.GunTurret',
        ];

        self::assertSame('ship.weapon.gun', $this->resolver->resolveClassifier($payload));
    }

    public function test_classifier_uses_legacy_classification_when_lowercase_key_is_missing(): void
    {
        $payload = [
            'Classification' => 'Ship.Weapon.Gun',
            'Type' => 'Ship.Turret.GunTurret',
        ];

        self::assertSame('Ship.Weapon.Gun', $this->resolver->resolveClassifier($payload));
    }

    public function test_classifier_uses_legacy_type_only_when_classifier_shaped(): void
    {
        self::assertSame('Ship.Turret.GunTurret', $this->resolver->resolveClassifier(['Type' => 'Ship.Turret.GunTurret']));
        self::assertNull($this->resolver->resolveClassifier(['Type' => 'ship.turret.gunturret']));
    }

    public function test_classifier_uses_item_classifier_service_as_final_fallback(): void
    {
        $payload = [
            'Components' => [
                'SAttachableComponentParams' => [
                    'AttachDef' => [
                        'Type' => 'WeaponGun',
                        'SubType' => 'Gun',
                    ],
                ],
            ],
        ];

        self::assertSame('Ship.Weapon.Gun', $this->resolver->resolveClassifier($payload));
    }

    public function test_classifier_unwraps_installed_item_from_port_payload(): void
    {
        $portPayload = [
            'classification' => 'port.level',
            'InstalledItem' => [
                'classification' => 'ship.weapon.gun',
            ],
        ];

        self::assertSame('ship.weapon.gun', $this->resolver->resolveClassifier($portPayload));
    }

    public function test_classifier_returns_null_when_no_fallback_produces_a_value(): void
    {
        self::assertNull($this->resolver->resolveClassifier([]));
    }

    public function test_classifier_shaped_detection_is_strict_and_case_sensitive(): void
    {
        self::assertTrue(ItemTypeResolver::isClassifierShaped('Ship.Turret.GunTurret'));
        self::assertTrue(ItemTypeResolver::isClassifierShaped('FPS.Weapon.Rifle'));
        self::assertTrue(ItemTypeResolver::isClassifierShaped('Mining.Module'));

        self::assertFalse(ItemTypeResolver::isClassifierShaped('ship.Turret.GunTurret'));
        self::assertFalse(ItemTypeResolver::isClassifierShaped('fps.Weapon.Rifle'));
        self::assertFalse(ItemTypeResolver::isClassifierShaped('MINING.Module'));
        self::assertFalse(ItemTypeResolver::isClassifierShaped('MiningModule'));
    }
}
