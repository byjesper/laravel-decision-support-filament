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

/** @param array<string, string> $promptI18n */
function questionNodeState(array $promptI18n): array
{
    return [
        'nodes' => [
            ['type' => 'question', 'key' => 'q', 'label' => null, 'config' => [
                'prompt' => 'Are you employed?',
                'fact' => 'employed',
                'inputType' => 'boolean',
                'prompt_i18n' => $promptI18n,
            ]],
        ],
        'edges' => [],
    ];
}

it('persists a per-locale translation when saving a node', function (): void {
    config(['decision-support-filament.locales' => ['da']]);
    $version = draftVersion();

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->fillForm(questionNodeState(['da' => 'Er du ansat?']))
        ->call('save')
        ->assertHasNoFormErrors();

    expect($version->nodes()->where('key', 'q')->first()?->config['prompt_i18n'] ?? null)
        ->toBe(['da' => 'Er du ansat?']);
})->group('integration');

it('drops a blank per-locale translation so it never overrides the base', function (): void {
    config(['decision-support-filament.locales' => ['da']]);
    $version = draftVersion();

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->fillForm(questionNodeState(['da' => '   ']))
        ->call('save')
        ->assertHasNoFormErrors();

    expect($version->nodes()->where('key', 'q')->first()?->config)
        ->not->toHaveKey('prompt_i18n');
})->group('integration');

it('persists a per-locale node label and localizes the diagram for it', function (): void {
    config(['decision-support-filament.locales' => ['da']]);
    app()->setLocale('da');
    $version = draftVersion();

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->fillForm([
            'nodes' => [
                // A fact node otherwise shows its raw key in the diagram — a label fixes that.
                ['type' => 'fact', 'key' => 'cp', 'label' => 'Overlaps a CP', 'config' => [
                    'fact' => 'overlaps_any_cp',
                    'label_i18n' => ['da' => 'Overlapper en BP'],
                ]],
            ],
            'edges' => [],
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        // The live preview renders the Danish label, not the key or base label.
        ->assertSeeHtml('Overlapper en BP');

    expect($version->nodes()->where('key', 'cp')->first()?->config['label_i18n'] ?? null)
        ->toBe(['da' => 'Overlapper en BP']);
})->group('integration');

it('localizes the live validation panel in the panel locale', function (): void {
    app()->setLocale('da');
    app(DecisionSupportManager::class)->registerProvider(
        'g',
        FakeFactProvider::make()->declare('employed', FactType::Boolean),
    );

    $version = draftVersion();
    // A boolean question covering only its 'true' port → graph.uncovered_port for 'false'.
    $q = $version->nodes()->create(['type' => 'question', 'key' => 'q1', 'config' => ['prompt' => 'Ansat?', 'fact' => 'employed', 'inputType' => 'boolean']]);
    $yes = $version->nodes()->create(['type' => 'outcome', 'key' => 'yes', 'config' => ['verdict' => 'Ja']]);
    $version->edges()->create(['from_node_id' => $q->id, 'to_node_id' => $yes->id, 'from_port' => 'true']);

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->assertSee("Noden 'q1' har ingen udgående kant for porten 'false'.");
})->group('integration');

it('persists a custom edge label with translations and localizes the diagram', function (): void {
    config(['decision-support-filament.locales' => ['da']]);
    app()->setLocale('da');
    $version = draftVersion();

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->fillForm([
            'nodes' => [
                ['type' => 'decision', 'key' => 'd1', 'label' => null, 'config' => ['fact' => 'tenure']],
                ['type' => 'outcome', 'key' => 'yes', 'label' => null, 'config' => ['verdict' => 'Eligible']],
            ],
            'edges' => [
                ['from' => 'd1', 'fromPort' => 'out', 'to' => 'yes', 'conditionType' => 'always', 'fact' => '', 'operator' => '=', 'value' => '', 'expression' => '', 'label' => 'Long tenure', 'label_i18n' => ['da' => 'Lang anciennitet']],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        // The Danish edge label appears on the branch in the live preview.
        ->assertSeeHtml('Lang anciennitet');

    $edge = $version->edges()->first();

    expect($edge?->label)->toBe('Long tenure')
        ->and($edge?->label_i18n)->toBe(['da' => 'Lang anciennitet']);
})->group('integration');

it('localizes the node-type and input-type option labels', function (): void {
    app()->setLocale('da');
    $version = draftVersion();
    // A node must exist for a repeater item (and thus the type/input-type selects) to render.
    $version->nodes()->create(['type' => 'question', 'key' => 'q', 'config' => ['prompt' => 'Hi?', 'fact' => 'x', 'inputType' => 'boolean']]);

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->assertOk()
        // Node-type and input-type dropdowns render Danish labels, not raw keys.
        ->assertSee('Spørgsmål')   // node_type.question
        ->assertSee('Udfald')      // node_type.outcome
        ->assertSee('Ja / Nej');   // input_type.boolean
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
