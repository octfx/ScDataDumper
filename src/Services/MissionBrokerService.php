<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Octfx\ScDataDumper\DocumentTypes\Mission\MissionBrokerEntry;

/**
 * @extends DocumentPathService<MissionBrokerEntry>
 */
final class MissionBrokerService extends DocumentPathService
{
    /**
     * @var array<string, MissionBrokerEntry>
     */
    protected static array $documentCache = [];

    public function count(): int
    {
        return $this->countPathsByClass();
    }

    protected function documentClass(): string
    {
        return MissionBrokerEntry::class;
    }

    protected function cacheMapKey(): string
    {
        return 'MissionBrokerEntry';
    }
}
