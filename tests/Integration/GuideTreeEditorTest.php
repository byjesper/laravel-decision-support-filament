<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Enums\VersionStatus;
use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupport\Publishing\GuidePublisher;
use ByJesper\DecisionSupportFilament\Pages\GuideRunner;
use ByJesper\DecisionSupportFilament\Pages\GuideTreeEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** A draft with one boolean question routing to two outcomes — a valid, publishable graph. */
function fillableGraph(): array
{
    return [
        'nodes' => [
            ['type' => 'question', 'key' => 'q1', 'label' => null, 'config' => ['prompt' => 'Employed?', 'fact' => 'employed', 'inputType' => 'boolean']],
            ['type' => 'outcome', 'key' => 'yes', 'label' => null, 'config' => ['verdict' => 'Eligible']],
            ['type' => 'outcome', 'key' => 'no', 'label' => null, 'config' => ['verdict' => 'Not eligible']],
        ],
        'edges' => [
            ['from' => 'q1', 'fromPort' => 'true', 'to' => 'yes', 'conditionType' => 'always', 'fact' => '', 'operator' => '=', 'value' => '', 'expression' => ''],
            ['from' => 'q1', 'fromPort' => 'false', 'to' => 'no', 'conditionType' => 'always', 'fact' => '', 'operator' => '=', 'value' => '', 'expression' => ''],
        ],
    ];
}

it('renders with a live mermaid preview container', function (): void {
    $version = seedBooleanGuide();

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->assertOk()
        ->assertSee('Live preview')
        ->assertSeeHtml('data-decision-support-mermaid');
})->group('integration');

it('loads existing nodes into the form', function (): void {
    $version = seedBooleanGuide();

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->assertFormSet(fn (array $state): bool => count($state['nodes'] ?? []) === 3
            && collect($state['nodes'])->pluck('key')->contains('q1'));
})->group('integration');

it('saves nodes and edges from the form, replacing the draft rows', function (): void {
    $guide = Guide::create(['key' => 'g', 'name' => 'G', 'profile' => 'freeform']);
    $version = $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Draft]);

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->fillForm(fillableGraph())
        ->call('save')
        ->assertHasNoFormErrors();

    expect($version->nodes()->count())->toBe(3)
        ->and($version->edges()->count())->toBe(2);

    $this->assertDatabaseHas('guide_nodes', ['guide_version_id' => $version->id, 'key' => 'q1', 'type' => 'question']);
})->group('integration');

it('rejects a duplicate node key', function (): void {
    $guide = Guide::create(['key' => 'g', 'name' => 'G', 'profile' => 'freeform']);
    $version = $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Draft]);

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->fillForm([
            'nodes' => [
                ['type' => 'outcome', 'key' => 'dup', 'label' => null, 'config' => ['verdict' => 'A']],
                ['type' => 'outcome', 'key' => 'dup', 'label' => null, 'config' => ['verdict' => 'B']],
            ],
            'edges' => [],
        ])
        ->call('save')
        ->assertHasFormErrors();

    expect($version->nodes()->count())->toBe(0);
})->group('integration');

it('surfaces validation issues live and blocks publishing an invalid tree', function (): void {
    // A lone question with no outgoing edges — uncovered ports, non-outcome leaf.
    $guide = Guide::create(['key' => 'invalid', 'name' => 'Invalid', 'profile' => 'phased']);
    $version = $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Draft]);
    $version->nodes()->create(['type' => 'question', 'key' => 'q1', 'config' => ['prompt' => 'Hi?', 'fact' => 'x', 'inputType' => 'boolean']]);

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        // The live Validation panel reports the issues without publishing.
        ->assertSeeHtml('data-validation-issues')
        ->call('publish')
        ->assertSet('publishErrors', fn (array $errors): bool => $errors !== []);

    expect($version->fresh()?->status)->toBe(VersionStatus::Draft);
})->group('integration');

it('reports a clean guide as ready to publish', function (): void {
    $version = seedBooleanGuide();

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->assertSeeHtml('data-validation-ok')
        ->assertDontSeeHtml('data-validation-issues');
})->group('integration');

it('publishes a valid tree', function (): void {
    $version = seedBooleanGuide();

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->call('publish')
        ->assertSet('publishErrors', []);

    expect($version->fresh()?->status)->toBe(VersionStatus::Published);
})->group('integration');

