<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament;

use ByJesper\DecisionSupportFilament\Pages\GuideRunner;
use ByJesper\DecisionSupportFilament\Pages\GuideTreeEditor;
use ByJesper\DecisionSupportFilament\Resources\GuideResource;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Registers the decision-support editor and runner with a host panel. Hosts opt
 * in by string in their panel provider:
 *
 *     $panel->plugin(DecisionSupportPlugin::make())
 *
 * The plugin contributes the {@see GuideResource} (guide CRUD + versions) and
 * the two standalone pages — the {@see GuideTreeEditor} and the {@see GuideRunner}
 * — both keyed to a guide version via a route parameter. Authorization is left
 * to the host: the resource defers to whatever Guide policy (if any) the host
 * registers, so it is permissive until the host restricts it.
 */
final class DecisionSupportPlugin implements Plugin
{
    public const string ID = 'decision-support';

    /**
     * Optional host hook to scope the guide list to viewable rows in SQL. When
     * set, {@see GuideResource::getEloquentQuery()} applies it and skips the PHP
     * policy filter entirely — a single indexed query for hosts whose visibility
     * is SQL-expressible.
     *
     * @var (Closure(Builder<Model>, Authenticatable): Builder<Model>)|null
     */
    private ?Closure $scopeGuideListUsing = null;

    public static function make(): self
    {
        return new self;
    }

    /**
     * Register a SQL scope for the guide list table. The closure receives the base
     * query and the current user and returns a constrained query. Prefer this over
     * the PHP policy fallback when your gating is expressible in SQL (e.g. a role
     * check or `whereJsonContains` on `extra_attributes->permissions`).
     *
     * @param  Closure(Builder<Model>, Authenticatable): Builder<Model>  $callback
     */
    public function scopeGuideListUsing(Closure $callback): static
    {
        $this->scopeGuideListUsing = $callback;

        return $this;
    }

    /** @return (Closure(Builder<Model>, Authenticatable): Builder<Model>)|null */
    public function guideListScope(): ?Closure
    {
        return $this->scopeGuideListUsing;
    }

    #[\Override]
    public function getId(): string
    {
        return self::ID;
    }

    #[\Override]
    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                GuideResource::class,
            ])
            ->pages([
                GuideTreeEditor::class,
                GuideRunner::class,
            ]);
    }

    #[\Override]
    public function boot(Panel $panel): void {}
}
