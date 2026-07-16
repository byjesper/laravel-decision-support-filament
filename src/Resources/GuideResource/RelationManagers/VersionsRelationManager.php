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
use ByJesper\DecisionSupportFilament\Support\Lang;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

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
                TextColumn::make('number')->label(Lang::get('versions.column.version'))->sortable(),
                TextColumn::make('status')->label(Lang::get('versions.column.status'))->badge(),
                TextColumn::make('published_at')->label(Lang::get('versions.column.published_at'))->dateTime()->placeholder('—'),
            ])
            ->defaultSort('number', 'desc')
            ->headerActions([
                Action::make('createDraft')
                    ->label(Lang::get('versions.action.new_draft'))
                    ->icon('heroicon-o-plus')
                    ->action(fn (): GuideVersion => $this->createDraft()),
            ])
            ->recordActions([
                Action::make('editTree')
                    ->label(Lang::get('versions.action.edit_tree'))
                    ->icon('heroicon-o-share')
                    ->url(fn (GuideVersion $record): string => GuideTreeEditor::getUrl(['version' => $record->getKey()])),
                Action::make('run')
                    ->label(Lang::get('versions.action.start'))
                    ->icon('heroicon-o-play')
                    ->url(fn (GuideVersion $record): string => GuideRunner::getUrl(['version' => $record->getKey()])),
                Action::make('duplicate')
                    ->label(Lang::get('versions.action.duplicate'))
                    ->icon('heroicon-o-document-duplicate')
                    // Clone any version (draft or published) into a fresh editable draft.
                    ->action(fn (GuideVersion $record): GuideVersion => $this->duplicate($record)),
                Action::make('editMetadata')
                    ->label(Lang::get('versions.action.edit_metadata'))
                    ->icon('heroicon-o-key')
                    // The version copy is the editable working copy; once published the guide
                    // copy is authoritative, so only drafts are edited here.
                    ->visible(fn (GuideVersion $record): bool => $record->status === VersionStatus::Draft)
                    ->fillForm(fn (GuideVersion $record): array => [
                        'extra_attributes' => $record->extra_attributes ?? [],
                    ])
                    ->schema([
                        GuideResource::permissionsField(),
                        GuideResource::permissionsModeField(),
                    ])
                    ->action(function (GuideVersion $record, array $data): void {
                        $extra = is_array($data['extra_attributes'] ?? null) ? $data['extra_attributes'] : [];
                        $record->update(['extra_attributes' => $extra]);

                        Notification::make()
                            ->title(Lang::get('versions.notification.metadata_updated', ['number' => $record->number]))
                            ->success()
                            ->send();
                    }),
                Action::make('publish')
                    ->label(Lang::get('versions.action.publish'))
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
            ->title(Lang::get('versions.notification.draft_created', ['number' => $next]))
            ->success()
            ->send();

        return $version;
    }

    /**
     * Clone an existing version's graph (nodes, edges) and metadata into a new
     * auto-numbered draft, so an author can iterate on a published version without
     * editing the frozen snapshot.
     */
    private function duplicate(GuideVersion $source): GuideVersion
    {
        /** @var Guide $guide */
        $guide = $this->getOwnerRecord();

        $next = (int) $guide->versions()->max('number') + 1;

        $version = DB::transaction(function () use ($guide, $source, $next): GuideVersion {
            $source->loadMissing(['nodes', 'edges']);

            $draft = $guide->versions()->create([
                'number' => $next,
                'status' => VersionStatus::Draft,
                'extra_attributes' => $source->extra_attributes ?? [],
            ]);

            /** @var array<int, int> $idMap */
            $idMap = [];
            foreach ($source->nodes as $node) {
                $idMap[$node->id] = $draft->nodes()->create([
                    'type' => $node->type,
                    'key' => $node->key,
                    'label' => $node->label,
                    'config' => $node->config,
                    'position' => $node->position,
                ])->id;
            }

            foreach ($source->edges as $edge) {
                $draft->edges()->create([
                    'from_node_id' => $idMap[$edge->from_node_id] ?? null,
                    'to_node_id' => $idMap[$edge->to_node_id] ?? null,
                    'from_port' => $edge->from_port,
                    'label' => $edge->label,
                    'label_i18n' => $edge->label_i18n,
                    'condition' => $edge->condition,
                ]);
            }

            return $draft;
        });

        Notification::make()
            ->title(Lang::get('versions.notification.duplicated', ['number' => $next, 'source' => $source->number]))
            ->success()
            ->send();

        return $version;
    }

    private function publish(GuideVersion $version): void
    {
        $result = app(GuidePublisher::class)->publish($version);

        if ($result->fails()) {
            Notification::make()
                ->title(Lang::get('versions.notification.publish_failed'))
                ->body($this->errorSummary($result->errors))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title(Lang::get('versions.notification.published', ['number' => $version->number]))
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
