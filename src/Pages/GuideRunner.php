<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament\Pages;

use ByJesper\DecisionSupport\Definition\GuideDefinition;
use ByJesper\DecisionSupport\Mermaid\MermaidRenderer;
use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupport\Models\GuideVersion;
use ByJesper\DecisionSupport\Runtime\GuideRunner as Engine;
use ByJesper\DecisionSupport\Runtime\Interaction;
use ByJesper\DecisionSupport\Runtime\Outcome;
use ByJesper\DecisionSupport\Runtime\RunState;
use ByJesper\DecisionSupportFilament\Resources\GuideResource;
use ByJesper\DecisionSupportFilament\Support\Lang;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Runs a guide version through the engine's resumable interpreter. It renders
 * each {@see Interaction} the run suspends on (a question or provider lookup),
 * feeds the captured value back through {@see Engine::advance()}, and shows the
 * terminal verdict — all over an always-visible Mermaid diagram that highlights
 * the reached path. The {@see RunState} lives in the Livewire payload as a plain
 * array, so a run survives every round-trip without server-side session state.
 *
 * Two modes:
 *
 * - **Version-keyed (default):** the route carries a `{version}` parameter, so
 *   any version — draft or published — can be run. This powers the "Run" action
 *   on {@see GuideResource}.
 * - **Pinned (opt-in):** a host subclass sets {@see static::$guideKey} to bind
 *   the page to one guide. The route loses its parameter and the page always
 *   serves that guide's *currently-active published* version — the production
 *   shape for a fixed navigation entry. Override {@see static::canAccess()},
 *   `$navigationGroup`, and `$shouldRegisterNavigation` on the subclass to place
 *   and authorize it.
 *
 * @property-read string $mermaidSource
 */
class GuideRunner extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    /**
     * When a subclass pins a guide key, the page drops its `{version}` route
     * parameter and resolves that guide's active published version instead of
     * accepting an arbitrary version from the URL.
     */
    protected static ?string $guideKey = null;

    protected string $view = 'decision-support-filament::pages.guide-runner';

    public int $version;

    public string $input = '';

    /**
     * Validation message shown under the current question's input — set when a
     * required question is submitted blank, cleared on any successful move.
     */
    public ?string $inputError = null;

    /** @var array<string, mixed>|null */
    public ?array $state = null;

    /**
     * Stack of prior run states so the runner can step back. The engine is
     * forward-only, so we remember each state before advancing past it.
     *
     * @var list<array<string, mixed>>
     */
    public array $history = [];

    #[\Override]
    public static function getRoutePath(Panel $panel): string
    {
        return static::$guideKey !== null
            ? '/'.static::getSlug($panel)
            : '/'.static::getSlug($panel).'/{version}';
    }

    #[\Override]
    public static function canAccess(): bool
    {
        // Version-keyed mode stays permissive (the resource gates the entry to
        // it). A pinned page defers to the host's Guide policy so production
        // access flows through it; a subclass may override for a permission.
        if (static::$guideKey === null) {
            return true;
        }

        $guide = Guide::query()->where('key', static::$guideKey)->first();

        return $guide !== null && Gate::allows('view', $guide);
    }

    public function mount(?int $version = null): void
    {
        $this->version = static::$guideKey !== null
            ? $this->resolveActiveVersion(static::$guideKey)
            : (int) $version;

        $this->record();
    }

    /**
     * Resolve a pinned guide's currently-active published version, 404-ing when
     * the guide is unknown or has nothing published yet.
     */
    protected function resolveActiveVersion(string $guideKey): int
    {
        $guide = Guide::query()->where('key', $guideKey)->firstOrFail();

        abort_if($guide->active_version_id === null, 404, "Guide '{$guideKey}' has no published version.");

        return $guide->active_version_id;
    }

    #[\Override]
    public function getTitle(): string
    {
        $record = $this->record();

        return Lang::get('runner.title', [
            'guide' => $record->guide->name,
            'version' => $record->number,
        ]);
    }

    /**
     * Breadcrumbs back to the resource for the version-keyed (test) runner. A
     * pinned end-user runner is its own navigation entry, so it gets none.
     *
     * @return array<int|string, string>
     */
    #[\Override]
    public function getBreadcrumbs(): array
    {
        if (static::$guideKey !== null) {
            return [];
        }

        $record = $this->record();

        return [
            GuideResource::getUrl() => Str::ucfirst(GuideResource::getPluralModelLabel()),
            GuideResource::getUrl('edit', ['record' => $record->guide]) => $record->guide->name,
            Lang::get('runner.breadcrumb', ['version' => $record->number]),
        ];
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
        $this->inputError = null;
        $this->history = [];
        // Render content in the panel's active locale (carried on the run state, so
        // it survives every advance), falling back to the configured fallback locale
        // and then each field's base string.
        $this->state = $this->engine()
            ->start($this->definition(), [], app()->getLocale(), $this->fallbackLocale())
            ->toArray();
    }

    /** Step back to the previous run state (undo the last answer). */
    public function back(): void
    {
        if ($this->history === []) {
            return;
        }

        $this->state = array_pop($this->history);
        $this->input = '';
        $this->inputError = null;
    }

    public function canGoBack(): bool
    {
        return $this->history !== [];
    }

    private function fallbackLocale(): ?string
    {
        $fallback = config('decision-support-filament.fallback_locale');

        return is_string($fallback) && $fallback !== '' ? $fallback : null;
    }

    public function submit(?string $value = null): void
    {
        if ($this->state === null) {
            return;
        }

        $input = $value ?? $this->input;

        // A required free question must carry a non-blank answer. Surface a
        // validation message and stay put rather than advancing (the engine
        // re-suspends on blank too, but catching it here gives the user a reason).
        $interaction = $this->interaction();
        if ($interaction !== null && $interaction->required && trim((string) $input) === '') {
            $this->inputError = Lang::get('runner.error.required');

            return;
        }

        $this->inputError = null;

        // Remember the state we are leaving so "Back" can return to it.
        $this->history[] = $this->state;

        $state = $this->engine()->advance($this->definition(), RunState::fromArray($this->state), $input);

        $this->state = $state->toArray();
        $this->input = '';
    }

    public function restart(): void
    {
        $this->state = null;
        $this->input = '';
        $this->inputError = null;
        $this->history = [];
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
        // Render the path diagram in the panel's active locale so node labels,
        // prompts and verdicts read in the same language as the rest of the run
        // (including the always-visible pre-start diagram, which has no run state).
        return (new MermaidRenderer)->render(
            $this->definition(),
            $this->runState(),
            app()->getLocale(),
            $this->fallbackLocale(),
        );
    }

    /**
     * Render author-supplied content (an outcome's text, a question prompt) as
     * Markdown, so authors can present a scannable "what to do" list instead of
     * one dense paragraph. Raw HTML in the source is escaped (not interpreted)
     * and unsafe links are stripped, so the result is safe to emit unescaped.
     * Plain text round-trips unchanged apart from a paragraph wrapper, so this is
     * fully backward compatible. Returns null for empty/blank input.
     */
    public function markdown(?string $text): ?HtmlString
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        return new HtmlString(Str::markdown($text, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]));
    }

    private function engine(): Engine
    {
        return app(Engine::class);
    }
}
