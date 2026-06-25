<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament\Resources;

use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupport\Registry\GuideProfileRegistry;
use ByJesper\DecisionSupportFilament\Pages\GuideRunner;
use ByJesper\DecisionSupportFilament\Pages\GuideTreeEditor;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages\CreateGuide;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages\EditGuide;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages\ListGuides;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\RelationManagers\VersionsRelationManager;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

use function Filament\Support\original_request;

/**
 * Guide CRUD. The graph itself is authored on the {@see GuideTreeEditor}
 * reached per version through the {@see VersionsRelationManager}; this resource
 * owns the guide's identity (key, name, profile). Authorization is deferred to
 * the host's Guide policy — permissive until one is registered.
 *
 * Not `final`: hosts may subclass to restyle the form, relayout the pages, or
 * swap the create flow without forking.
 */
class GuideResource extends Resource
{
    protected static ?string $model = Guide::class;

    protected static ?string $recordTitleAttribute = 'name';

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::formSchema());
    }

    /**
     * The guide-identity form, wrapped in a native Section so it looks like the
     * rest of a Filament panel. Shared by the full-page form and the modal/
     * slideover create action, so there is a single definition.
     *
     * @return array<int, Component>
     */
    public static function formSchema(): array
    {
        return [
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('key')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        // The key is the stable identifier a host fact provider binds to, so
                        // it is set once at creation and locked afterwards. Disabled fields are
                        // not dehydrated, so the stored value is preserved on edit.
                        ->disabled(fn (?Guide $record): bool => $record !== null)
                        ->helperText('Stable identifier a host fact provider is registered against. Set at creation; cannot be changed afterwards.'),
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
                        // The profile is a publish-time shape constraint that the whole tree is
                        // authored against; changing it later could invalidate that tree, so —
                        // like the key — it is chosen at creation and locked on edit.
                        ->disabled(fn (?Guide $record): bool => $record !== null)
                        ->helperText('Publish-time shape constraint enforced by the engine. Set at creation; cannot be changed afterwards.')
                        ->columnSpanFull(),
                ]),
            Section::make('Metadata')
                ->description('Consumer-defined metadata stored on the guide. Read by your Guide policy — the engine enforces nothing.')
                ->schema([
                    static::permissionsField()
                        ->helperText('Permissions a user needs to see/run this guide. The guide-level copy is authoritative for gating; edits take effect immediately. Publishing a version overwrites it from that version.'),
                ]),
        ];
    }

    /**
     * Field for the consumer-defined required permissions, stored at
     * `extra_attributes.permissions`. A configured options list yields a
     * constrained multi-select; otherwise a free-form tags input. Reused by the
     * guide form and the per-version "Edit metadata" action.
     */
    public static function permissionsField(string $statePath = 'extra_attributes.permissions'): Field
    {
        $options = config('decision-support-filament.permissions.options');

        if (is_array($options) && $options !== []) {
            return Select::make($statePath)
                ->label('Required permissions')
                ->multiple()
                ->options(self::normalizePermissionOptions($options));
        }

        return TagsInput::make($statePath)
            ->label('Required permissions');
    }

    /**
     * @param  array<array-key, mixed>  $options
     * @return array<string, string>
     */
    private static function normalizePermissionOptions(array $options): array
    {
        $normalized = [];

        foreach ($options as $key => $value) {
            if (is_int($key) && is_string($value)) {
                $normalized[$value] = $value;       // list form: ['view-guide', 'run-guide']
            } elseif (is_string($key) && is_string($value)) {
                $normalized[$key] = $value;         // assoc form: ['view-guide' => 'View guide']
            }
        }

        return $normalized;
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
                Action::make('run')
                    ->label('Run')
                    ->icon('heroicon-o-play')
                    // Run the guide's currently-active published version. A guide with only
                    // drafts has no active version, so the action is disabled with a hint.
                    ->disabled(fn (Guide $record): bool => $record->active_version_id === null)
                    ->tooltip(fn (Guide $record): ?string => $record->active_version_id === null
                        ? 'Publish a version to run this guide.'
                        : null)
                    ->url(fn (Guide $record): ?string => $record->active_version_id === null
                        ? null
                        : GuideRunner::getUrl(['version' => $record->active_version_id])),
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
        // Editing always stays a full page because that page hosts the versions relation
        // manager. Only the create flow switches to a modal/slideover, which Filament does
        // automatically once there is no standalone create page.
        $pages = [
            'index' => ListGuides::route('/'),
            'edit' => EditGuide::route('/{record}/edit'),
        ];

        if (! static::createsInModal()) {
            $pages['create'] = CreateGuide::route('/create');
        }

        return $pages;
    }

    /**
     * Configured create-flow layout: 'page' (default), 'modal', or 'slideover'.
     * Unknown values fall back to 'page'.
     */
    public static function formsLayout(): string
    {
        $layout = config('decision-support-filament.forms.layout');

        return in_array($layout, ['page', 'modal', 'slideover'], true) ? $layout : 'page';
    }

    public static function createsInModal(): bool
    {
        return static::formsLayout() !== 'page';
    }

    public static function createUsesSlideOver(): bool
    {
        return static::formsLayout() === 'slideover';
    }

    #[\Override]
    public static function getNavigationLabel(): string
    {
        $label = self::translatedConfig('decision-support-filament.navigation.label');

        return $label ?? parent::getNavigationLabel();
    }

    #[\Override]
    public static function getModelLabel(): string
    {
        $label = self::translatedConfig('decision-support-filament.labels.model');

        return $label ?? parent::getModelLabel();
    }

    #[\Override]
    public static function getPluralModelLabel(): string
    {
        $label = self::translatedConfig('decision-support-filament.labels.plural');

        return $label ?? parent::getPluralModelLabel();
    }

    /**
     * Read a config string and run it through the translator so hosts can supply
     * either a literal label or a translation key. Returns null when unset.
     */
    private static function translatedConfig(string $key): ?string
    {
        $value = config($key);

        if (! is_string($value) || $value === '') {
            return null;
        }

        $translated = __($value);

        return is_string($translated) ? $translated : $value;
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

    /**
     * Keep the resource's navigation item active on its standalone pages too — the
     * tree editor and the version-keyed runner — which otherwise leave nothing in
     * the sidebar highlighted.
     *
     * @return array<NavigationItem>
     */
    #[\Override]
    public static function getNavigationItems(): array
    {
        $activePattern = static::getNavigationItemActiveRoutePattern();

        return array_map(
            static fn (NavigationItem $item): NavigationItem => $item->isActiveWhen(
                static fn (): bool => original_request()->routeIs(
                    $activePattern,
                    GuideTreeEditor::getRouteName(),
                    GuideRunner::getRouteName(),
                ),
            ),
            parent::getNavigationItems(),
        );
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
