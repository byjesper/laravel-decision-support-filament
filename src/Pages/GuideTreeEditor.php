<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament\Pages;

use ByJesper\DecisionSupport\Conditions\Condition;
use ByJesper\DecisionSupport\Definition\EdgeDefinition;
use ByJesper\DecisionSupport\Definition\GuideDefinition;
use ByJesper\DecisionSupport\Definition\NodeDefinition;
use ByJesper\DecisionSupport\Enums\Operator;
use ByJesper\DecisionSupport\Enums\VersionStatus;
use ByJesper\DecisionSupport\Mermaid\MermaidRenderer;
use ByJesper\DecisionSupport\Models\GuideEdge;
use ByJesper\DecisionSupport\Models\GuideNode;
use ByJesper\DecisionSupport\Models\GuideVersion;
use ByJesper\DecisionSupport\NodeTypes\DecisionNode;
use ByJesper\DecisionSupport\NodeTypes\FactNode;
use ByJesper\DecisionSupport\NodeTypes\OutcomeNode;
use ByJesper\DecisionSupport\NodeTypes\QuestionNode;
use ByJesper\DecisionSupport\Publishing\GuidePublisher;
use ByJesper\DecisionSupport\Registry\FactProviderRegistry;
use ByJesper\DecisionSupport\Registry\GuideProfileRegistry;
use ByJesper\DecisionSupport\Registry\NodeTypeRegistry;
use ByJesper\DecisionSupport\Validation\PublishValidator;
use ByJesper\DecisionSupport\Validation\ValidationError;
use ByJesper\DecisionSupportFilament\Resources\GuideResource;
use ByJesper\DecisionSupportFilament\Support\Lang;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The non-developer authoring surface for a single draft version. Nodes and
 * edges are edited as native, collapsible Filament repeaters — add, reorder,
 * edit in place, and delete — with each node's config form driven by its type.
 * Saving replaces the draft's rows in one transaction; **Publish** runs the
 * engine's publish pipeline (after saving), surfacing any validation failures
 * inline rather than freezing an invalid graph. The Mermaid preview reflects the
 * last saved state.
 *
 * @property-read Schema $form
 * @property-read string $mermaidSource
 */
class GuideTreeEditor extends Page
{
    /** @var list<string> */
    private const array INPUT_TYPES = ['boolean', 'select', 'date', 'text', 'number'];

    /** @var array<string, string> */
    private const array CONDITION_TYPES = [
        'always' => 'always (default)',
        'structured' => 'structured (fact / operator / value)',
        'expression' => 'expression',
        'unknown' => 'fact unknown',
    ];

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'decision-support-filament::pages.guide-tree-editor';

    public int $version;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var list<string> */
    public array $publishErrors = [];

    /** Per-request cache so a single round-trip resolves the version once, not per accessor. */
    private ?GuideVersion $recordCache = null;

    #[\Override]
    public static function getRoutePath(Panel $panel): string
    {
        return '/'.self::getSlug($panel).'/{version}';
    }

    public function mount(int $version): void
    {
        $this->version = $version;
        $record = $this->record();

        $this->form->fill([
            'nodes' => $this->nodesToState($record),
            'edges' => $this->edgesToState($record),
            'extra_attributes' => $record->extra_attributes ?? [],
        ]);
    }

    #[\Override]
    public function getTitle(): string
    {
        $record = $this->record();

        return Lang::get('editor.title', [
            'guide' => $record->guide->name,
            'version' => $record->number,
        ]);
    }

    /** @return array<int|string, string> */
    #[\Override]
    public function getBreadcrumbs(): array
    {
        $record = $this->record();

        return [
            GuideResource::getUrl() => Str::ucfirst(GuideResource::getPluralModelLabel()),
            GuideResource::getUrl('edit', ['record' => $record->guide]) => $record->guide->name,
            Lang::get('editor.breadcrumb', ['version' => $record->number]),
        ];
    }

    /** Nodes and edges are frozen once a version is published; only metadata stays editable. */
    public function isDraft(): bool
    {
        return $this->record()->status === VersionStatus::Draft;
    }

    public function record(): GuideVersion
    {
        return $this->recordCache ??= GuideVersion::query()->with(['guide', 'nodes', 'edges'])->findOrFail($this->version);
    }

