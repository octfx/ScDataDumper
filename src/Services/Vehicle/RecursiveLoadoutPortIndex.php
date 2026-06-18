<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

/**
 * Flat index over an annotated loadout tree (from LoadoutPortIdentityAnnotator).
 *
 * Provides a flat ordered port list (top-level first, then depth-first),
 * O(1) lookup by PortId, and lightweight reference objects with normalized Type/SubType.
 */
final class RecursiveLoadoutPortIndex implements \Countable
{
    /**
     * Flat list of all ports in traversal order.
     *
     * @var list<array<string, mixed>>
     */
    private array $ports = [];

    /**
     * Map of PortId -> port entry for O(1) lookup.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $byPortId = [];

    /** @param  list<array<string, mixed>>  $annotatedLoadout */
    public function build(array $annotatedLoadout): self
    {
        $this->ports = [];
        $this->byPortId = [];

        $this->indexPorts($annotatedLoadout);

        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->ports;
    }

    /** @return array<string, mixed>|null */
    public function findByPortId(string $portId): ?array
    {
        return $this->byPortId[$portId] ?? null;
    }

    /**
     * Build a reference object (VehicleSystemKeys::PORT_REF_KEYS) with normalized Type/SubType.
     *
     * @return array<string, mixed>|null
     */
    public function getReferenceObject(string $portId): ?array
    {
        $port = $this->findByPortId($portId);

        if ($port === null) {
            return null;
        }

        return $this->buildReferenceObject($port);
    }

    public function count(): int
    {
        return count($this->ports);
    }

    /**
     * Pre-order traversal: parent first, then children depth-first.
     *
     * @param  list<array<string, mixed>>  $ports
     */
    private function indexPorts(array $ports): void
    {
        foreach ($ports as $port) {
            $portId = $port['PortId'] ?? null;

            if ($portId !== null) {
                $this->ports[] = $port;
                $this->byPortId[$portId] = $port;
            }

            if (isset($port['Loadout']) && is_array($port['Loadout']) && ! empty($port['Loadout'])) {
                $this->indexPorts($port['Loadout']);
            }
        }
    }

    /**
     * Build a reference object from an annotated port, splitting the raw
     * "Type.SubType" string (e.g. "WeaponGun.Gun" -> Type/SubType; "UNDEFINED" -> null).
     *
     * @param  array<string, mixed>  $port
     * @return array<string, mixed>
     */
    private function buildReferenceObject(array $port): array
    {
        [$type, $subType] = $this->normalizeType($port['Type'] ?? null);

        return [
            'PortId' => $port['PortId'] ?? null,
            'HardpointName' => $port['HardpointName'] ?? null,
            'Type' => $type,
            'SubType' => $subType,
            'UUID' => $port['UUID'] ?? null,
            'ClassName' => $port['ClassName'] ?? null,
            'ParentPortId' => $port['ParentPortId'] ?? null,
            'RootPortId' => $port['RootPortId'] ?? null,
            'Path' => $port['Path'] ?? [],

        ];
    }

    /** @return array{0: string|null, 1: string|null} */
    private function normalizeType(?string $rawType): array
    {
        if ($rawType === null) {
            return [null, null];
        }

        $parts = explode('.', $rawType, 2);
        $type = $parts[0];
        $subType = $parts[1] ?? null;

        // "UNDEFINED" is not a meaningful subtype
        if ($subType === 'UNDEFINED') {
            $subType = null;
        }

        return [$type, $subType];
    }
}
