<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages\CreateGuide;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages\ListGuides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('lists guides', function (): void {
    Guide::create(['key' => 'onboarding', 'name' => 'Onboarding', 'profile' => 'phased']);

    Livewire::test(ListGuides::class)
        ->assertOk()
        ->assertSee('Onboarding')
        ->assertSee('onboarding');
})->group('integration');

it('creates a guide through the resource form', function (): void {
    Livewire::test(CreateGuide::class)
        ->fillForm([
            'key' => 'leave-policy',
            'name' => 'Leave Policy',
            'profile' => 'phased',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('guides', ['key' => 'leave-policy', 'name' => 'Leave Policy']);
})->group('integration');
