<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament;

use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;

final class DecisionSupportFilamentServiceProvider extends ServiceProvider
{
    public const string ASSET_PACKAGE = 'byjesper/laravel-decision-support-filament';

    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/decision-support-filament.php', 'decision-support-filament');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'decision-support-filament');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'decision-support-filament');
        $this->registerAssets();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/decision-support-filament.php' => config_path('decision-support-filament.php'),
            ], 'decision-support-filament-config');

            $this->publishes([
                __DIR__.'/../resources/views' => base_path('resources/views/vendor/decision-support-filament'),
            ], 'decision-support-filament-views');

            $this->publishes([
                __DIR__.'/../resources/lang' => base_path('lang/vendor/decision-support-filament'),
            ], 'decision-support-filament-translations');
        }
    }

    /**
     * Register the bundled mermaid loader so hosts never have to wire the npm
     * dependency themselves. Filament copies it to the public assets path on
     * `php artisan filament:assets`.
     */
    private function registerAssets(): void
    {
        FilamentAsset::register([
            Js::make('decision-support', __DIR__.'/../resources/dist/decision-support.js')->module(),
        ], self::ASSET_PACKAGE);
    }
}
