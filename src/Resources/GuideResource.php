<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament\Resources;

use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupport\Registry\GuideProfileRegistry;
use ByJesper\DecisionSupportFilament\Pages\GuideTreeEditor;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages\CreateGuide;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages\EditGuide;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages\ListGuides;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\RelationManagers\VersionsRelationManager;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Guide CRUD. The graph itself is authored on the {@see GuideTreeEditor}
 * reached per version through the {@see VersionsRelationManager}; this resource
 * owns the guide's identity (key, name, profile). Authorization is deferred to
 * the host's Guide policy — permissive until one is registered.
 */
final class GuideResource extends Resource
{
    protected static ?string $model = Guide::class;

    protected static ?string $recordTitleAttribute = 'name';

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->helperText('Stable identifier a host fact provider is registered against. Cannot change after publishing.'),
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->rows(3)
                ->columnSpanFull(),
            Select::make('profile')
                ->options(self::profileOptions())
                ->default('phased')
                ->required()
                ->helperText('Publish-time shape constraint enforced by the engine.'),
        ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('profile')->badge(),
                TextColumn::make('versions_count')->counts('versions')->label('Versions'),
                TextColumn::make('active_version_id')->label('Active version')->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /** @return array<int, class-string> */
    #[\Override]
    public static function getRelations(): array
    {
        return [
            VersionsRelationManager::class,
        ];
    }

    /** @return array<string, PageRegistration> */
    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ListGuides::route('/'),
            'create' => CreateGuide::route('/create'),
            'edit' => EditGuide::route('/{record}/edit'),
        ];
    }

    #[\Override]
    public static function getNavigationGroup(): ?string
    {
        $group = config('decision-support-filament.navigation.group');

        return is_string($group) ? $group : null;
    }

    #[\Override]
    public static function getNavigationIcon(): ?string
    {
        $icon = config('decision-support-filament.navigation.icon');

        return is_string($icon) ? $icon : null;
    }

    #[\Override]
    public static function getNavigationSort(): ?int
    {
        $sort = config('decision-support-filament.navigation.sort');

        return is_int($sort) ? $sort : null;
    }

    /** @return array<string, string> */
    private static function profileOptions(): array
    {
        $options = [];

        foreach (array_keys(app(GuideProfileRegistry::class)->all()) as $key) {
            $options[$key] = Str::headline($key);
        }

        return $options === [] ? ['phased' => 'Phased', 'freeform' => 'Freeform'] : $options;
    }
}
