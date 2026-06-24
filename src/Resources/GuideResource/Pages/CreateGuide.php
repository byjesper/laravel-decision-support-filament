<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages;

use ByJesper\DecisionSupportFilament\Resources\GuideResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateGuide extends CreateRecord
{
    protected static string $resource = GuideResource::class;
}
