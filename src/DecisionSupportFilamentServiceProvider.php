<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament;

use Illuminate\Support\ServiceProvider;

class DecisionSupportFilamentServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/decision-support-filament.php', 'decision-support-filament');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/decision-support-filament.php' => config_path('decision-support-filament.php'),
            ], 'decision-support-filament-config');
        }
    }
}
