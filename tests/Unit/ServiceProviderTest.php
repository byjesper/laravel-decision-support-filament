<?php

declare(strict_types=1);

use ByJesper\DecisionSupportFilament\DecisionSupportPlugin;
use ByJesper\DecisionSupportFilament\Pages\GuideRunner;
use ByJesper\DecisionSupportFilament\Pages\GuideTreeEditor;
use ByJesper\DecisionSupportFilament\Resources\GuideResource;
use Filament\Facades\Filament;

it('merges the package configuration', function (): void {
    expect(config('decision-support-filament.navigation.group'))->toBe('Decision Support');
});

it('exposes a stable plugin id', function (): void {
    expect(DecisionSupportPlugin::make()->getId())->toBe('decision-support');
});

it('registers the resource and pages on the host panel', function (): void {
    $panel = Filament::getPanel('admin');

    expect($panel->getResources())->toContain(GuideResource::class)
        ->and($panel->getPages())->toContain(GuideTreeEditor::class)
        ->and($panel->getPages())->toContain(GuideRunner::class);
});
