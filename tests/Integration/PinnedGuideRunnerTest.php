<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupport\Publishing\GuidePublisher;
use ByJesper\DecisionSupportFilament\Pages\GuideRunner;
use ByJesper\DecisionSupportFilament\Tests\Fixtures\AllowGuidePolicy;
use ByJesper\DecisionSupportFilament\Tests\Fixtures\DenyGuidePolicy;
use ByJesper\DecisionSupportFilament\Tests\Fixtures\PinnedGuideRunner;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

it('drops the version route parameter when pinned to a guide', function (): void {
    $panel = Filament::getPanel('admin');

    expect(PinnedGuideRunner::getRoutePath($panel))->toBe('/'.PinnedGuideRunner::getSlug($panel))
        ->and(GuideRunner::getRoutePath($panel))->toBe('/'.GuideRunner::getSlug($panel).'/{version}');
})->group('integration');

it('resolves the active published version without a route parameter', function (): void {
    Gate::policy(Guide::class, AllowGuidePolicy::class);

    $version = seedBooleanGuide();
    app(GuidePublisher::class)->publish($version);

    Livewire::test(PinnedGuideRunner::class)
        ->assertOk()
        ->assertSet('version', $version->id)
        ->call('start')
        ->assertSee('Are you employed?')
        ->call('submit', 'true')
        ->assertSee('Eligible');
})->group('integration');

it('404s when the pinned guide has no published version', function (): void {
    $this->withoutExceptionHandling();
    Gate::policy(Guide::class, AllowGuidePolicy::class);

    seedBooleanGuide(); // draft only — never published, so active_version_id is null.

    Livewire::test(PinnedGuideRunner::class);
})->throws(NotFoundHttpException::class)->group('integration');

it('keeps the version-keyed runner permissive', function (): void {
    expect(GuideRunner::canAccess())->toBeTrue();
})->group('integration');

it('defers a pinned runner to the host Guide policy', function (): void {
    seedBooleanGuide(wireProvider: false);

    expect(PinnedGuideRunner::canAccess())->toBeFalse();          // no policy → deny by default

    Gate::policy(Guide::class, AllowGuidePolicy::class);
    expect(PinnedGuideRunner::canAccess())->toBeTrue();
})->group('integration');

it('denies a pinned runner when the host policy denies view', function (): void {
    seedBooleanGuide(wireProvider: false);
    Gate::policy(Guide::class, DenyGuidePolicy::class);

    expect(PinnedGuideRunner::canAccess())->toBeFalse();
})->group('integration');

it('denies a pinned runner when its guide is absent', function (): void {
    Gate::policy(Guide::class, AllowGuidePolicy::class);

    expect(PinnedGuideRunner::canAccess())->toBeFalse();
})->group('integration');
