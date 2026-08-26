<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupportFilament\Resources\GuideResource;
use ByJesper\DecisionSupportFilament\Tests\Fixtures\PermissionCatalog;

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

it('resolves a per-guide catalog from an invokable class-string', function (): void {
    // The serializable catalog form: a class-string survives var_export(), so the
    // host keeps config:cache (see #27). Resolved through the container per guide.
    expect(GuideResource::resolvePermissionOptions(PermissionCatalog::class, new Guide(['key' => 'a'])))
        ->toBe(['perm-a' => 'Permission for a'])
        ->and(GuideResource::resolvePermissionOptions(PermissionCatalog::class, null))
        ->toBe(['perm-none' => 'Permission for none']);
});

it('resolves the catalog class through the container, so it can take dependencies', function (): void {
    app()->bind(PermissionCatalog::class, fn (): PermissionCatalog => new PermissionCatalog('scoped'));

    expect(GuideResource::resolvePermissionOptions(PermissionCatalog::class, new Guide(['key' => 'a'])))
        ->toBe(['scoped-a' => 'Permission for a']);
});

it('ignores a class-string that is not invokable', function (): void {
    expect(GuideResource::resolvePermissionOptions(Guide::class, null))->toBe([]);
});

it('reports a class-string catalog as configured', function (): void {
    config(['decision-support-filament.permissions.options' => PermissionCatalog::class]);
    expect(GuideResource::hasPermissionCatalog())->toBeTrue();

    config(['decision-support-filament.permissions.options' => Guide::class]);
    expect(GuideResource::hasPermissionCatalog())->toBeFalse();
});

it('keeps a class-string catalog serializable, unlike a closure', function (): void {
    // What Laravel's config:cache actually does — var_export() the config array
    // and re-evaluate it. A Closure has no __set_state(); a class-string is a
    // plain string and round-trips.
    $exported = var_export(['options' => PermissionCatalog::class], true);

    /** @var array{options: string} $restored */
    $restored = eval("return {$exported};");

    expect($restored['options'])->toBe(PermissionCatalog::class)
        ->and(GuideResource::resolvePermissionOptions($restored['options'], null))
        ->toBe(['perm-none' => 'Permission for none']);
});
