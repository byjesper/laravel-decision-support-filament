<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament;

use ByJesper\DecisionSupportFilament\Pages\GuideRunner;
use ByJesper\DecisionSupportFilament\Pages\GuideTreeEditor;
use ByJesper\DecisionSupportFilament\Resources\GuideResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

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

    public static function make(): self
    {
        return new self;
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
