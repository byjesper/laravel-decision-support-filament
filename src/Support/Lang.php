<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament\Support;

/**
 * Thin wrapper over the translator for this package's namespace. It guarantees a
 * `string` return (the framework's `__()` is typed `array|string`), so call sites
 * with `string` return types stay clean and type-safe at PHPStan level 8.
 */
final class Lang
{
    /**
     * @param  array<string, string|int>  $replace
     */
    public static function get(string $key, array $replace = []): string
    {
        $value = trans("decision-support-filament::{$key}", $replace);

        return is_string($value) ? $value : $key;
    }
}
