<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\DecisionSupportManager;
use ByJesper\DecisionSupport\Enums\FactType;
use ByJesper\DecisionSupport\Enums\VersionStatus;
use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupport\Models\GuideVersion;
use ByJesper\DecisionSupport\Publishing\GuidePublisher;
use ByJesper\DecisionSupport\Testing\FakeFactProvider;
use ByJesper\DecisionSupportFilament\Pages\GuideRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * A published one-question guide whose `true` branch reaches an outcome carrying
 * the given text/warnings — for exercising how the runner renders an outcome.
 *
 * @param  list<string>  $warnings
 */
function seedOutcomeGuide(string $text, array $warnings = []): GuideVersion
{
    app(DecisionSupportManager::class)->registerProvider(
        'oc',
        FakeFactProvider::make()->declare('employed', FactType::Boolean),
    );

    $guide = Guide::create(['key' => 'oc', 'name' => 'Outcome', 'profile' => 'phased']);
    $version = $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Draft]);

    $q = $version->nodes()->create(['type' => 'question', 'key' => 'q1', 'config' => ['prompt' => 'Are you employed?', 'fact' => 'employed', 'inputType' => 'boolean']]);
    $yes = $version->nodes()->create(['type' => 'outcome', 'key' => 'yes', 'config' => ['verdict' => 'Eligible', 'text' => $text, 'warnings' => $warnings]]);
    $no = $version->nodes()->create(['type' => 'outcome', 'key' => 'no', 'config' => ['verdict' => 'Not eligible']]);

    $version->edges()->create(['from_node_id' => $q->id, 'to_node_id' => $yes->id, 'from_port' => 'true']);
    $version->edges()->create(['from_node_id' => $q->id, 'to_node_id' => $no->id, 'from_port' => 'false']);

    app(GuidePublisher::class)->publish($version);

    return $version;
}

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

it('localizes the runner chrome via the translation namespace', function (): void {
    app()->setLocale('da');
    app('translator')->addLines(
        ['runner.section.start' => 'Begynd', 'runner.action.start' => 'Kør'],
        'da',
        'decision-support-filament',
    );

    $version = seedBooleanGuide();

    Livewire::test(GuideRunner::class, ['version' => $version->id])
        ->assertSee('Begynd')
        ->assertSee('Kør');
})->group('integration');

it('renders outcome text as markdown', function (): void {
    $version = seedOutcomeGuide("**Do this:**\n\n- Reset seniority\n- Change person type");

    Livewire::test(GuideRunner::class, ['version' => $version->id])
        ->call('start')
        ->call('submit', 'true')
        ->assertSeeHtml('<strong>Do this:</strong>')
        ->assertSeeHtml('<ul>')
        ->assertSeeHtml('<li>Reset seniority</li>');
})->group('integration');

it('escapes raw html embedded in outcome text', function (): void {
    $version = seedOutcomeGuide('Before <script>alert(1)</script> after');

    Livewire::test(GuideRunner::class, ['version' => $version->id])
        ->call('start')
        ->call('submit', 'true')
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertSeeHtml('&lt;script&gt;');
})->group('integration');

it('renders plain outcome text unchanged', function (): void {
    $version = seedOutcomeGuide('Just a plain sentence.');

    Livewire::test(GuideRunner::class, ['version' => $version->id])
        ->call('start')
        ->call('submit', 'true')
        ->assertSee('Just a plain sentence.');
})->group('integration');

it('styles the warnings box with literal CSS, not host-uncompiled Tailwind utilities', function (): void {
    $version = seedOutcomeGuide('Done.', ['Mind the gap']);

    Livewire::test(GuideRunner::class, ['version' => $version->id])
        ->call('start')
        ->call('submit', 'true')
        ->assertSeeHtml('data-warnings')
        ->assertSee('Mind the gap')
        ->assertDontSeeHtml('bg-warning-50');
})->group('integration');

it('wires the input and submit button to disable and spin during a submit', function (): void {
    // A free-text/number question renders the input + Submit button wired to
    // disable for the duration of the submit request (so a slow external lookup
    // reads as progress, not a frozen form).
    app(DecisionSupportManager::class)->registerProvider(
        'num',
        FakeFactProvider::make()->declare('age', FactType::Number),
    );
    $guide = Guide::create(['key' => 'num', 'name' => 'Num', 'profile' => 'freeform']);
    $version = $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Draft]);
    $version->nodes()->create(['type' => 'question', 'key' => 'q1', 'config' => ['prompt' => 'How old?', 'fact' => 'age', 'inputType' => 'number']]);

    Livewire::test(GuideRunner::class, ['version' => $version->id])
        ->call('start')
        ->assertSee('How old?')
        ->assertSeeHtml('wire:target="submit"')
        ->assertSeeHtml('wire:loading.attr="disabled"');
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
