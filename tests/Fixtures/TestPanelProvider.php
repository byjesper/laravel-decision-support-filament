<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament\Tests\Fixtures;

use ByJesper\DecisionSupportFilament\DecisionSupportPlugin;
use Filament\Panel;
use Filament\PanelProvider;

/**
 * A minimal host panel for the test suite — the same `->plugin()` registration a
 * real host performs in its own panel provider.
 */
final class TestPanelProvider extends PanelProvider
{
    #[\Override]
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(DecisionSupportPlugin::make());
    }
}