it('previews the current unsaved form state', function (): void {
    $guide = Guide::create(['key' => 'g', 'name' => 'G', 'profile' => 'freeform']);
    $version = $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Draft]);

    // Node lives only in form state (never saved); the preview must still render it.
    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->fillForm([
            'nodes' => [['type' => 'outcome', 'key' => 'liveonly', 'label' => null, 'config' => ['verdict' => 'X']]],
            'edges' => [],
        ])
        ->assertSeeHtml('n_liveonly');
})->group('integration');

it('prevents creating a self-loop edge', function (): void {
    $guide = Guide::create(['key' => 'g', 'name' => 'G', 'profile' => 'freeform']);
    $version = $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Draft]);

    // The 'to' options exclude the chosen 'from', so a node cannot edge to itself.
    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->fillForm([
            'nodes' => [
                ['type' => 'question', 'key' => 'a', 'label' => null, 'config' => ['prompt' => 'A?', 'fact' => 'a', 'inputType' => 'boolean']],
                ['type' => 'outcome', 'key' => 'b', 'label' => null, 'config' => ['verdict' => 'B']],
            ],
            'edges' => [['from' => 'a', 'fromPort' => 'out', 'to' => 'a', 'conditionType' => 'always', 'fact' => '', 'operator' => '=', 'value' => '', 'expression' => '']],
        ])
        ->call('save')
        ->assertHasFormErrors();

    expect($version->edges()->count())->toBe(0);
})->group('integration');

it('saves the draft and opens the runner from the Test run action', function (): void {
    $guide = Guide::create(['key' => 'g', 'name' => 'G', 'profile' => 'freeform']);
    $version = $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Draft]);

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->fillForm([
            'nodes' => [['type' => 'outcome', 'key' => 'done', 'label' => null, 'config' => ['verdict' => 'Done']]],
            'edges' => [],
        ])
        ->callAction('run')
        ->assertRedirect(GuideRunner::getUrl(['version' => $version->id]));

    $this->assertDatabaseHas('guide_nodes', ['guide_version_id' => $version->id, 'key' => 'done']);
})->group('integration');

it('shows a read-only notice and hides Publish for a published version', function (): void {
    $version = seedBooleanGuide();
    app(GuidePublisher::class)->publish($version);

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->assertActionHidden('publish')
        ->assertSee('This version is published');
})->group('integration');

it('preserves the graph structure and metadata when saving a published version', function (): void {
    $version = seedBooleanGuide();
    app(GuidePublisher::class)->publish($version);
    $nodeCount = $version->nodes()->count();
    $edgeCount = $version->edges()->count();

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->fillForm(['extra_attributes' => ['permissions' => ['view-guide']]])
        ->call('save')
        ->assertHasNoFormErrors();

    $version->refresh();

    expect($version->nodes()->count())->toBe($nodeCount)
        ->and($version->edges()->count())->toBe($edgeCount)
        ->and($version->extra_attributes)->toBe(['permissions' => ['view-guide']]);
})->group('integration');

it('edits content on a published version in place while keeping structure', function (): void {
    $version = seedBooleanGuide();
    app(GuidePublisher::class)->publish($version);
    $question = $version->nodes()->where('key', 'q1')->firstOrFail();
    $originalId = $question->id;

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->fillForm([
            'nodes' => [
                ['type' => 'question', 'key' => 'q1', 'label' => 'Renamed', 'config' => ['prompt' => 'Updated prompt?', 'fact' => 'employed', 'inputType' => 'boolean']],
                ['type' => 'outcome', 'key' => 'yes', 'label' => null, 'config' => ['verdict' => 'Eligible']],
                ['type' => 'outcome', 'key' => 'no', 'label' => null, 'config' => ['verdict' => 'Not eligible']],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $question->refresh();

    expect($question->id)->toBe($originalId) // updated in place — edges stay wired
        ->and($question->label)->toBe('Renamed')
        ->and($question->config['prompt'] ?? null)->toBe('Updated prompt?')
        ->and($version->nodes()->count())->toBe(3)
        ->and($version->edges()->count())->toBe(2);
})->group('integration');

it('loads and saves the version metadata', function (): void {
    $guide = Guide::create(['key' => 'g', 'name' => 'G', 'profile' => 'freeform']);
    $version = $guide->versions()->create([
        'number' => 1,
        'status' => VersionStatus::Draft,
        'extra_attributes' => ['permissions' => ['run-guide']],
    ]);

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->assertFormSet(fn (array $state): bool => ($state['extra_attributes']['permissions'] ?? []) === ['run-guide'])
        ->fillForm(['extra_attributes' => ['permissions' => ['edit-guide']]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($version->fresh()?->extra_attributes)->toBe(['permissions' => ['edit-guide']]);
})->group('integration');
