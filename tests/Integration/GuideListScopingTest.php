<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupportFilament\DecisionSupportPlugin;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages\ListGuides;
use ByJesper\DecisionSupportFilament\Tests\Fixtures\PartialViewGuidePolicy;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows every guide when no Guide policy is registered', function (): void {
    Guide::create(['key' => 'open', 'name' => 'Open guide', 'profile' => 'phased']);
    Guide::create(['key' => 'secret', 'name' => 'Secret guide', 'profile' => 'phased']);

    Livewire::test(ListGuides::class)
        ->assertSee('Open guide')
        ->assertSee('Secret guide');
})->group('integration');

it('scopes the list to guides the user can view when a policy is registered', function (): void {
    Gate::policy(Guide::class, PartialViewGuidePolicy::class);
    Guide::create(['key' => 'open', 'name' => 'Open guide', 'profile' => 'phased']);
    Guide::create(['key' => 'secret', 'name' => 'Secret guide', 'profile' => 'phased']);

    Livewire::test(ListGuides::class)
        ->assertSee('Open guide')
        ->assertDontSee('Secret guide');
})->group('integration');

it('shows an empty list when no guide is viewable', function (): void {
    Gate::policy(Guide::class, PartialViewGuidePolicy::class);
    Guide::create(['key' => 'secret', 'name' => 'Secret guide', 'profile' => 'phased']);

    Livewire::test(ListGuides::class)
        ->assertOk()
        ->assertDontSee('Secret guide');
})->group('integration');

it('applies the host SQL scope hook and skips the PHP policy filter', function (): void {
    // A restrictive policy would hide 'open' too, but the SQL hook takes over and
    // decides visibility purely in the query — proving the PHP filter is bypassed.
    Gate::policy(Guide::class, PartialViewGuidePolicy::class);

    $plugin = Filament::getCurrentPanel()?->getPlugin(DecisionSupportPlugin::ID);
    expect($plugin)->toBeInstanceOf(DecisionSupportPlugin::class);

    /** @var DecisionSupportPlugin $plugin */
    $plugin->scopeGuideListUsing(
        static fn (Builder $query, Authenticatable $user): Builder => $query->where('key', 'open'),
    );

    Guide::create(['key' => 'open', 'name' => 'Open guide', 'profile' => 'phased']);
    Guide::create(['key' => 'secret', 'name' => 'Secret guide', 'profile' => 'phased']);

    Livewire::test(ListGuides::class)
        ->assertSee('Open guide')
        ->assertDontSee('Secret guide');
})->group('integration');

it('shows every guide when scoping is disabled despite a policy', function (): void {
    config(['decision-support-filament.list.scope_to_viewable' => false]);
    Gate::policy(Guide::class, PartialViewGuidePolicy::class);
    Guide::create(['key' => 'open', 'name' => 'Open guide', 'profile' => 'phased']);
    Guide::create(['key' => 'secret', 'name' => 'Secret guide', 'profile' => 'phased']);

    Livewire::test(ListGuides::class)
        ->assertSee('Open guide')
        ->assertSee('Secret guide');
})->group('integration');
