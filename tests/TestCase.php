<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use ByJesper\DecisionSupport\DecisionSupportServiceProvider;
use ByJesper\DecisionSupportFilament\DecisionSupportFilamentServiceProvider;
use ByJesper\DecisionSupportFilament\Tests\Fixtures\TestPanelProvider;
use ByJesper\DecisionSupportFilament\Tests\Fixtures\TestUser;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Livewire\LivewireServiceProvider;
use Livewire\Mechanisms\DataStore;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Livewire keeps per-component state (error bags, schema state) in its
        // DataStore. Testbench's bootstrap leaves that binding unshared, so a
        // value set during a render is lost on the next resolve and component
        // rendering blows up. Pin it as a real singleton for the test panel.
        $this->app->singleton(DataStore::class);

        // Authenticate against the host panel so page components render under
        // the same context a real panel request would provide.
        $this->actingAs(new TestUser(['id' => 1, 'name' => 'Tester', 'email' => 'tester@example.com']));
        Filament::setCurrentPanel('admin');
    }

    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            DecisionSupportServiceProvider::class,
            DecisionSupportFilamentServiceProvider::class,
            TestPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.timezone', 'UTC');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
    }
}
