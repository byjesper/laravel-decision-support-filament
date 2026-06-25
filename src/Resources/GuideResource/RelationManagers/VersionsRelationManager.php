<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament\Resources\GuideResource\RelationManagers;

use ByJesper\DecisionSupport\Enums\VersionStatus;
use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupport\Models\GuideVersion;
use ByJesper\DecisionSupport\Publishing\GuidePublisher;
use ByJesper\DecisionSupport\Validation\ValidationError;
use ByJesper\DecisionSupportFilament\Pages\GuideRunner;
use ByJesper\DecisionSupportFilament\Pages\GuideTreeEditor;
use ByJesper\DecisionSupportFilament\Resources\GuideResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Lists a guide's versions and routes each to the tree editor or runner. New
 * versions are created as auto-numbered drafts; publishing runs the engine's
 * {@see GuidePublisher} and surfaces any validation failures inline rather than
 * letting an invalid graph reach the snapshot.
 */
final class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $recordTitleAttribute = 'number';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->columns([
                TextColumn::make('number')->label('Version')->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('published_at')->dateTime()->placeholder('—'),
            ])
            ->defaultSort('number', 'desc')
            ->headerActions([
                Action::make('createDraft')
                    ->label('New draft')
                    ->icon('heroicon-o-plus')
                    ->action(fn (): GuideVersion => $this->createDraft()),
            ])
            ->recordActions([
                Action::make('editTree')
                    ->label('Edit tree')
                    ->icon('heroicon-o-share')
                    ->url(fn (GuideVersion $record): string => GuideTreeEditor::getUrl(['version' => $record->getKey()])),
                Action::make('run')
                    ->label('Run')
                    ->icon('heroicon-o-play')
                    ->url(fn (GuideVersion $record): string => GuideRunner::getUrl(['version' => $record->getKey()])),
                Action::make('editMetadata')
                    ->label('Edit metadata')
                    ->icon('heroicon-o-key')
                    // The version copy is the editable working copy; once published the guide
                    // copy is authoritative, so only drafts are edited here.
                    ->visible(fn (GuideVersion $record): bool => $record->status === VersionStatus::Draft)
                    ->fillForm(fn (GuideVersion $record): array => [
                        'extra_attributes' => $record->extra_attributes ?? [],
                    ])
                    ->schema([
                        GuideResource::permissionsField(),
                    ])
                    ->action(function (GuideVersion $record, array $data): void {
                        $extra = is_array($data['extra_attributes'] ?? null) ? $data['extra_attributes'] : [];
                        $record->update(['extra_attributes' => $extra]);

                        Notification::make()
                            ->title("Version {$record->number} metadata updated")
                            ->success()
                            ->send();
                    }),
                Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-rocket-launch')
                    ->requiresConfirmation()
                    ->visible(fn (GuideVersion $record): bool => $record->status === VersionStatus::Draft)
                    ->action(function (GuideVersion $record): void {
                        $this->publish($record);
                    }),
            ]);
    }

    private function createDraft(): GuideVersion
    {
        /** @var Guide $guide */
        $guide = $this->getOwnerRecord();

        $next = (int) $guide->versions()->max('number') + 1;

        $version = $guide->versions()->create([
            'number' => $next,
            'status' => VersionStatus::Draft,
            // Inherit the guide's current attributes as editable defaults for this draft.
            'extra_attributes' => $guide->extra_attributes ?? [],
        ]);

        Notification::make()
            ->title("Draft version {$next} created")
            ->success()
            ->send();

        return $version;
    }

    private function publish(GuideVersion $version): void
    {
        $result = app(GuidePublisher::class)->publish($version);

        if ($result->fails()) {
            Notification::make()
                ->title('Publishing failed')
                ->body($this->errorSummary($result->errors))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title("Version {$version->number} published")
            ->success()
            ->send();
    }

    /** @param list<ValidationError> $errors */
    private function errorSummary(array $errors): string
    {
        return implode("\n", array_map(
            static fn (ValidationError $error): string => '• '.$error->message,
            $errors,
        ));
    }
}
