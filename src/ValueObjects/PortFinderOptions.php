<?php

namespace Octfx\ScDataDumper\ValueObjects;

/**
 * Options for port finding operations
 */
final class PortFinderOptions
{
    public bool $stopOnFind;

    /** @var callable|null */
    public $stopPredicate;

    public function __construct(
        bool $stopOnFind = false,
        ?callable $stopPredicate = null
    ) {
        $this->stopOnFind = $stopOnFind;
        $this->stopPredicate = $stopPredicate;
    }

    /**
     * Check if search should stop at this item
     */
    public function shouldStop(array $item): bool
    {
        return $this->stopPredicate !== null && ($this->stopPredicate)($item);
    }
}
