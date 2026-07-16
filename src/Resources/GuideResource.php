<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament\Resources;

use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupport\Models\GuideVersion;
use ByJesper\DecisionSupport\Registry\GuideProfileRegistry;
use ByJesper\DecisionSupportFilament\DecisionSupportPlugin;
use ByJesper\DecisionSupportFilament\Pages\GuideRunner;
use ByJesper\DecisionSupportFilament\Pages\GuideTreeEditor;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages\CreateGuide;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages\EditGuide;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\Pages\ListGuides;
use ByJesper\DecisionSupportFilament\Resources\GuideResource\RelationManagers\VersionsRelationManager;
use ByJesper\DecisionSupportFilament\Support\Lang;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
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
                        ->label(Lang::get('resource.field.key'))
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        // The key is the stable identifier a host fact provider binds to, so
                        // it is set once at creation and locked afterwards. Disabled fields are
                        // not dehydrated, so the stored value is preserved on edit.
                        ->disabled(fn (?Guide $record): bool => $record !== null)
                        ->helperText(Lang::get('resource.field.key_help')),
                    TextInput::make('name')
                        ->label(Lang::get('resource.field.name'))
                        ->required()
                        ->maxLength(255),
                    Textarea::make('description')
                        ->label(Lang::get('resource.field.description'))
                        ->rows(3)
                        ->columnSpanFull(),
                    Select::make('profile')
                        ->label(Lang::get('resource.field.profile'))
                        ->options(self::profileOptions())
                        ->default('phased')
                        ->required()
                        // The profile is a publish-time shape constraint that the whole tree is
                        // authored against; changing it later could invalidate that tree, so —
                        // like the key — it is chosen at creation and locked on edit.
                        ->disabled(fn (?Guide $record): bool => $record !== null)
                        ->helperText(Lang::get('resource.field.profile_help'))
                        ->columnSpanFull(),
                ]),
            Section::make(Lang::get('resource.section.metadata'))
                ->description(Lang::get('resource.section.metadata_description'))
                ->schema([
                    static::permissionsField(helperText: Lang::get('resource.field.permissions_help')),
                    static::permissionsModeField(),
                ]),
        ];
    }

    /**
     * Field for the consumer-defined required permissions, stored at
     * `extra_attributes.permissions`. A configured catalog yields a standard
     * multi-select (chosen permissions show as removable tags, the dropdown lists
     * the catalog and is searchable). With no catalog, permissions can't be
     * meaningfully chosen, so the field is replaced by an info callout. Reused by
     * the guide form and the per-version "Edit metadata" action.
     */
    public static function permissionsField(string $statePath = 'extra_attributes.permissions', ?string $helperText = null): Component
    {
        if (self::hasPermissionCatalog()) {
            $options = config('decision-support-filament.permissions.options');

            $field = Select::make($statePath)
                ->label(Lang::get('resource.field.permissions'))
                ->multiple()
                ->searchable()
                ->options(fn (mixed $record, mixed $livewire): array => self::resolvePermissionOptions(
                    $options,
                    self::guideFromContext($record, $livewire),
                ));

            return $helperText === null ? $field : $field->helperText($helperText);
        }

        // No catalog. Always show a warning callout explaining nothing can be added.
        // If the guide already carries permissions, an author must still be able to
        // remove them, so render a multi-select whose options are exactly the stored
        // values (removable, nothing new can be added) — shown only when some exist.
        return Group::make([
            Callout::make(Lang::get('resource.field.permissions'))
                ->description(Lang::get('resource.field.permissions_unavailable'))
                ->warning(),
            Select::make($statePath)
                ->label(Lang::get('resource.field.permissions'))
                ->helperText(Lang::get('resource.field.permissions_no_catalog_help'))
                ->multiple()
                ->options(fn (Get $get): array => self::storedPermissionOptions($get($statePath)))
                ->visible(fn (Get $get): bool => filled($get($statePath))),
        ]);
    }

    /**
     * Map a stored permissions value (a list of keys) to a value => label option
     * map, so a no-catalog field can still display and remove them.
     *
     * @return array<string, string>
     */
    private static function storedPermissionOptions(mixed $stored): array
    {
        return is_array($stored) ? self::normalizePermissionOptions($stored) : [];
    }

    /**
     * Whether a permission catalog is configured — a non-empty array (one catalog
     * for every guide) or a closure `fn (?Guide $guide): array` (resolved per
     * guide). Without one, the permissions UI is just an info callout.
     */
    public static function hasPermissionCatalog(): bool
    {
        $options = config('decision-support-filament.permissions.options');

        return $options instanceof Closure || (is_array($options) && $options !== []);
    }

    /**
     * Companion to {@see permissionsField()}: how the required permissions
     * combine when access is checked — 'any' (OR — hold any one) or 'all'
     * (AND — hold every one). Stored at `extra_attributes.permissions_mode`,
     * defaulting to the configured `permissions.mode`. The engine enforces
     * nothing; a host policy reads this alongside the permissions to gate access.
     */
    public static function permissionsModeField(string $statePath = 'extra_attributes.permissions_mode'): Field
    {
        return Radio::make($statePath)
            ->label(Lang::get('resource.field.permissions_mode'))
            ->options([
                'any' => Lang::get('resource.field.permissions_mode_any'),
                'all' => Lang::get('resource.field.permissions_mode_all'),
            ])
            ->default(self::defaultPermissionsMode())
            // Pre-select the configured default even on an edit form (where component
            // defaults don't apply), so a guide with no stored mode still shows one
            // selected rather than an empty radio.
            ->formatStateUsing(fn (mixed $state): string => is_string($state) && $state !== '' ? $state : self::defaultPermissionsMode())
            // The match mode only matters when there are permissions to combine —
            // shown (and stored) when a catalog is configured, or when the guide
            // already carries permissions (so a stored mode is never silently lost).
            ->visible(fn (Get $get): bool => self::modeApplies($get))
            ->dehydrated(fn (Get $get): bool => self::modeApplies($get))
            ->helperText(Lang::get('resource.field.permissions_mode_help'));
    }

    /** Whether the permission-match mode applies: a catalog exists, or the guide already has permissions. */
    private static function modeApplies(Get $get): bool
    {
        return self::hasPermissionCatalog() || filled($get('extra_attributes.permissions'));
    }

    /** Configured default permission-match mode, falling back to 'any' (OR). */
    public static function defaultPermissionsMode(): string
    {
        $mode = config('decision-support-filament.permissions.mode');

        return in_array($mode, ['all', 'any'], true) ? $mode : 'any';
    }

    /**
     * Resolve the permission catalog for a guide. `permissions.options` may be a
     * fixed array (one catalog for every guide) or a closure
     * `fn (?Guide $guide): array` evaluated per guide; either way the result is
     * normalized to a value => label map. A null/non-array result yields none.
     *
     * @return array<string, string>
     */
    public static function resolvePermissionOptions(mixed $options, ?Guide $guide): array
    {
        if ($options instanceof Closure) {
            $options = $options($guide);
        }

        return is_array($options) ? self::normalizePermissionOptions($options) : [];
    }

    /**
     * Best-effort resolve the Guide a permissions field is rendered for, across the
     * three contexts it appears in: the guide form ($record is the Guide), the
     * per-version "Edit metadata" action ($record is the GuideVersion), and the tree
     * editor (resolved off the Livewire component). Null while creating a guide.
     */
    private static function guideFromContext(mixed $record, mixed $livewire): ?Guide
    {
        if ($record instanceof Guide) {
            return $record;
        }

        if ($record instanceof GuideVersion) {
            return $record->guide;
        }

        if ($livewire instanceof GuideTreeEditor) {
            return $livewire->record()->guide;
        }

        if ($livewire instanceof VersionsRelationManager) {
            $owner = $livewire->getOwnerRecord();

            return $owner instanceof Guide ? $owner : null;
        }

        return null;
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
                TextColumn::make('key')->label(Lang::get('resource.column.key'))->searchable()->sortable()
                    ->visible(fn (): bool => ! static::columnHiddenFromReaders('key')),
                TextColumn::make('name')->label(Lang::get('resource.column.name'))->searchable()->sortable()
                    ->visible(fn (): bool => ! static::columnHiddenFromReaders('name')),
                TextColumn::make('profile')->label(Lang::get('resource.column.profile'))->badge()
                    ->visible(fn (): bool => ! static::columnHiddenFromReaders('profile')),
                TextColumn::make('versions_count')->counts('versions')->label(Lang::get('resource.column.versions'))
                    ->visible(fn (): bool => ! static::columnHiddenFromReaders('versions_count')),
                TextColumn::make('active_version_id')->label(Lang::get('resource.column.active_version'))->placeholder('—')
                    ->visible(fn (): bool => ! static::columnHiddenFromReaders('active_version_id')),
            ])
            ->recordActions([
                Action::make('run')
                    ->label(Lang::get('resource.action.start'))
                    ->icon('heroicon-o-play')
                    // Run the guide's currently-active published version. A guide with only
                    // drafts has no active version, so the action is disabled with a hint.
                    ->disabled(fn (Guide $record): bool => $record->active_version_id === null)
                    ->tooltip(fn (Guide $record): ?string => $record->active_version_id === null
                        ? Lang::get('resource.action.start_tooltip')
                        : null)
                    ->url(fn (Guide $record): ?string => $record->active_version_id === null
                        ? null
                        : GuideRunner::getUrl(['version' => $record->active_version_id])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * Constrain the list to guides the current user may `view()`, so the table
     * honours each guide's own required permissions rather than only the coarse
     * page-level `viewAny`. Deferred to the host's Guide policy and only applied
     * when one is registered — without a policy (or with scoping disabled) the
     * package stays permissive and shows everything, as before.
     */
    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! static::scopesListToViewable()) {
            return $query;
        }

        $user = Filament::auth()->user();

        if ($user === null || Gate::getPolicyFor(Guide::class) === null) {
            return $query;
        }

        // Preferred path: a host that can express visibility in SQL registers a
        // scope on the plugin, giving a single indexed query and skipping the PHP
        // filter entirely.
        $scope = static::guideListScope();

        if ($scope !== null) {
            return $scope($query, $user);
        }

        // Fallback: a guide's required permissions (extra_attributes.permissions,
        // combined any/all) aren't always SQL-expressible, so resolve the viewable
        // IDs in PHP — but cheaply: select only the columns a policy needs and
        // stream with a cursor instead of hydrating the whole table, memoized per
        // request so the paginator's count query doesn't recompute it.
        return $query->whereKey(static::viewableGuideIds($query, $user));
    }

    /**
     * The registered host SQL scope for the guide list, if any.
     *
     * @return (Closure(Builder<Model>, Authenticatable): Builder<Model>)|null
     */
    protected static function guideListScope(): ?Closure
    {
        $panel = Filament::getCurrentPanel();

        if ($panel === null || ! $panel->hasPlugin(DecisionSupportPlugin::ID)) {
            return null;
        }

        $plugin = $panel->getPlugin(DecisionSupportPlugin::ID);

        return $plugin instanceof DecisionSupportPlugin ? $plugin->guideListScope() : null;
    }

    /**
     * Guide ids the user may `view()`, computed with a lean projection (only the
     * columns a policy needs) and a cursor, so the catalogue is streamed rather
     * than hydrated whole.
     *
     * @param  Builder<Model>  $query
     * @return list<int>
     */
    protected static function viewableGuideIds(Builder $query, Authenticatable $user): array
    {
        /** @var list<int> $ids */
        $ids = [];

        $query->clone()
            ->select(['id', 'key', 'extra_attributes'])
            ->lazyById()
            ->each(static function (Model $guide) use ($user, &$ids): void {
                if (Gate::forUser($user)->allows('view', $guide)) {
                    $ids[] = (int) $guide->getKey();
                }
            });

        return $ids;
    }

    /**
     * Whether the list query is scoped to viewable guides. On by default; set
     * `list.scope_to_viewable` to false to opt out. Only has an effect when a
     * Guide policy is registered (see {@see getEloquentQuery()}).
     */
    public static function scopesListToViewable(): bool
    {
        return config('decision-support-filament.list.scope_to_viewable') !== false;
    }

    /**
     * Whether a table column is hidden from "readers". A column is dropped only
     * when it is listed in `list.reader_hidden_columns` *and* the current user is
     * a reader — so an empty list (the default) shows every column to everyone.
     */
    protected static function columnHiddenFromReaders(string $column): bool
    {
        return static::isReader() && in_array($column, static::readerHiddenColumns(), true);
    }

    /**
     * A "reader" is a user who may browse guides but not create them — per the
     * host Guide policy's `create` ability, which Filament's {@see canCreate()}
     * already resolves. With no policy registered authorization is permissive, so
     * no one is a reader and every column shows. Override to use another signal.
     */
    protected static function isReader(): bool
    {
        return ! static::canCreate();
    }

    /**
     * Columns hidden from readers, by column name
     * (`key`, `name`, `profile`, `versions_count`, `active_version_id`).
     *
     * @return list<string>
     */
    public static function readerHiddenColumns(): array
    {
        $columns = config('decision-support-filament.list.reader_hidden_columns');

        return is_array($columns) ? array_values(array_filter($columns, is_string(...))) : [];
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
        // Like the labels above, accept a literal or a translation key so the
        // navigation group localizes alongside the rest of the panel chrome.
        return self::translatedConfig('decision-support-filament.navigation.group');
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
