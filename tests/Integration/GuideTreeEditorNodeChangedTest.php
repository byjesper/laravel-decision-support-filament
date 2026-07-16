<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Enums\VersionStatus;
use ByJesper\DecisionSupport\Events\NodeChanged;
use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupport\Publishing\GuidePublisher;
use ByJesper\DecisionSupportFilament\Pages\GuideTreeEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** A valid three-node boolean graph as editor form state. */
function editorGraphState(): array
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

it('dispatches NodeChanged for every node added on a draft save', function (): void {
    $guide = Guide::create(['key' => 'g', 'name' => 'G', 'profile' => 'freeform']);
    $version = $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Draft]);

    Event::fake([NodeChanged::class]);

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->fillForm(editorGraphState())
        ->call('save')
        ->assertHasNoFormErrors();

    Event::assertDispatchedTimes(NodeChanged::class, 3);
    Event::assertDispatched(
        NodeChanged::class,
        static fn (NodeChanged $e): bool => $e->guideKey === 'g' && $e->version === 1 && $e->nodeKey === 'q1',
    );
})->group('integration');

it('dispatches no NodeChanged when a draft save changes nothing', function (): void {
    $guide = Guide::create(['key' => 'g', 'name' => 'G', 'profile' => 'freeform']);
    $version = $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Draft]);

    // Save once to establish the rows.
    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->fillForm(editorGraphState())
        ->call('save')
        ->assertHasNoFormErrors();

    // Re-open and save again without touching anything.
    Event::fake([NodeChanged::class]);

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->call('save')
        ->assertHasNoFormErrors();

    Event::assertNotDispatched(NodeChanged::class);
})->group('integration');

it('dispatches NodeChanged for a node edited in place on a published version', function (): void {
    $version = seedBooleanGuide();
    app(GuidePublisher::class)->publish($version);

    Event::fake([NodeChanged::class]);

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

    // q1's label and prompt genuinely changed → it must fire (published path uses
    // exact per-node wasChanged()).
    Event::assertDispatched(
        NodeChanged::class,
        static fn (NodeChanged $e): bool => $e->nodeKey === 'q1' && $e->guideKey === 'eligibility' && $e->version === 1,
    );
})->group('integration');
