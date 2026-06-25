<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament\Pages;

use ByJesper\DecisionSupport\Conditions\Condition;
use ByJesper\DecisionSupport\Contracts\NodeType;
use ByJesper\DecisionSupport\Enums\ConditionType;
use ByJesper\DecisionSupport\Enums\Operator;
use ByJesper\DecisionSupport\Mermaid\MermaidRenderer;
use ByJesper\DecisionSupport\Models\GuideEdge;
use ByJesper\DecisionSupport\Models\GuideNode;
use ByJesper\DecisionSupport\Models\GuideVersion;
use ByJesper\DecisionSupport\NodeTypes\OutcomeNode;
use ByJesper\DecisionSupport\NodeTypes\QuestionNode;
use ByJesper\DecisionSupport\Publishing\GuidePublisher;
use ByJesper\DecisionSupport\Registry\FactProviderRegistry;
use ByJesper\DecisionSupport\Registry\NodeTypeRegistry;
use ByJesper\DecisionSupport\Validation\ValidationError;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Collection;

/**
 * The non-developer authoring surface for a single draft version. Nodes are
 * added with a per-type config form driven by {@see NodeType::configSchema()};
 * edges carry a structured (fact + operator + value), expression, or sentinel
 * (always/unknown) condition built from the guide's fact vocabulary. The live
 * Mermaid preview is the engine's own renderer, and **Publish** runs the
 * engine's publish pipeline, surfacing any validation failures inline instead
 * of freezing an invalid graph.
 *
 * @property-read string $mermaidSource
 */
