<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="space-y-6">
            {{-- Publish --}}
            <x-filament::section>
                <x-slot name="heading">Publish</x-slot>
                <x-slot name="description">
                    Runs the engine's validation pipeline and freezes the draft into an immutable snapshot.
                </x-slot>

                <div class="space-y-3">
                    <x-filament::button wire:click="publish" icon="heroicon-o-rocket-launch">
                        Publish version
                    </x-filament::button>

                    @if (filled($publishErrors))
                        <ul
                            data-publish-errors
                            class="list-disc space-y-1 rounded-lg bg-danger-50 p-4 text-sm text-danger-700 dark:bg-danger-950 dark:text-danger-300"
                        >
                            @foreach ($publishErrors as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </x-filament::section>

            {{-- Nodes --}}
            <x-filament::section>
                <x-slot name="heading">Nodes</x-slot>
                <x-slot name="description">
                    A node is a step in the guide: a question, a fact lookup, a decision, or a terminal outcome.
                </x-slot>

                <div class="space-y-2">
                    @forelse ($this->nodes() as $node)
                        <div class="flex items-center justify-between rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <div class="flex items-center gap-x-2">
                                <span class="font-mono text-sm font-medium">{{ $node->key }}</span>
                                <x-filament::badge>{{ $node->type }}</x-filament::badge>
                            </div>
                            <x-filament::icon-button
                                icon="heroicon-o-trash"
                                color="danger"
                                wire:click="deleteNode({{ $node->id }})"
                                label="Delete node"
                            />
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">No nodes yet.</p>
                    @endforelse
                </div>

                <div class="mt-4 space-y-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-decision-support-filament::field label="Type" help="The kind of step. Changing it swaps the configuration fields below.">
                            <x-filament::input.wrapper>
                                <x-filament::input.select wire:model.live="nodeDraft.type">
                                    @foreach ($this->nodeTypeKeys() as $type)
                                        <option value="{{ $type }}">{{ $type }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </x-decision-support-filament::field>

                        <x-decision-support-filament::field label="Key" help="Unique identifier for this node within the guide; edges reference it.">
                            <x-filament::input.wrapper>
                                <x-filament::input type="text" wire:model="nodeDraft.key" placeholder="e.g. q_employed" />
                            </x-filament::input.wrapper>
                        </x-decision-support-filament::field>
                    </div>

                    <x-decision-support-filament::field label="Label" help="Optional human-friendly name shown in the editor and diagram.">
                        <x-filament::input.wrapper>
                            <x-filament::input type="text" wire:model="nodeDraft.label" placeholder="optional" />
                        </x-filament::input.wrapper>
                    </x-decision-support-filament::field>

                    @foreach ($this->configSchema($nodeDraft['type']) as $field => $spec)
                        @php
                            $fieldType = is_array($spec) ? ($spec['type'] ?? 'string') : 'string';
                            $fieldHelp = is_array($spec) ? ($spec['help'] ?? null) : null;
                        @endphp

                        <x-decision-support-filament::field :label="$field" :help="$fieldHelp">
                            @if ($fieldType === 'enum' && is_array($spec) && isset($spec['values']) && is_array($spec['values']))
                                <x-filament::input.wrapper>
                                    <x-filament::input.select wire:model.live="nodeDraft.config.{{ $field }}">
                                        <option value="">—</option>
                                        @foreach ($spec['values'] as $value)
                                            <option value="{{ $value }}">{{ $value }}</option>
                                        @endforeach
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                            @elseif ($fieldType === 'list' && $field === 'options')
                                <x-filament::input.wrapper>
                                    <textarea wire:model="nodeDraft.config.optionsText" rows="3" placeholder="value:label per line" class="fi-input block w-full"></textarea>
                                </x-filament::input.wrapper>
                            @elseif ($fieldType === 'list')
                                <x-filament::input.wrapper>
                                    <textarea wire:model="nodeDraft.config.{{ $field }}Text" rows="3" placeholder="one per line" class="fi-input block w-full"></textarea>
                                </x-filament::input.wrapper>
                            @else
                                <x-filament::input.wrapper>
                                    <x-filament::input type="text" wire:model="nodeDraft.config.{{ $field }}" />
                                </x-filament::input.wrapper>
                            @endif

                            {{-- Per-locale translation inputs for content fields → the node's *_i18n map. --}}
                            @if (in_array($field, $this->translatableFields($nodeDraft['type']), true) && filled($this->locales()))
                                <div class="mt-1 space-y-1">
                                    @foreach ($this->locales() as $locale)
                                        <x-filament::input.wrapper>
                                            <x-filament::input
                                                type="text"
                                                wire:model="nodeDraft.config.{{ $field }}_i18n.{{ $locale }}"
                                                placeholder="{{ $field }} — {{ $locale }}"
                                            />
                                        </x-filament::input.wrapper>
                                    @endforeach
                                </div>
                            @endif
                        </x-decision-support-filament::field>
                    @endforeach

                    <x-filament::button wire:click="addNode" size="sm" icon="heroicon-o-plus" color="gray">
                        Add node
                    </x-filament::button>
                </div>
            </x-filament::section>

            {{-- Edges --}}
            <x-filament::section>
                <x-slot name="heading">Edges</x-slot>
                <x-slot name="description">
                    An edge routes from a node's output port to another node, optionally guarded by a condition.
                </x-slot>

                <div class="space-y-2">
                    @forelse ($this->edges() as $edge)
                        <div class="flex items-center justify-between rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-700">
                            <span class="font-mono">
                                {{ optional($edge->fromNode)->key ?? $edge->from_node_id }}
                                <span class="text-gray-400">[{{ $edge->from_port }}]</span>
                                →
                                {{ optional($edge->toNode)->key ?? $edge->to_node_id }}
                            </span>
                            <x-filament::icon-button
                                icon="heroicon-o-trash"
                                color="danger"
                                wire:click="deleteEdge({{ $edge->id }})"
                                label="Delete edge"
                            />
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">No edges yet.</p>
                    @endforelse
                </div>

                <div class="mt-4 space-y-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-decision-support-filament::field label="From" help="Source node.">
                            <x-filament::input.wrapper>
                                <x-filament::input.select wire:model="edgeDraft.from">
                                    <option value="">from…</option>
                                    @foreach ($this->nodes() as $node)
                                        <option value="{{ $node->key }}">{{ $node->key }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </x-decision-support-filament::field>

                        <x-decision-support-filament::field label="Port" help="Source output port (e.g. true/false for a boolean question, or out).">
                            <x-filament::input.wrapper>
                                <x-filament::input type="text" wire:model="edgeDraft.fromPort" placeholder="out" />
                            </x-filament::input.wrapper>
                        </x-decision-support-filament::field>

                        <x-decision-support-filament::field label="To" help="Target node.">
                            <x-filament::input.wrapper>
                                <x-filament::input.select wire:model="edgeDraft.to">
                                    <option value="">to…</option>
                                    @foreach ($this->nodes() as $node)
                                        <option value="{{ $node->key }}">{{ $node->key }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </x-decision-support-filament::field>
                    </div>

                    <x-decision-support-filament::field label="Condition" help="When this edge is taken. 'always' is the default/fallback; the others guard on a fact.">
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="edgeDraft.conditionType">
                                <option value="always">always (default)</option>
                                <option value="structured">structured (fact / operator / value)</option>
                                <option value="expression">expression</option>
                                <option value="unknown">fact unknown</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </x-decision-support-filament::field>

                    @if ($edgeDraft['conditionType'] === 'structured')
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <x-decision-support-filament::field label="Fact">
                                <x-filament::input.wrapper>
                                    <x-filament::input.select wire:model="edgeDraft.fact">
                                        <option value="">fact…</option>
                                        @foreach ($this->factNames() as $fact)
                                            <option value="{{ $fact }}">{{ $fact }}</option>
                                        @endforeach
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                            </x-decision-support-filament::field>

                            <x-decision-support-filament::field label="Operator">
                                <x-filament::input.wrapper>
                                    <x-filament::input.select wire:model="edgeDraft.operator">
                                        @foreach ($this->operatorOptions() as $op => $label)
                                            <option value="{{ $op }}">{{ $label }}</option>
                                        @endforeach
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                            </x-decision-support-filament::field>

                            <x-decision-support-filament::field label="Value">
                                <x-filament::input.wrapper>
                                    <x-filament::input type="text" wire:model="edgeDraft.value" placeholder="value" />
                                </x-filament::input.wrapper>
                            </x-decision-support-filament::field>
                        </div>
                    @elseif ($edgeDraft['conditionType'] === 'expression')
                        <x-decision-support-filament::field label="Expression" help="A symfony/expression-language expression evaluated against the guide's facts.">
                            <x-filament::input.wrapper>
                                <x-filament::input type="text" wire:model="edgeDraft.expression" placeholder="e.g. tenure_years >= 5" />
                            </x-filament::input.wrapper>
                        </x-decision-support-filament::field>
                    @elseif ($edgeDraft['conditionType'] === 'unknown')
                        <x-decision-support-filament::field label="Fact" help="Take this edge when the fact cannot be resolved.">
                            <x-filament::input.wrapper>
                                <x-filament::input.select wire:model="edgeDraft.fact">
                                    <option value="">fact…</option>
                                    @foreach ($this->factNames() as $fact)
                                        <option value="{{ $fact }}">{{ $fact }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </x-decision-support-filament::field>
                    @endif

                    <x-filament::button wire:click="addEdge" size="sm" icon="heroicon-o-plus" color="gray">
                        Add edge
                    </x-filament::button>
                </div>
            </x-filament::section>
        </div>

        {{-- Live preview --}}
        <div class="space-y-3">
            <x-filament::section>
                <x-slot name="heading">Live preview</x-slot>
                <x-decision-support-filament::mermaid :source="$this->mermaidSource" />
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
