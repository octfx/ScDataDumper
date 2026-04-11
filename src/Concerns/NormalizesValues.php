<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Concerns;

use Illuminate\Support\Str;
use Octfx\ScDataDumper\Formats\BaseFormat;

trait NormalizesValues
{
    protected static array $pascalCaseAcronyms = [
        'Uuid' => 'UUID',
        'Scu' => 'SCU',
        'Ifcs' => 'IFCS',
        'Emp' => 'EMP',
        'StdItem' => 'stdItem',
    ];

    protected function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    protected function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value !== 0 && $value !== 0.0;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value !== 0.0;
        }

        return false;
    }

    protected function normalizeNumber(mixed $value): int|float|null
    {
        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
            return null;
        }

        $number = (float) $value;

        if ((float) ((int) $number) === $number) {
            return (int) $number;
        }

        return $number;
    }

    protected function toPascalCase(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (ctype_upper($value[0]) && ! str_contains($value, '_') && ! str_contains($value, '-')) {
            return self::$pascalCaseAcronyms[$value] ?? $value;
        }

        $result = Str::pascal($value);

        $result = self::$pascalCaseAcronyms[$result] ?? $result;

        if (str_ends_with($result, 'Uuid')) {
            $result = rtrim($result, 'Uuid').'UUID';
        }

        return $result;
    }

    protected function transformArrayKeysToPascalCase(array|null|BaseFormat $data): array
    {
        if ($data === null) {
            return [];
        }

        if ($data instanceof BaseFormat) {
            return $data->toArray() ?? [];
        }

        $result = [];

        foreach ($data as $key => $value) {
            $pascalKey = is_string($key) ? $this->toPascalCase($key) : $key;
            $result[$pascalKey] = is_array($value)
                ? $this->transformArrayKeysToPascalCase($value)
                : $value;
        }

        return $result;
    }
}
