<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Enums\VersionStatus;
use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupportFilament\Pages\GuideTreeEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders with a live mermaid preview container', function (): void {
    $version = seedBooleanGuide();

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->assertOk()
        ->assertSee('Live preview')
        ->assertSeeHtml('data-decision-support-mermaid');
})->group('integration');

it('adds a node to the draft', function (): void {
    $guide = Guide::create(['key' => 'g', 'name' => 'G', 'profile' => 'freeform']);
    $version = $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Draft]);

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->set('nodeDraft.type', 'outcome')
        ->set('nodeDraft.key', 'done')
        ->set('nodeDraft.config.verdict', 'Done')
        ->call('addNode');

    $this->assertDatabaseHas('guide_nodes', [
        'guide_version_id' => $version->id,
        'key' => 'done',
        'type' => 'outcome',
    ]);
})->group('integration');

it('rejects a duplicate node key', function (): void {
    $version = seedBooleanGuide();

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->set('nodeDraft.type', 'outcome')
        ->set('nodeDraft.key', 'yes')
        ->set('nodeDraft.config.verdict', 'Dup')
        ->call('addNode');

    expect($version->nodes()->where('key', 'yes')->count())->toBe(1);
})->group('integration');

it('adds an edge between existing nodes', function (): void {
    $guide = Guide::create(['key' => 'g2', 'name' => 'G2', 'profile' => 'freeform']);
    $version = $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Draft]);
    $version->nodes()->create(['type' => 'decision', 'key' => 'd', 'config' => []]);
    $version->nodes()->create(['type' => 'outcome', 'key' => 'o', 'config' => ['verdict' => 'Out']]);

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->set('edgeDraft.from', 'd')
        ->set('edgeDraft.to', 'o')
        ->set('edgeDraft.fromPort', 'out')
        ->set('edgeDraft.conditionType', 'always')
        ->call('addEdge');

    expect($version->edges()->count())->toBe(1);
})->group('integration');

it('blocks publishing an invalid tree and surfaces the failures inline', function (): void {
    // A guide whose only node is a question with no outgoing edges — the publish
    // validator must reject it (uncovered ports, non-outcome leaf).
    $guide = Guide::create(['key' => 'invalid', 'name' => 'Invalid', 'profile' => 'phased']);
    $version = $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Draft]);
    $version->nodes()->create([
        'type' => 'question',
        'key' => 'q1',
        'config' => ['prompt' => 'Hi?', 'fact' => 'x', 'inputType' => 'boolean'],
    ]);

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->call('publish')
        ->assertSet('publishErrors', fn (array $errors): bool => $errors !== [])
        ->assertSeeHtml('data-publish-errors');

    expect($version->fresh()?->status)->toBe(VersionStatus::Draft);
})->group('integration');

it('publishes a valid tree', function (): void {
    $version = seedBooleanGuide();

    Livewire::test(GuideTreeEditor::class, ['version' => $version->id])
        ->call('publish')
        ->assertSet('publishErrors', []);

    expect($version->fresh()?->status)->toBe(VersionStatus::Published);
})->group('integration');
