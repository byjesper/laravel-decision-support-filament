<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Publishing\GuidePublisher;
use ByJesper\DecisionSupportFilament\Pages\GuideRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders an always-visible mermaid path container', function (): void {
    $version = seedBooleanGuide();

    Livewire::test(GuideRunner::class, ['version' => $version->id])
        ->assertOk()
        ->assertSee('Path')
        ->assertSeeHtml('data-decision-support-mermaid');
})->group('integration');

it('drives a run from question to verdict', function (): void {
    $version = seedBooleanGuide();
    app(GuidePublisher::class)->publish($version);

    Livewire::test(GuideRunner::class, ['version' => $version->id])
        ->call('start')
        ->assertSee('Are you employed?')
        ->call('submit', 'true')
        ->assertSee('Eligible');
})->group('integration');

it('routes the other branch from the same question', function (): void {
    $version = seedBooleanGuide();
    app(GuidePublisher::class)->publish($version);

    Livewire::test(GuideRunner::class, ['version' => $version->id])
        ->call('start')
        ->call('submit', 'false')
        ->assertSee('Not eligible');
})->group('integration');

it('steps back to the previous question', function (): void {
    $version = seedBooleanGuide();
    app(GuidePublisher::class)->publish($version);

    Livewire::test(GuideRunner::class, ['version' => $version->id])
        ->call('start')
        ->call('submit', 'true')
        ->assertSee('Eligible')
        // Back returns to the suspended question and empties the history.
        ->call('back')
        ->assertSee('Are you employed?')
        ->assertSet('state', fn (?array $state): bool => ($state['status'] ?? null) === 'suspended')
        ->assertSet('history', []);
})->group('integration');
