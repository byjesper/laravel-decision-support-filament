<?php

declare(strict_types=1);

it('merges the package configuration', function (): void {
    expect(config('decision-support-filament.enabled'))->toBeTrue();
});
