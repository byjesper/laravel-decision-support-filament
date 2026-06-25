<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\DecisionSupportManager;
use ByJesper\DecisionSupport\Enums\FactType;
use ByJesper\DecisionSupport\Enums\VersionStatus;
use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupport\Models\GuideVersion;
use ByJesper\DecisionSupport\Testing\FakeFactProvider;
use ByJesper\DecisionSupportFilament\Pages\GuideRunner;
use ByJesper\DecisionSupportFilament\Pages\GuideTreeEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function draftVersion(string $key = 'g'): GuideVersion
{
    $guide = Guide::create(['key' => $key, 'name' => 'G', 'profile' => 'freeform']);

    return $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Draft]);
}

it('renders node config field help text in the editor', function (): void {
    $version = draftVersion();

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        // The default draft type is "question"; its prompt field carries help text.
        ->assertSee('The question shown to the person running the guide.');
})->group('integration');

it('only offers translation inputs when locales are configured', function (): void {
    config(['decision-support-filament.locales' => []]);
    $version = draftVersion();

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->assertDontSee('prompt — da');

    config(['decision-support-filament.locales' => ['da']]);

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->assertSee('prompt — da');
})->group('integration');

it('persists a per-locale translation when adding a node', function (): void {
    config(['decision-support-filament.locales' => ['da']]);
    $version = draftVersion();

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->set('nodeDraft.type', 'question')
        ->set('nodeDraft.key', 'q')
        ->set('nodeDraft.config.prompt', 'Are you employed?')
        ->set('nodeDraft.config.fact', 'employed')
        ->set('nodeDraft.config.inputType', 'boolean')
        ->set('nodeDraft.config.prompt_i18n.da', 'Er du ansat?')
        ->call('addNode');

    expect($version->nodes()->where('key', 'q')->first()?->config['prompt_i18n'] ?? null)
        ->toBe(['da' => 'Er du ansat?']);
})->group('integration');

it('drops a blank per-locale translation so it never overrides the base', function (): void {
    config(['decision-support-filament.locales' => ['da']]);
    $version = draftVersion();

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->set('nodeDraft.type', 'question')
        ->set('nodeDraft.key', 'q')
        ->set('nodeDraft.config.prompt', 'Are you employed?')
        ->set('nodeDraft.config.fact', 'employed')
        ->set('nodeDraft.config.inputType', 'boolean')
        ->set('nodeDraft.config.prompt_i18n.da', '   ')
        ->call('addNode');

    expect($version->nodes()->where('key', 'q')->first()?->config)
        ->not->toHaveKey('prompt_i18n');
})->group('integration');

it('runs a guide in the panel locale', function (): void {
    app()->setLocale('da');

    app(DecisionSupportManager::class)->registerProvider(
        'g',
        FakeFactProvider::make()->declare('employed', FactType::Boolean),
    );

    $version = draftVersion();
    $q = $version->nodes()->create([
        'type' => 'question',
        'key' => 'q',
        'config' => [
            'prompt' => 'Are you employed?',
            'fact' => 'employed',
            'inputType' => 'boolean',
            'prompt_i18n' => ['da' => 'Er du ansat?'],
        ],
    ]);
    $yes = $version->nodes()->create(['type' => 'outcome', 'key' => 'yes', 'config' => ['verdict' => 'Eligible']]);
    $version->edges()->create(['from_node_id' => $q->id, 'to_node_id' => $yes->id, 'from_port' => 'true']);

    Livewire::test(GuideRunner::class, ['version' => $version->id])
        ->call('start')
        ->assertSee('Er du ansat?');
})->group('integration');
