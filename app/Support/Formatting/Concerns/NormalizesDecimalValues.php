<?php

namespace App\Support\Formatting\Concerns;

trait NormalizesDecimalValues
{
    protected function normalizeDecimalToFloat(mixed $value, ?int $precision = 2): float
    {
        $normalized = $this->normalizeDecimalToNullableString($value);

        $float = $normalized === null ? 0.0 : (float) $normalized;

        return $precision === null ? $float : round($float, $precision);
    }

    protected function normalizeDecimalToNullableFloat(mixed $value, ?int $precision = 2): ?float
    {
        $normalized = $this->normalizeDecimalToNullableString($value);

        if ($normalized === null) {
            return null;
        }

        $float = (float) $normalized;

        return $precision === null ? $float : round($float, $precision);
    }

    protected function normalizeDecimalToNullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        $clean = preg_replace('/[^0-9,.-]/', '', (string) $value);
        $clean = str_replace(['. ', ' '], '', $clean);
        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);

        return $clean === '' ? null : $clean;
    }
}

