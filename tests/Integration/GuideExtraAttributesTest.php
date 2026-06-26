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
    config(['decision-support-filament.permissions.options' => ['view-guide', 'run-guide']]);
    Livewire::test(CreateGuide::class)
        ->fillForm([
            'key' => 'eligibility',
            'name' => 'Eligibility',
            'profile' => 'phased',
            'extra_attributes' => ['permissions' => ['view-guide', 'run-guide']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // The permission-match mode is stored alongside, defaulting to OR ('any').
    expect(Guide::firstWhere('key', 'eligibility')?->extra_attributes)
        ->toBe(['permissions' => ['view-guide', 'run-guide'], 'permissions_mode' => 'any']);
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

it('duplicates a version into a new editable draft', function (): void {
    $version = seedBooleanGuide();
    $version->update(['extra_attributes' => ['permissions' => ['run-guide']]]);
    $guide = $version->guide;

    versionsManager($guide)->callTableAction('duplicate', $version);

    $copy = $guide->versions()->where('number', 2)->first();

    expect($copy)->not->toBeNull()
        ->and($copy?->status)->toBe(VersionStatus::Draft)
        ->and($copy?->nodes()->count())->toBe($version->nodes()->count())
        ->and($copy?->edges()->count())->toBe($version->edges()->count())
        ->and($copy?->extra_attributes)->toBe(['permissions' => ['run-guide']]);

    // The copied edges must reference the copied nodes, not the source's.
    $copyNodeIds = $copy?->nodes()->pluck('id')->all() ?? [];
    foreach ($copy?->edges ?? [] as $edge) {
        expect($copyNodeIds)->toContain($edge->from_node_id)
            ->and($copyNodeIds)->toContain($edge->to_node_id);
    }
})->group('integration');

it('offers Edit metadata on drafts but not published versions', function (): void {
    $guide = Guide::create(['key' => 'eligibility', 'name' => 'Eligibility', 'profile' => 'phased']);
    $draft = $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Draft]);
    $published = $guide->versions()->create(['number' => 2, 'status' => VersionStatus::Published]);

    versionsManager($guide)
        ->assertTableActionVisible('editMetadata', $draft)
        ->assertTableActionHidden('editMetadata', $published);
})->group('integration');
