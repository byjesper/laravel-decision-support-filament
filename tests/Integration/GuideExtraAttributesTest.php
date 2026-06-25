<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Enums\VersionStatus;
use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages\CreateGuide;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages\EditGuide;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\RelationManagers\VersionsRelationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function versionsManager(Guide $guide): Testable
{
    return Livewire::test(VersionsRelationManager::class, [
        'ownerRecord' => $guide,
        'pageClass' => EditGuide::class,
    ]);
}

it('stores required permissions from the guide form', function (): void {
    Livewire::test(CreateGuide::class)
        ->fillForm([
            'key' => 'eligibility',
            'name' => 'Eligibility',
            'profile' => 'phased',
            'extra_attributes' => ['permissions' => ['view-guide', 'run-guide']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Guide::firstWhere('key', 'eligibility')?->extra_attributes)
        ->toBe(['permissions' => ['view-guide', 'run-guide']]);
})->group('integration');

it('copies the guide attributes down to a new draft', function (): void {
    $guide = Guide::create([
        'key' => 'eligibility',
        'name' => 'Eligibility',
        'profile' => 'phased',
        'extra_attributes' => ['permissions' => ['run-guide']],
    ]);

    versionsManager($guide)->callTableAction('createDraft');

    expect($guide->versions()->latest('id')->first()?->extra_attributes)
        ->toBe(['permissions' => ['run-guide']]);
})->group('integration');

it('creates a draft for a guide that has no attributes', function (): void {
    $guide = Guide::create(['key' => 'eligibility', 'name' => 'Eligibility', 'profile' => 'phased']);

    versionsManager($guide)->callTableAction('createDraft');

    expect($guide->versions()->latest('id')->first()?->extra_attributes)->toBe([]);
})->group('integration');

it('offers Edit metadata on drafts but not published versions', function (): void {
    $guide = Guide::create(['key' => 'eligibility', 'name' => 'Eligibility', 'profile' => 'phased']);
    $draft = $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Draft]);
    $published = $guide->versions()->create(['number' => 2, 'status' => VersionStatus::Published]);

    versionsManager($guide)
        ->assertTableActionVisible('editMetadata', $draft)
        ->assertTableActionHidden('editMetadata', $published);
})->group('integration');
