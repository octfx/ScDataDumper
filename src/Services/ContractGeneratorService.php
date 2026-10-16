<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Octfx\ScDataDumper\DocumentTypes\Contract\ContractGeneratorRecord;

/**
 * @extends DocumentPathService<ContractGeneratorRecord>
 */
final class ContractGeneratorService extends DocumentPathService
{
    /**
     * @var array<string, ContractGeneratorRecord>
     */
    protected static array $documentCache = [];

    protected function documentClass(): string
    {
        return ContractGeneratorRecord::class;
    }

    protected function cacheMapKey(): string
    {
        return 'ContractGenerator';
    }
}
