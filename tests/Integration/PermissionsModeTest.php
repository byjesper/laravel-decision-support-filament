<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupportFilament\Resources\GuideResource;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages\CreateGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('stores the permission-match mode, defaulting to OR (any)', function (): void {
    config(['decision-support-filament.permissions.options' => ['run-guide']]);
    Livewire::test(CreateGuide::class)
        ->fillForm([
            'key' => 'g',
            'name' => 'G',
            'profile' => 'phased',
            'extra_attributes' => ['permissions' => ['run-guide']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Guide::firstWhere('key', 'g')?->extra_attributes['permissions_mode'] ?? null)->toBe('any');
})->group('integration');

it('persists a chosen AND (all) mode', function (): void {
    config(['decision-support-filament.permissions.options' => ['a', 'b']]);
    Livewire::test(CreateGuide::class)
        ->fillForm([
            'key' => 'g',
            'name' => 'G',
            'profile' => 'phased',
            'extra_attributes' => ['permissions' => ['a', 'b'], 'permissions_mode' => 'all'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Guide::firstWhere('key', 'g')?->extra_attributes['permissions_mode'] ?? null)->toBe('all');
})->group('integration');

it('resolves the configured default mode, falling back to OR', function (): void {
    config(['decision-support-filament.permissions.mode' => 'all']);
    expect(GuideResource::defaultPermissionsMode())->toBe('all');

    config(['decision-support-filament.permissions.mode' => 'nonsense']);
    expect(GuideResource::defaultPermissionsMode())->toBe('any');
})->group('integration');
