<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages;

use ByJesper\DecisionSupportFilament\Resources\GuideResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListGuides extends ListRecords
{
    protected static string $resource = GuideResource::class;

    /** @return array<int, Action> */
    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
