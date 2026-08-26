<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament\Tests\Fixtures;

use ByJesper\DecisionSupport\Models\Guide;

/**
 * A host-style invokable permission catalog — the serializable alternative to a
 * closure in `permissions.options`, so an adopter with a dynamic catalog can
 * still run `config:cache`. Resolved through the container, so it may take
 * constructor dependencies; here a prefix stands in for one.
 */
final readonly class PermissionCatalog
{
    public function __construct(private string $prefix = 'perm') {}

    /** @return array<string, string> */
    public function __invoke(?Guide $guide): array
    {
        $key = $guide?->key ?? 'none';

        return ["{$this->prefix}-{$key}" => "Permission for {$key}"];
    }
}
