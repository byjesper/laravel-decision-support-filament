<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Enums\VersionStatus;
use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupportFilament\Pages\GuideTreeEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders a per-guide permission catalog as a multi-select in the tree editor', function (): void {
    // A closure catalog resolved per guide — guide 'a' sees a different list than others.
    config(['decision-support-filament.permissions.options' => fn (?Guide $guide): array => $guide?->key === 'a'
        ? ['perm-a' => 'Perm A']
        : ['perm-z' => 'Perm Z']]);

    $guide = Guide::create(['key' => 'a', 'name' => 'A', 'profile' => 'freeform']);
    $version = $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Draft]);

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->assertOk()
        // The multi-select offers this guide's catalog, not another guide's.
        ->assertSee('Perm A')
        ->assertDontSee('Perm Z');
})->group('integration');

it('shows an info callout instead of a field when no catalog is configured', function (): void {
    config(['decision-support-filament.permissions.options' => null]);

    $guide = Guide::create(['key' => 'g', 'name' => 'G', 'profile' => 'freeform']);
    $version = $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Draft]);

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->assertOk()
        // The "permission gating not possible" callout, not a tags/select input.
        ->assertSee('No permission catalog is configured', false)
        ->assertDontSeeHtml('fi-fo-tags-input');
})->group('integration');

it('does not wipe stored permissions when saving with no catalog configured', function (): void {
    config(['decision-support-filament.permissions.options' => null]);

    $guide = Guide::create(['key' => 'g', 'name' => 'G', 'profile' => 'freeform']);
    $version = $guide->versions()->create([
        'number' => 1,
        'status' => VersionStatus::Draft,
        'extra_attributes' => ['permissions' => ['legacy-perm']],
    ]);

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->call('save')
        ->assertHasNoFormErrors();

    // The stored permissions survive (and the mode defaults in, since permissions exist).
    expect($version->fresh()?->extra_attributes)->toBe(['permissions' => ['legacy-perm'], 'permissions_mode' => 'any']);
})->group('integration');

it('lets an author remove stored permissions even with no catalog configured', function (): void {
    config(['decision-support-filament.permissions.options' => null]);

    $guide = Guide::create(['key' => 'g', 'name' => 'G', 'profile' => 'freeform']);
    $version = $guide->versions()->create([
        'number' => 1,
        'status' => VersionStatus::Draft,
        'extra_attributes' => ['permissions' => ['keep-this', 'remove-this']],
    ]);

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->assertOk()
        // A removable multi-select renders (next to the no-catalog callout) because
        // permissions exist, so the author can drop one and save.
        ->assertSee('keep-this')
        ->fillForm(['extra_attributes' => ['permissions' => ['keep-this']]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($version->fresh()?->extra_attributes['permissions'] ?? null)->toBe(['keep-this']);
})->group('integration');

it('pre-selects the default OR match mode on an edit form when none is stored', function (): void {
    config(['decision-support-filament.permissions.options' => ['run-guide']]);

    $guide = Guide::create(['key' => 'g', 'name' => 'G', 'profile' => 'freeform']);
    $version = $guide->versions()->create([
        'number' => 1,
        'status' => VersionStatus::Draft,
        'extra_attributes' => ['permissions' => ['run-guide']],
    ]);

    // No permissions_mode stored, yet the radio shows the configured default selected.
    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->assertFormSet(fn (array $state): bool => ($state['extra_attributes']['permissions_mode'] ?? null) === 'any');
})->group('integration');
