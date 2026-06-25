<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages;

use ByJesper\DecisionSupportFilament\Resources\GuideResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGuides extends ListRecords
{
    protected static string $resource = GuideResource::class;

    /** @return array<int, Action> */
    #[\Override]
    protected function getHeaderActions(): array
    {
        $create = CreateAction::make();

        // When the resource has no standalone create page (forms.layout = modal|slideover),
        // this action opens a modal automatically; the slideover variant just opts in.
        if (GuideResource::createUsesSlideOver()) {
            $create->slideOver();
        }

        return [
            $create,
        ];
    }
}
