<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages;

use ByJesper\DecisionSupportFilament\Resources\GuideResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditGuide extends EditRecord
{
    protected static string $resource = GuideResource::class;

    /** @return array<int, Action> */
    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
