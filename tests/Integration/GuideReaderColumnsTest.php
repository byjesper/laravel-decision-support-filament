<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages\ListGuides;
use ByJesper\DecisionSupportFilament\Tests\Fixtures\EditorGuidePolicy;
use ByJesper\DecisionSupportFilament\Tests\Fixtures\PartialViewGuidePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('hides the configured columns from a reader', function (): void {
    config(['decision-support-filament.list.reader_hidden_columns' => ['profile', 'versions_count', 'active_version_id']]);
    // PartialViewGuidePolicy can view but not create → the user is a reader.
    Gate::policy(Guide::class, PartialViewGuidePolicy::class);
    Guide::create(['key' => 'onboarding', 'name' => 'Onboarding', 'profile' => 'freeform']);

    Livewire::test(ListGuides::class)
        ->assertSee('Onboarding')  // key, name and the action column remain
        ->assertSee('Name')
        ->assertDontSee('Profile') // the hidden column header is gone
        ->assertDontSee('freeform'); // and its value
})->group('integration');

it('keeps the full column set for an editor', function (): void {
    config(['decision-support-filament.list.reader_hidden_columns' => ['profile', 'versions_count', 'active_version_id']]);
    // EditorGuidePolicy can create → not a reader, so nothing is hidden.
    Gate::policy(Guide::class, EditorGuidePolicy::class);
    Guide::create(['key' => 'onboarding', 'name' => 'Onboarding', 'profile' => 'freeform']);

    Livewire::test(ListGuides::class)
        ->assertSee('Onboarding')
        ->assertSee('Profile')
        ->assertSee('freeform');
})->group('integration');

it('shows every column when no columns are configured to hide', function (): void {
    config(['decision-support-filament.list.reader_hidden_columns' => []]);
    Gate::policy(Guide::class, PartialViewGuidePolicy::class); // a reader
    Guide::create(['key' => 'onboarding', 'name' => 'Onboarding', 'profile' => 'freeform']);

    Livewire::test(ListGuides::class)
        ->assertSee('Profile')
        ->assertSee('freeform');
})->group('integration');

it('ignores reader columns when no policy makes anyone a reader', function (): void {
    config(['decision-support-filament.list.reader_hidden_columns' => ['profile']]);
    // No Guide policy → canCreate is permissive → nobody is a reader.
    Guide::create(['key' => 'onboarding', 'name' => 'Onboarding', 'profile' => 'freeform']);

    Livewire::test(ListGuides::class)
        ->assertSee('Profile')
        ->assertSee('freeform');
})->group('integration');
