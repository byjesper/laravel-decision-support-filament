<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Enums\VersionStatus;
use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupportFilament\Pages\GuideTreeEditor;
use ByJesper\DecisionSupportFilament\Tests\Fixtures\DenyGuidePolicy;
use ByJesper\DecisionSupportFilament\Tests\Fixtures\EditorGuidePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function draftForEditor(): int
{
    $guide = Guide::create(['key' => 'g', 'name' => 'G', 'profile' => 'freeform']);

    return (int) $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Draft])->id;
}

it('opens the editor for a user the Guide policy permits to update', function (): void {
    Gate::policy(Guide::class, EditorGuidePolicy::class);

    Livewire::test(GuideTreeEditor::class, ['version' => draftForEditor()])
        ->assertOk();
})->group('integration');

it('403s the editor for a user the Guide policy denies', function (): void {
    $this->withoutExceptionHandling();
    Gate::policy(Guide::class, DenyGuidePolicy::class);

    Livewire::test(GuideTreeEditor::class, ['version' => draftForEditor()]);
})->throws(HttpException::class)->group('integration');

it('stays permissive when no Guide policy is registered', function (): void {
    Livewire::test(GuideTreeEditor::class, ['version' => draftForEditor()])
        ->assertOk();
})->group('integration');
