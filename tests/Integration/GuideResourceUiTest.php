<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupportFilament\Resources\GuideResource;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages\CreateGuide;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages\EditGuide;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages\ListGuides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// --- B: navigation + model labels from config -------------------------------

it('uses the configured navigation label', function (): void {
    config(['decision-support-filament.navigation.label' => 'Decision guides']);

    expect(GuideResource::getNavigationLabel())->toBe('Decision guides');
});

it('falls back to the default navigation label when unset', function (): void {
    config(['decision-support-filament.navigation.label' => null]);

    expect(GuideResource::getNavigationLabel())->toBe('Guides');
});

it('uses the configured model labels', function (): void {
    config([
        'decision-support-filament.labels.model' => 'Decision guide',
        'decision-support-filament.labels.plural' => 'Decision guides',
    ]);

    expect(GuideResource::getModelLabel())->toBe('Decision guide')
        ->and(GuideResource::getPluralModelLabel())->toBe('Decision guides');
});

// --- C2: pages stay open for host subclassing -------------------------------

it('keeps the resource and its CRUD pages open for extension', function (): void {
    expect((new ReflectionClass(GuideResource::class))->isFinal())->toBeFalse()
        ->and((new ReflectionClass(CreateGuide::class))->isFinal())->toBeFalse()
        ->and((new ReflectionClass(EditGuide::class))->isFinal())->toBeFalse()
        ->and((new ReflectionClass(ListGuides::class))->isFinal())->toBeFalse();
});

// --- C3: create-flow layout -------------------------------------------------

it('registers a standalone create page by default', function (): void {
    config(['decision-support-filament.forms.layout' => 'page']);

    expect(array_keys(GuideResource::getPages()))->toContain('index', 'create', 'edit');
});

it('drops the create page for modal and slideover layouts', function (string $layout): void {
    config(['decision-support-filament.forms.layout' => $layout]);

    $pages = array_keys(GuideResource::getPages());

    expect($pages)->toContain('index', 'edit')
        ->and($pages)->not->toContain('create');
})->with(['modal', 'slideover']);

// --- C1: key/profile immutability -------------------------------------------

it('allows setting the key when creating a guide', function (): void {
    Livewire::test(CreateGuide::class)
        ->assertFormFieldEnabled('key');
})->group('integration');

it('locks the key on edit and preserves it when saving', function (): void {
    $guide = Guide::create(['key' => 'original', 'name' => 'Original', 'profile' => 'phased']);

    Livewire::test(EditGuide::class, ['record' => $guide->getRouteKey()])
        ->assertFormFieldDisabled('key')
        ->fillForm(['name' => 'Renamed'])
        ->call('save')
        ->assertHasNoFormErrors();

    $guide->refresh();

    expect($guide->key)->toBe('original')
        ->and($guide->name)->toBe('Renamed');
})->group('integration');

it('locks the profile on edit but allows it on create', function (): void {
    Livewire::test(CreateGuide::class)
        ->assertFormFieldEnabled('profile');

    $guide = Guide::create(['key' => 'g', 'name' => 'G', 'profile' => 'phased']);

    // Profile is chosen at creation and fixed afterwards (the tree is authored against it).
    Livewire::test(EditGuide::class, ['record' => $guide->getRouteKey()])
        ->assertFormFieldDisabled('profile');
})->group('integration');

// --- E: Run action on the guide list table ----------------------------------

it('disables the list Run action when the guide has no active version', function (): void {
    $guide = seedBooleanGuide()->guide;

    Livewire::test(ListGuides::class)
        ->assertTableActionDisabled('run', $guide);
})->group('integration');

it('enables the list Run action once a version is active', function (): void {
    $version = seedBooleanGuide();
    $guide = $version->guide;
    $guide->update(['active_version_id' => $version->id]);

    Livewire::test(ListGuides::class)
        ->assertTableActionEnabled('run', $guide);
})->group('integration');
