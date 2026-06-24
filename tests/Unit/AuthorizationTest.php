<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupportFilament\Resources\GuideResource;
use ByJesper\DecisionSupportFilament\Tests\Fixtures\DenyGuidePolicy;
use Illuminate\Support\Facades\Gate;

it('is permissive until the host registers a Guide policy', function (): void {
    expect(GuideResource::canViewAny())->toBeTrue();
});

it('defers authorization to a host-registered Guide policy', function (): void {
    Gate::policy(Guide::class, DenyGuidePolicy::class);

    expect(GuideResource::canViewAny())->toBeFalse();
});
