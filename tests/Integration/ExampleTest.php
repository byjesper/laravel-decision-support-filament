<?php

declare(strict_types=1);

// Tests tagged with the "integration" group are excluded from `composer test:unit`
// and `composer test:parallel`, and run only under `composer test:integration`.
// Use this group for tests that need a real database or other external services.
it('runs in the integration suite', function (): void {
    expect(true)->toBeTrue();
})->group('integration');