class GuideTreeEditor extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'decision-support-filament::pages.guide-tree-editor';

    public int $version;

    /** @var array{type: string, key: string, label: string, config: array<string, mixed>} */
    public array $nodeDraft = [
        'type' => 'question',
        'key' => '',
        'label' => '',
        'config' => [],
    ];

    /** @var array{from: string, fromPort: string, to: string, conditionType: string, fact: string, operator: string, value: string, expression: string} */
    public array $edgeDraft = [
        'from' => '',
        'fromPort' => 'out',
        'to' => '',
        'conditionType' => 'always',
        'fact' => '',
        'operator' => '=',
        'value' => '',
        'expression' => '',
    ];

    /** @var list<string> */
    public array $publishErrors = [];

    #[\Override]
    public static function getRoutePath(Panel $panel): string
    {
        return '/'.self::getSlug($panel).'/{version}';
    }

    public function mount(int $version): void
    {
        $this->version = $version;
        $this->record(); // 404 early if the version does not exist.
    }

    #[\Override]
    public function getTitle(): string
    {
        $record = $this->record();

        return "Edit tree — {$record->guide->name} v{$record->number}";
    }

    public function record(): GuideVersion
    {
        return GuideVersion::query()->with(['guide', 'nodes', 'edges'])->findOrFail($this->version);
    }

    /** @return Collection<int, GuideNode> */
    public function nodes(): Collection
    {
        return $this->record()->nodes()->orderBy('position')->orderBy('id')->get();
    }

    /** @return Collection<int, GuideEdge> */
    public function edges(): Collection
    {
        return $this->record()->edges()->get();
    }

    /** @return list<string> */
    public function nodeTypeKeys(): array
    {
        return app(NodeTypeRegistry::class)->keys();
    }

    /** @return array<string, mixed> */
    public function configSchema(string $type): array
    {
        $nodeType = app(NodeTypeRegistry::class)->get($type);

        return $nodeType === null ? [] : $nodeType->configSchema();
    }

    /**
     * Content config fields the editor offers per-locale translation inputs for,
     * written into the node's `{field}_i18n` map and resolved by the engine at
     * run time. Other string fields (e.g. `fact`) are not user-facing content.
     *
     * @return list<string>
     */
    public function translatableFields(string $type): array
    {
        return match ($type) {
            QuestionNode::KEY => ['prompt'],
            OutcomeNode::KEY => ['verdict', 'text'],
            default => [],
        };
    }

    /**
     * Locales the editor offers a translation input for, per translatable field.
     *
     * @return list<string>
     */
    public function locales(): array
    {
        $locales = config('decision-support-filament.locales');

        if (! is_array($locales)) {
            return [];
        }

        return array_values(array_filter($locales, static fn (mixed $l): bool => is_string($l) && $l !== ''));
    }

    /** @return list<string> */
    public function factNames(): array
    {
        return app(FactProviderRegistry::class)
            ->for($this->record()->guide->key)
            ->vocabulary()
            ->names();
    }

    /** @return array<string, string> */
    public function operatorOptions(): array
    {
        $options = [];

        foreach (Operator::cases() as $operator) {
            $options[$operator->value] = $operator->value;
        }

        return $options;
    }

    public function getMermaidSourceProperty(): string
    {
        return (new MermaidRenderer)->render($this->record()->toDefinition());
    }

    public function addNode(): void
    {
        $key = trim($this->nodeDraft['key']);

        if ($key === '') {
            $this->nodeError('A node key is required.');

            return;
        }

        $record = $this->record();

        if ($record->nodes()->where('key', $key)->exists()) {
            $this->nodeError("A node with key '{$key}' already exists.");

            return;
        }

        $label = trim($this->nodeDraft['label']);

        $record->nodes()->create([
            'type' => $this->nodeDraft['type'],
            'key' => $key,
            'label' => $label === '' ? null : $label,
            'config' => $this->normalizeConfig(),
            'position' => (int) $record->nodes()->max('position') + 1,
        ]);

        $this->nodeDraft = ['type' => $this->nodeDraft['type'], 'key' => '', 'label' => '', 'config' => []];

        Notification::make()->title("Node '{$key}' added")->success()->send();
    }

    public function deleteNode(int $id): void
    {
        $this->record()->nodes()->whereKey($id)->delete();
    }

    public function addEdge(): void
    {
        $record = $this->record();

        $from = $record->nodes()->where('key', $this->edgeDraft['from'])->first();
        $to = $record->nodes()->where('key', $this->edgeDraft['to'])->first();

        if ($from === null || $to === null) {
            $this->nodeError('Both a source and a target node are required for an edge.');

            return;
        }

        $port = trim($this->edgeDraft['fromPort']);

        $record->edges()->create([
            'from_node_id' => $from->getKey(),
            'to_node_id' => $to->getKey(),
            'from_port' => $port === '' ? 'out' : $port,
            'condition' => $this->buildCondition()?->toArray(),
        ]);

        $this->edgeDraft = [...$this->edgeDraft, 'from' => '', 'to' => '', 'fact' => '', 'value' => '', 'expression' => ''];
    }

    public function deleteEdge(int $id): void
    {
        $this->record()->edges()->whereKey($id)->delete();
    }

    public function publish(): void
    {
        $this->publishErrors = [];

        $result = app(GuidePublisher::class)->publish($this->record());

        if ($result->fails()) {
            $this->publishErrors = array_map(
                static fn (ValidationError $error): string => $error->message,
                $result->errors,
            );

            Notification::make()
                ->title('Publishing failed')
                ->body('The guide has '.count($this->publishErrors).' validation issue(s) to resolve.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()->title('Guide published')->success()->send();
    }

    private function buildCondition(): ?Condition
    {
        $fact = trim($this->edgeDraft['fact']);

        return match ($this->edgeDraft['conditionType']) {
            'structured' => $fact === ''
                ? null
                : Condition::structured($fact, Operator::from($this->edgeDraft['operator']), $this->castValue($this->edgeDraft['value'])),
            'expression' => Condition::expression(trim($this->edgeDraft['expression'])),
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

    /** @return array<string, mixed> */
    private function normalizeConfig(): array
    {
        $config = $this->nodeDraft['config'];

        if (isset($config['optionsText']) && is_string($config['optionsText'])) {
            $config['options'] = $this->parseOptions($config['optionsText']);
            unset($config['optionsText']);
        }

        if (isset($config['warningsText']) && is_string($config['warningsText'])) {
            $config['warnings'] = array_values(array_filter(array_map(
                trim(...),
                explode("\n", $config['warningsText']),
            ), static fn (string $line): bool => $line !== ''));
            unset($config['warningsText']);
        }

        // Drop blank per-locale translations so an empty input never overrides the
        // base string at run time (the resolver treats '' as a present translation).
        foreach ($config as $key => $value) {
            if (str_ends_with($key, '_i18n') && is_array($value)) {
                $translations = array_filter(
                    $value,
                    static fn (mixed $v): bool => is_string($v) && trim($v) !== '',
                );

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
     * @return list<array{value: string, label: string}>
     */
    private function parseOptions(string $text): array
    {
        $options = [];

        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            [$value, $label] = array_pad(explode(':', $line, 2), 2, null);
            $value = trim((string) $value);
            $options[] = ['value' => $value, 'label' => $label === null ? $value : trim($label)];
        }

        return $options;
    }

    private function nodeError(string $message): void
    {
        Notification::make()->title($message)->danger()->send();
    }
}
