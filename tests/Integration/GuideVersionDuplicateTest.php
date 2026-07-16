<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Enums\VersionStatus;
use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages\EditGuide;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\RelationManagers\VersionsRelationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('preserves edge labels and translations when duplicating a version', function (): void {
    $guide = Guide::create(['key' => 'g', 'name' => 'G', 'profile' => 'freeform']);
    $version = $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Published]);

    $q = $version->nodes()->create(['type' => 'question', 'key' => 'q1', 'config' => ['prompt' => 'Employed?', 'fact' => 'employed', 'inputType' => 'boolean']]);
    $yes = $version->nodes()->create(['type' => 'outcome', 'key' => 'yes', 'config' => ['verdict' => 'Yes']]);
    $no = $version->nodes()->create(['type' => 'outcome', 'key' => 'no', 'config' => ['verdict' => 'No']]);

    $version->edges()->create([
        'from_node_id' => $q->id,
        'to_node_id' => $yes->id,
        'from_port' => 'true',
        'label' => 'Employed',
        'label_i18n' => ['da' => 'Ansat'],
    ]);
    $version->edges()->create(['from_node_id' => $q->id, 'to_node_id' => $no->id, 'from_port' => 'false']);

    Livewire::test(VersionsRelationManager::class, ['ownerRecord' => $guide, 'pageClass' => EditGuide::class])
        ->callTableAction('duplicate', $version);

    $copy = $guide->versions()->where('number', 2)->firstOrFail();
    $copyEdge = $copy->edges()->where('from_port', 'true')->firstOrFail();

    expect($copyEdge->label)->toBe('Employed')
        ->and($copyEdge->label_i18n)->toBe(['da' => 'Ansat'])
        // Node/edge parity too, so a future column added to one side doesn't
        // silently regress the duplicate the way label/label_i18n did.
        ->and($copy->nodes()->count())->toBe($version->nodes()->count())
        ->and($copy->edges()->count())->toBe($version->edges()->count());
})->group('integration');
