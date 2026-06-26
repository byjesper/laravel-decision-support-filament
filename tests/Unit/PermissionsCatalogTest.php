<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupportFilament\Resources\GuideResource;

it('normalizes a fixed array catalog (list or value => label)', function (): void {
    expect(GuideResource::resolvePermissionOptions(['view-guide', 'run-guide'], null))
        ->toBe(['view-guide' => 'view-guide', 'run-guide' => 'run-guide'])
        ->and(GuideResource::resolvePermissionOptions(['view-guide' => 'View guide'], null))
        ->toBe(['view-guide' => 'View guide']);
});

it('resolves a per-guide catalog from a closure receiving the guide', function (): void {
    $guideA = new Guide(['key' => 'a']);
    $guideB = new Guide(['key' => 'b']);

    $options = fn (?Guide $guide): array => $guide?->key === 'a'
        ? ['perm-a' => 'Perm A']
        : ['perm-b' => 'Perm B'];

    expect(GuideResource::resolvePermissionOptions($options, $guideA))->toBe(['perm-a' => 'Perm A'])
        ->and(GuideResource::resolvePermissionOptions($options, $guideB))->toBe(['perm-b' => 'Perm B'])
        ->and(GuideResource::resolvePermissionOptions($options, null))->toBe(['perm-b' => 'Perm B']);
});

it('yields no options for a non-array / non-closure value or a non-array closure result', function (): void {
    expect(GuideResource::resolvePermissionOptions(null, null))->toBe([])
        ->and(GuideResource::resolvePermissionOptions('nonsense', null))->toBe([])
        ->and(GuideResource::resolvePermissionOptions(fn (): ?array => null, null))->toBe([]);
});
