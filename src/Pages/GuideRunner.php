<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament\Pages;

use ByJesper\DecisionSupport\Definition\GuideDefinition;
use ByJesper\DecisionSupport\Mermaid\MermaidRenderer;
use ByJesper\DecisionSupport\Models\GuideVersion;
use ByJesper\DecisionSupport\Runtime\GuideRunner as Engine;
use ByJesper\DecisionSupport\Runtime\Interaction;
use ByJesper\DecisionSupport\Runtime\Outcome;
use ByJesper\DecisionSupport\Runtime\RunState;
use Filament\Pages\Page;
use Filament\Panel;

/**
 * Runs a guide version through the engine's resumable interpreter. It renders
 * each {@see Interaction} the run suspends on (a question or provider lookup),
 * feeds the captured value back through {@see Engine::advance()}, and shows the
 * terminal verdict — all over an always-visible Mermaid diagram that highlights
 * the reached path. The {@see RunState} lives in the Livewire payload as a plain
 * array, so a run survives every round-trip without server-side session state.
 *
 * @property-read string $mermaidSource
 */
final class GuideRunner extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'decision-support-filament::pages.guide-runner';

    public int $version;

    public string $input = '';

    /** @var array<string, mixed>|null */
    public ?array $state = null;

    #[\Override]
    public static function getRoutePath(Panel $panel): string
    {
        return '/'.self::getSlug($panel).'/{version}';
    }

    public function mount(int $version): void
    {
        $this->version = $version;
        $this->record();
    }

    #[\Override]
    public function getTitle(): string
    {
        $record = $this->record();

        return "Run — {$record->guide->name} v{$record->number}";
    }

    public function record(): GuideVersion
    {
        return GuideVersion::query()->with('guide')->findOrFail($this->version);
    }

    public function definition(): GuideDefinition
    {
        return $this->record()->toDefinition();
    }

    public function start(): void
    {
        $this->input = '';
        $this->state = $this->engine()->start($this->definition())->toArray();
    }

    public function submit(?string $value = null): void
    {
        if ($this->state === null) {
            return;
        }

        $input = $value ?? $this->input;
        $state = $this->engine()->advance($this->definition(), RunState::fromArray($this->state), $input);

        $this->state = $state->toArray();
        $this->input = '';
    }

    public function restart(): void
    {
        $this->state = null;
        $this->input = '';
    }

    public function runState(): ?RunState
    {
        return $this->state === null ? null : RunState::fromArray($this->state);
    }

    public function interaction(): ?Interaction
    {
        $state = $this->runState();

        return $state !== null && $state->isSuspended() ? $state->pendingInteraction : null;
    }

    public function outcome(): ?Outcome
    {
        $state = $this->runState();

        return $state !== null && $state->isCompleted() ? $state->outcome : null;
    }

    public function getMermaidSourceProperty(): string
    {
        return (new MermaidRenderer)->render($this->definition(), $this->runState());
    }

    private function engine(): Engine
    {
        return app(Engine::class);
    }
}
