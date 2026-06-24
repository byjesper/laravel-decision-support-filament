<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\DecisionSupportManager;
use ByJesper\DecisionSupport\Enums\FactType;
use ByJesper\DecisionSupport\Enums\VersionStatus;
use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupport\Models\GuideVersion;
use ByJesper\DecisionSupport\Testing\FakeFactProvider;
use ByJesper\DecisionSupportFilament\Tests\TestCase;

uses(TestCase::class)->in('Unit', 'Feature', 'Integration');

/**
 * Seed a minimal two-outcome boolean guide as draft rows, optionally wiring a
 * fact provider so publishing and running resolve the `employed` fact.
 */
function seedBooleanGuide(string $key = 'eligibility', bool $wireProvider = true): GuideVersion
{
    if ($wireProvider) {
        app(DecisionSupportManager::class)->registerProvider(
            $key,
            FakeFactProvider::make()->declare('employed', FactType::Boolean),
        );
    }

    $guide = Guide::create(['key' => $key, 'name' => 'Eligibility', 'profile' => 'phased']);
    $version = $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Draft]);

    $q = $version->nodes()->create([
        'type' => 'question',
        'key' => 'q1',
        'config' => ['prompt' => 'Are you employed?', 'fact' => 'employed', 'inputType' => 'boolean'],
    ]);
    $yes = $version->nodes()->create(['type' => 'outcome', 'key' => 'yes', 'config' => ['verdict' => 'Eligible']]);
    $no = $version->nodes()->create(['type' => 'outcome', 'key' => 'no', 'config' => ['verdict' => 'Not eligible']]);

    $version->edges()->create(['from_node_id' => $q->id, 'to_node_id' => $yes->id, 'from_port' => 'true']);
    $version->edges()->create(['from_node_id' => $q->id, 'to_node_id' => $no->id, 'from_port' => 'false']);

    return $version;
}
