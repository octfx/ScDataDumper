<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

final class FoundryRecord extends RootDocument
{
    public function getStringValue(string $path): ?string
    {
        return $this->getString($path);
    }

    public function getIntValue(string $path): ?int
    {
        return $this->getInt($path);
    }

    public function getFloatValue(string $path): ?float
    {
        return $this->getFloat($path);
    }

    public function getBoolValue(string $path): bool
    {
        return $this->getBool($path);
    }

    public function getValue(string $path): mixed
    {
        return $this->get($path);
    }
}
