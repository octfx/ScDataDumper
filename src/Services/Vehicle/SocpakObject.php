<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

readonly class SocpakObject
{
    public function __construct(
        public string $className,
        public string $instanceName,
        public string $section,
        public ?string $layer,
        public string $socpakPath,
    ) {}
}