    public function form(Schema $schema): Schema
    {
        // Once published, the graph is a frozen snapshot: nodes and edges are
        // read-only. Metadata stays editable so permissions can change after
        // publishing without cutting a new version.
        $frozen = ! $this->isDraft();

        return $schema
            ->statePath('data')
            ->components([
                Section::make(Lang::get('editor.section.nodes'))
                    ->description(Lang::get('editor.section.nodes_description'))
                    ->schema([
                        ...$this->readOnlyCallout($frozen),
                        // Structure locked, content editable when published.
                        $this->nodesRepeater($frozen),
                    ]),
                Section::make(Lang::get('editor.section.edges'))
                    ->description(Lang::get('editor.section.edges_description'))
                    ->schema([
                        ...$this->readOnlyCallout($frozen),
                        // Edges are pure structure — fully locked when published.
                        $this->edgesRepeater()->disabled($frozen),
                    ]),
                Section::make(Lang::get('editor.section.metadata'))
                    ->description(Lang::get('editor.section.metadata_description'))
                    ->collapsed()
                    ->schema([
                        GuideResource::permissionsField()
                            ->helperText(Lang::get('editor.field.permissions_help')),
                    ]),
            ]);
    }

    /** @return array<int, Action> */
    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(Lang::get($this->isDraft() ? 'editor.action.save' : 'editor.action.save_published'))
                ->icon('heroicon-o-check')
                ->action(fn () => $this->save()),
            Action::make('run')
                ->label(Lang::get('editor.action.test'))
                ->icon('heroicon-o-play')
                ->color('gray')
                // Save first so the run reflects exactly what's on screen, then open
                // the runner for this version.
                ->action(function (): void {
                    $this->save();
                    $this->redirect(GuideRunner::getUrl(['version' => $this->version]));
                }),
            Action::make('publish')
                ->label(Lang::get('editor.action.publish'))
                ->icon('heroicon-o-rocket-launch')
                ->color('warning')
                ->visible(fn (): bool => $this->isDraft())
                ->action(fn () => $this->publish()),
        ];
    }

    public function save(): void
    {
        /** @var array{nodes?: array<string, array<string, mixed>>, edges?: array<string, array<string, mixed>>, extra_attributes?: array<string, mixed>} $state */
        $state = $this->form->getState();

        DB::transaction(function () use ($state): void {
            $record = $this->record();

            $record->update([
                'extra_attributes' => is_array($state['extra_attributes'] ?? null) ? $state['extra_attributes'] : [],
            ]);

            // A published version's structure is frozen — nodes can't be added,
            // removed or rewired, and edges are untouched — but display content
            // (labels, prompts, verdicts, translations) stays editable, so it is
            // updated in place by key (preserving node ids the edges reference).
            if (! $this->isDraft()) {
                $this->updatePublishedContent($record, $state);

                return;
            }

            $record->edges()->delete();
            $record->nodes()->delete();

            /** @var array<string, int> $keyToId */
            $keyToId = [];

            foreach (array_values($state['nodes'] ?? []) as $position => $node) {
                $key = is_string($node['key'] ?? null) ? $node['key'] : '';
                if ($key === '') {
                    continue;
                }

                $created = $record->nodes()->create([
                    'type' => is_string($node['type'] ?? null) ? $node['type'] : QuestionNode::KEY,
                    'key' => $key,
                    'label' => filled($node['label'] ?? null) ? $node['label'] : null,
                    'config' => $this->cleanConfig(
                        is_string($node['type'] ?? null) ? $node['type'] : '',
                        is_array($node['config'] ?? null) ? $node['config'] : [],
                    ),
                    'position' => $position,
                ]);

                $keyToId[$key] = $created->id;
            }

            foreach ($state['edges'] ?? [] as $edge) {
                $from = $keyToId[$edge['from'] ?? ''] ?? null;
                $to = $keyToId[$edge['to'] ?? ''] ?? null;

                // Skip incomplete edges and self-loops (a self-loop is the trivial
                // cycle the engine forbids; the UI also excludes it from the options).
                if ($from === null || $to === null || $from === $to) {
                    continue;
                }

                $port = is_string($edge['fromPort'] ?? null) && $edge['fromPort'] !== '' ? $edge['fromPort'] : 'out';

                $record->edges()->create([
                    'from_node_id' => $from,
                    'to_node_id' => $to,
                    'from_port' => $port,
                    'condition' => $this->buildCondition($edge)?->toArray(),
                ]);
            }
        });

        // The cached version now holds stale node/edge relations; drop it so a
        // following publish (or re-render) re-reads the freshly saved rows.
        $this->recordCache = null;

        Notification::make()
            ->title(Lang::get($this->isDraft() ? 'editor.notification.saved' : 'editor.notification.saved_published'))
            ->success()
            ->send();
    }

    /**
     * Update the editable display content of a published version's nodes in place,
     * matched by key. Structure (keys, types, facts, input types, option values,
     * edges) is locked in the form and preserved here; only labels, prompts,
     * verdicts and their translations change. Node ids are kept so edges stay wired.
     *
     * @param  array{nodes?: array<string, array<string, mixed>>}  $state
     */
    private function updatePublishedContent(GuideVersion $record, array $state): void
    {
        $existing = $record->nodes()->get()->keyBy('key');

        foreach ($state['nodes'] ?? [] as $node) {
            $key = is_string($node['key'] ?? null) ? $node['key'] : '';
            $current = $key === '' ? null : $existing->get($key);
            if ($current === null) {
                continue;
            }

            $current->update([
                'label' => filled($node['label'] ?? null) ? $node['label'] : null,
                'config' => $this->cleanConfig(
                    is_string($node['type'] ?? null) ? $node['type'] : $current->type,
                    is_array($node['config'] ?? null) ? $node['config'] : [],
                ),
            ]);
        }
    }

    public function publish(): void
    {
        // A published version cannot be re-published from the editor; nodes/edges
        // are frozen, so there is nothing new to snapshot.
        if (! $this->isDraft()) {
            return;
        }

        $this->save();

        $this->publishErrors = [];

        $result = app(GuidePublisher::class)->publish($this->record());

        if ($result->fails()) {
            $this->publishErrors = array_map(
                static fn (ValidationError $error): string => $error->message,
                $result->errors,
            );

            Notification::make()
                ->title(Lang::get('editor.notification.publish_failed'))
                ->body(Lang::get('editor.notification.publish_failed_body', ['count' => count($this->publishErrors)]))
                ->danger()
                ->send();

            return;
        }

        Notification::make()->title(Lang::get('editor.notification.published'))->success()->send();
    }

    public function getMermaidSourceProperty(): string
    {
        return (new MermaidRenderer)->render($this->formDefinition());
    }

    /**
     * The engine's publish validation run against the *current* form state, so the
     * editor surfaces issues (missing config, uncovered ports, unreachable or
     * non-terminating nodes) live instead of only on publish.
     *
     * @return list<string>
     */
    public function validationIssues(): array
    {
        $record = $this->record();

        $result = app(PublishValidator::class)->validate(
            $this->formDefinition(),
            app(FactProviderRegistry::class)->for($record->guide->key)->vocabulary(),
            app(GuideProfileRegistry::class)->get($record->guide->profile),
        );

        return array_values(array_unique(array_map(
            static fn (ValidationError $error): string => $error->message,
            $result->errors,
        )));
    }

    /**
     * Build a guide definition from the *current, unsaved* form state so the
     * preview tracks edits live (incomplete nodes/edges are skipped).
     */
    private function formDefinition(): GuideDefinition
    {
        $record = $this->record();
        $data = $this->data ?? [];

        /** @var list<NodeDefinition> $nodes */
        $nodes = [];
        foreach (is_array($data['nodes'] ?? null) ? $data['nodes'] : [] as $node) {
            $key = is_array($node) && is_string($node['key'] ?? null) ? $node['key'] : '';
            if ($key === '') {
                continue;
            }

            $type = is_string($node['type'] ?? null) ? $node['type'] : QuestionNode::KEY;
            $config = is_array($node['config'] ?? null) ? $this->cleanConfig($type, $node['config']) : [];
            $label = filled($node['label'] ?? null) && is_string($node['label']) ? $node['label'] : null;

            $nodes[] = new NodeDefinition($key, $type, $config, $label);
        }

        /** @var list<EdgeDefinition> $edges */
        $edges = [];
        /** @var array<string, true> $incoming */
        $incoming = [];
        foreach (is_array($data['edges'] ?? null) ? $data['edges'] : [] as $edge) {
            $from = is_array($edge) && is_string($edge['from'] ?? null) ? $edge['from'] : '';
            $to = is_array($edge) && is_string($edge['to'] ?? null) ? $edge['to'] : '';
            if ($from === '' || $to === '' || $from === $to) {
                continue;
            }

            $port = is_string($edge['fromPort'] ?? null) && $edge['fromPort'] !== '' ? $edge['fromPort'] : 'out';
            $edges[] = new EdgeDefinition($from, $port, $to, $this->buildCondition($edge));
            $incoming[$to] = true;
        }

        $entry = '';
        foreach ($nodes as $node) {
            if (! isset($incoming[$node->key])) {
                $entry = $node->key;
                break;
            }
        }

        return new GuideDefinition(
            guideKey: $record->guide->key,
            version: $record->number,
            profile: $record->guide->profile,
            entryNode: $entry,
            nodes: $nodes,
            edges: $edges,
        );
    }

    // -- Schema -------------------------------------------------------------

    /**
     * A native info callout shown above the (read-only) Nodes/Edges of a published
     * version, explaining that a new version is needed to make changes.
     *
     * @return list<Callout>
     */
    private function readOnlyCallout(bool $frozen): array
    {
        if (! $frozen) {
            return [];
        }

        return [
            Callout::make(Lang::get('editor.readonly_notice_title'))
                ->description(Lang::get('editor.readonly_notice'))
                ->info(),
        ];
    }

    private function nodesRepeater(bool $frozen = false): Repeater
    {
        return Repeater::make('nodes')
            ->hiddenLabel()
            ->addActionLabel(Lang::get('editor.action.add_node'))
            ->collapsible()
            ->collapsed()
            // Structure is locked once published: no adding, removing or reordering.
            ->addable(! $frozen)
            ->deletable(! $frozen)
            ->reorderable(! $frozen)
            ->itemLabel(fn (array $state): string => filled($state['key'] ?? null)
                ? trim(($state['key']).' · '.($state['type'] ?? ''))
                : Lang::get('editor.item.new_node'))
            ->columns(2)
            ->schema([
                Select::make('type')
                    ->label(Lang::get('editor.field.type'))
                    ->options($this->nodeTypeOptions())
                    ->default(QuestionNode::KEY)
                    ->required()
                    ->live()
                    ->disabled($frozen)
                    ->dehydrated()
                    ->helperText(Lang::get('editor.field.type_help')),
                TextInput::make('key')
                    ->label(Lang::get('editor.field.key'))
                    ->distinct()
                    ->live(onBlur: true)
                    ->disabled($frozen)
                    ->dehydrated()
                    ->helperText(Lang::get('editor.field.key_help')),
                TextInput::make('label')
                    ->label(Lang::get('editor.field.label'))
                    ->live(onBlur: true)
                    ->helperText(Lang::get('editor.field.label_help'))
                    ->columnSpanFull(),
                ...$this->nodeConfigComponents($frozen),
            ]);
    }

    /** @return list<Component> */
    private function nodeConfigComponents(bool $frozen = false): array
    {
        $isQuestion = static fn (Get $get): bool => $get('type') === QuestionNode::KEY;
        $isOutcome = static fn (Get $get): bool => $get('type') === OutcomeNode::KEY;
        $usesFact = static fn (Get $get): bool => in_array(
            $get('type'),
            [QuestionNode::KEY, FactNode::KEY, DecisionNode::KEY],
            true,
        );

        return [
            TextInput::make('config.prompt')
                ->label(Lang::get('editor.field.prompt'))
                ->helperText(Lang::get('editor.field.prompt_help'))
                ->visible($isQuestion)
                ->columnSpanFull(),
            ...$this->translationInputs('prompt', Lang::get('editor.field.prompt'), $isQuestion),

            Select::make('config.inputType')
                ->label(Lang::get('editor.field.input_type'))
                ->options(array_combine(self::INPUT_TYPES, self::INPUT_TYPES))
                ->default('boolean')
                ->live()
                ->disabled($frozen)
                ->dehydrated()
                ->helperText(Lang::get('editor.field.input_type_help'))
                ->visible($isQuestion),
            TextInput::make('config.fact')
                ->label(Lang::get('editor.field.fact'))
                ->helperText(Lang::get('editor.field.fact_help'))
                ->disabled($frozen)
                ->dehydrated()
                ->visible($usesFact),
            Repeater::make('config.options')
                ->label(Lang::get('editor.field.options'))
                ->helperText(Lang::get('editor.field.options_help'))
                ->addActionLabel(Lang::get('editor.action.add_option'))
                // The set of options is structure; only their labels/translations are content.
                ->addable(! $frozen)
                ->deletable(! $frozen)
                ->reorderable(! $frozen)
                ->visible(static fn (Get $get): bool => $get('type') === QuestionNode::KEY && $get('config.inputType') === 'select')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('value')->label(Lang::get('editor.field.option_value'))->required()->disabled($frozen)->dehydrated(),
                    TextInput::make('label')->label(Lang::get('editor.field.option_label')),
                    ...$this->optionTranslationInputs(),
                ]),

            TextInput::make('config.verdict')
                ->label(Lang::get('editor.field.verdict'))
                ->helperText(Lang::get('editor.field.verdict_help'))
                ->visible($isOutcome)
                ->columnSpanFull(),
            ...$this->translationInputs('verdict', Lang::get('editor.field.verdict'), $isOutcome),
            Textarea::make('config.text')
                ->label(Lang::get('editor.field.text'))
                ->helperText(Lang::get('editor.field.text_help'))
                ->visible($isOutcome)
                ->columnSpanFull(),
            ...$this->translationInputs('text', Lang::get('editor.field.text'), $isOutcome),
            TagsInput::make('config.warnings')
                ->label(Lang::get('editor.field.warnings'))
                ->helperText(Lang::get('editor.field.warnings_help'))
                ->visible($isOutcome)
                ->columnSpanFull(),
            ...$this->listTranslationInputs('warnings', Lang::get('editor.field.warnings'), $isOutcome),
        ];
    }

    /**
     * Per-locale translation inputs for a string content field (written to `{field}_i18n`).
     *
     * @return list<TextInput>
     */
    private function translationInputs(string $field, string $label, Closure $visible): array
    {
        $inputs = [];

        foreach ($this->locales() as $locale) {
            $inputs[] = TextInput::make("config.{$field}_i18n.{$locale}")
                ->label(Lang::get('editor.field.translation_label', ['label' => $label, 'locale' => $locale]))
                ->visible($visible)
                ->columnSpanFull();
        }

        return $inputs;
    }

    /**
     * Per-locale translation inputs for a list content field (e.g. warnings).
     *
     * @return list<TagsInput>
     */
    private function listTranslationInputs(string $field, string $label, Closure $visible): array
    {
        $inputs = [];

        foreach ($this->locales() as $locale) {
            $inputs[] = TagsInput::make("config.{$field}_i18n.{$locale}")
                ->label(Lang::get('editor.field.translation_label', ['label' => $label, 'locale' => $locale]))
                ->visible($visible)
                ->columnSpanFull();
        }

        return $inputs;
    }

    /** @return list<TextInput> */
    private function optionTranslationInputs(): array
    {
        $inputs = [];

        foreach ($this->locales() as $locale) {
            $inputs[] = TextInput::make("label_i18n.{$locale}")
                ->label(Lang::get('editor.field.translation_label', ['label' => Lang::get('editor.field.option_label'), 'locale' => $locale]));
        }

        return $inputs;
    }

    private function edgesRepeater(): Repeater
    {
        return Repeater::make('edges')
            ->hiddenLabel()
            ->addActionLabel(Lang::get('editor.action.add_edge'))
            ->collapsible()
            ->collapsed()
            ->reorderable()
            ->itemLabel(fn (array $state): string => filled($state['from'] ?? null) && filled($state['to'] ?? null)
                ? "{$state['from']} → {$state['to']}"
                : Lang::get('editor.item.new_edge'))
            ->columns(3)
            ->schema([
                Select::make('from')
                    ->label(Lang::get('editor.field.from'))
                    ->options(fn (Get $get): array => $this->nodeKeyOptions(is_string($get('to')) ? $get('to') : null))
                    ->live(),
                TextInput::make('fromPort')
                    ->label(Lang::get('editor.field.port'))
                    ->default('out')
                    ->live(onBlur: true)
                    ->helperText(Lang::get('editor.field.port_help')),
                Select::make('to')
                    ->label(Lang::get('editor.field.to'))
                    ->options(fn (Get $get): array => $this->nodeKeyOptions(is_string($get('from')) ? $get('from') : null))
                    ->live(),
                Select::make('conditionType')
                    ->label(Lang::get('editor.field.condition'))
                    ->options($this->conditionTypeOptions())
                    ->default('always')
                    ->live()
                    ->columnSpanFull(),
                Select::make('fact')
                    ->label(Lang::get('editor.field.fact'))
                    ->options(fn (): array => $this->factOptions())
                    ->visible(static fn (Get $get): bool => in_array($get('conditionType'), ['structured', 'unknown'], true)),
                Select::make('operator')
                    ->label(Lang::get('editor.field.operator'))
                    ->options($this->operatorOptions())
                    ->visible(static fn (Get $get): bool => $get('conditionType') === 'structured'),
                TextInput::make('value')
                    ->label(Lang::get('editor.field.value'))
                    ->visible(static fn (Get $get): bool => $get('conditionType') === 'structured'),
                TextInput::make('expression')
                    ->label(Lang::get('editor.field.expression'))
                    ->helperText(Lang::get('editor.field.expression_help'))
                    ->visible(static fn (Get $get): bool => $get('conditionType') === 'expression')
                    ->columnSpanFull(),
            ]);
    }

    /** @return array<string, string> */
    private function conditionTypeOptions(): array
    {
        $options = [];

        foreach (array_keys(self::CONDITION_TYPES) as $key) {
            $options[$key] = Lang::get("editor.condition.{$key}");
        }

        return $options;
    }

    // -- State mapping ------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function nodesToState(GuideVersion $record): array
    {
        return $record->nodes()->orderBy('position')->orderBy('id')->get()
            ->map(static fn (GuideNode $node): array => [
                'type' => $node->type,
                'key' => $node->key,
                'label' => $node->label,
                'config' => $node->config ?? [],
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function edgesToState(GuideVersion $record): array
    {
        $keyById = $record->nodes->pluck('key', 'id');

        return $record->edges
            ->map(function (GuideEdge $edge) use ($keyById): array {
                $condition = $edge->conditionObject();

                $base = [
                    'from' => (string) ($keyById[$edge->from_node_id] ?? ''),
                    'fromPort' => $edge->from_port,
                    'to' => (string) ($keyById[$edge->to_node_id] ?? ''),
                ];

                if ($condition === null) {
                    return [...$base, 'conditionType' => 'always', 'fact' => '', 'operator' => Operator::Equals->value, 'value' => '', 'expression' => ''];
                }

                return [
                    ...$base,
                    'conditionType' => $condition->type->value,
                    'fact' => $condition->fact ?? '',
                    'operator' => $condition->operator !== null ? $condition->operator->value : Operator::Equals->value,
                    'value' => is_scalar($condition->value) ? (string) $condition->value : '',
                    'expression' => $condition->expression ?? '',
                ];
            })
            ->all();
    }

    // -- Persistence helpers ------------------------------------------------

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function cleanConfig(string $type, array $config): array
    {
        $allowed = match ($type) {
            QuestionNode::KEY => ['prompt', 'prompt_i18n', 'fact', 'inputType', 'options'],
            FactNode::KEY, DecisionNode::KEY => ['fact'],
            OutcomeNode::KEY => ['verdict', 'verdict_i18n', 'text', 'text_i18n', 'warnings', 'warnings_i18n'],
            default => array_keys($config),
        };

        $config = array_intersect_key($config, array_flip($allowed));

        if (isset($config['options']) && is_array($config['options'])) {
            $config['options'] = $this->cleanOptions($config['options']);
        }

        foreach ($config as $key => $value) {
            if (str_ends_with($key, '_i18n') && is_array($value)) {
                $translations = $this->cleanTranslations($value);

                if ($translations === []) {
                    unset($config[$key]);
                } else {
                    $config[$key] = $translations;
                }
            }
        }

        return $config;
    }

    /**
     * Drop blank translations from a `*_i18n` map. Values are either strings
     * (prompt/verdict/text) or lists of strings (warnings).
     *
     * @param  array<string, mixed>  $map
     * @return array<string, string|list<string>>
     */
    private function cleanTranslations(array $map): array
    {
        $cleaned = [];

        foreach ($map as $locale => $value) {
            if (is_string($value) && trim($value) !== '') {
                $cleaned[$locale] = $value;
            } elseif (is_array($value)) {
                $list = array_values(array_filter(
                    $value,
                    static fn (mixed $item): bool => is_string($item) && trim($item) !== '',
                ));

                if ($list !== []) {
                    $cleaned[$locale] = $list;
                }
            }
        }

        return $cleaned;
    }

    /**
     * @param  array<int|string, mixed>  $options
     * @return list<array<string, mixed>>
     */
    private function cleanOptions(array $options): array
    {
        $cleaned = [];

        foreach ($options as $option) {
            if (! is_array($option) || ! is_scalar($option['value'] ?? null) || (string) $option['value'] === '') {
                continue;
            }

            $value = (string) $option['value'];
            $entry = ['value' => $value, 'label' => is_scalar($option['label'] ?? null) && (string) $option['label'] !== '' ? (string) $option['label'] : $value];

            if (isset($option['label_i18n']) && is_array($option['label_i18n'])) {
                $i18n = array_filter($option['label_i18n'], static fn (mixed $v): bool => is_string($v) && trim($v) !== '');
                if ($i18n !== []) {
                    $entry['label_i18n'] = $i18n;
                }
            }

            $cleaned[] = $entry;
        }

        return $cleaned;
    }

    /** @param array<string, mixed> $edge */
    private function buildCondition(array $edge): ?Condition
    {
        $fact = is_string($edge['fact'] ?? null) ? trim($edge['fact']) : '';
        $type = is_string($edge['conditionType'] ?? null) ? $edge['conditionType'] : 'always';

        return match ($type) {
            'structured' => $fact === '' ? null : Condition::structured(
                $fact,
                Operator::from(is_string($edge['operator'] ?? null) ? $edge['operator'] : Operator::Equals->value),
                $this->castValue(is_string($edge['value'] ?? null) ? $edge['value'] : ''),
            ),
            'expression' => filled($edge['expression'] ?? null)
                ? Condition::expression(trim((string) $edge['expression']))
                : null,
            'unknown' => $fact === '' ? null : Condition::unknown($fact),
            default => null,
        };
    }

    private function castValue(string $value): mixed
    {
        return match (true) {
            $value === 'true' => true,
            $value === 'false' => false,
            is_numeric($value) => $value + 0,
            default => $value,
        };
    }

    // -- Option sources -----------------------------------------------------

    /** @return array<string, string> */
    private function nodeTypeOptions(): array
    {
        $keys = app(NodeTypeRegistry::class)->keys();

        return array_combine($keys, $keys);
    }

    /**
     * Node keys for the edge from/to selects. `$exclude` drops one key so an edge
     * cannot loop a node to itself — a self-loop is the trivial cycle the engine's
     * publish validator forbids.
     *
     * @return array<string, string>
     */
    private function nodeKeyOptions(?string $exclude = null): array
    {
        $keys = [];

        foreach ($this->data['nodes'] ?? [] as $node) {
            $key = is_array($node) && is_string($node['key'] ?? null) ? $node['key'] : '';
            if ($key !== '' && $key !== $exclude) {
                $keys[$key] = $key;
            }
        }

        return $keys;
    }

    /** @return array<string, string> */
    private function factOptions(): array
    {
        $facts = app(FactProviderRegistry::class)
            ->for($this->record()->guide->key)
            ->vocabulary()
            ->names();

        return array_combine($facts, $facts);
    }

    /** @return array<string, string> */
    private function operatorOptions(): array
    {
        $options = [];

        foreach (Operator::cases() as $operator) {
            $options[$operator->value] = $operator->value;
        }

        return $options;
    }

    /** @return list<string> */
    private function locales(): array
    {
        $locales = config('decision-support-filament.locales');

        if (! is_array($locales)) {
            return [];
        }

        return array_values(array_filter($locales, static fn (mixed $l): bool => is_string($l) && $l !== ''));
    }
}
