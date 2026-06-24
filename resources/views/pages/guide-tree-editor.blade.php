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

                <div class="space-y-2">
                    @forelse ($this->nodes() as $node)
                        <div class="flex items-center justify-between rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <div>
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
                        <p class="text-sm text-gray-500">No nodes yet.</p>
                    @endforelse
                </div>

                <x-slot name="footerActions">
                </x-slot>

                <div class="mt-4 space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                    <div class="grid grid-cols-2 gap-3">
                        <select wire:model.live="nodeDraft.type" class="fi-input rounded-lg border-gray-300 text-sm dark:bg-gray-900">
                            @foreach ($this->nodeTypeKeys() as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </select>
                        <input type="text" wire:model="nodeDraft.key" placeholder="key" class="fi-input rounded-lg border-gray-300 text-sm dark:bg-gray-900" />
                    </div>

                    <input type="text" wire:model="nodeDraft.label" placeholder="label (optional)" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900" />

                    @foreach ($this->configSchema($nodeDraft['type']) as $field => $spec)
                        @php($type = is_array($spec) ? ($spec['type'] ?? 'string') : 'string')
                        <div>
                            <label class="text-xs font-medium text-gray-600 dark:text-gray-400">{{ $field }}</label>
                            @if ($type === 'enum' && is_array($spec) && isset($spec['values']) && is_array($spec['values']))
                                <select wire:model.live="nodeDraft.config.{{ $field }}" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900">
                                    <option value="">—</option>
                                    @foreach ($spec['values'] as $value)
                                        <option value="{{ $value }}">{{ $value }}</option>
                                    @endforeach
                                </select>
                            @elseif ($type === 'list' && $field === 'options')
                                <textarea wire:model="nodeDraft.config.optionsText" rows="3" placeholder="value:label per line" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900"></textarea>
                            @elseif ($type === 'list')
                                <textarea wire:model="nodeDraft.config.{{ $field }}Text" rows="3" placeholder="one per line" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900"></textarea>
                            @else
                                <input type="text" wire:model="nodeDraft.config.{{ $field }}" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900" />
                            @endif
                        </div>
                    @endforeach

                    <x-filament::button wire:click="addNode" size="sm" icon="heroicon-o-plus" color="gray">
                        Add node
                    </x-filament::button>
                </div>
            </x-filament::section>

            {{-- Edges --}}
            <x-filament::section>
                <x-slot name="heading">Edges</x-slot>

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
                        <p class="text-sm text-gray-500">No edges yet.</p>
                    @endforelse
                </div>

                <div class="mt-4 space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                    <div class="grid grid-cols-3 gap-3">
                        <select wire:model="edgeDraft.from" class="fi-input rounded-lg border-gray-300 text-sm dark:bg-gray-900">
                            <option value="">from…</option>
                            @foreach ($this->nodes() as $node)
                                <option value="{{ $node->key }}">{{ $node->key }}</option>
                            @endforeach
                        </select>
                        <input type="text" wire:model="edgeDraft.fromPort" placeholder="port" class="fi-input rounded-lg border-gray-300 text-sm dark:bg-gray-900" />
                        <select wire:model="edgeDraft.to" class="fi-input rounded-lg border-gray-300 text-sm dark:bg-gray-900">
                            <option value="">to…</option>
                            @foreach ($this->nodes() as $node)
                                <option value="{{ $node->key }}">{{ $node->key }}</option>
                            @endforeach
                        </select>
                    </div>

                    <select wire:model.live="edgeDraft.conditionType" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900">
                        <option value="always">always (default)</option>
                        <option value="structured">structured</option>
                        <option value="expression">expression</option>
                        <option value="unknown">fact unknown</option>
                    </select>

                    @if ($edgeDraft['conditionType'] === 'structured')
                        <div class="grid grid-cols-3 gap-3">
                            <select wire:model="edgeDraft.fact" class="fi-input rounded-lg border-gray-300 text-sm dark:bg-gray-900">
                                <option value="">fact…</option>
                                @foreach ($this->factNames() as $fact)
                                    <option value="{{ $fact }}">{{ $fact }}</option>
                                @endforeach
                            </select>
                            <select wire:model="edgeDraft.operator" class="fi-input rounded-lg border-gray-300 text-sm dark:bg-gray-900">
                                @foreach ($this->operatorOptions() as $op => $label)
                                    <option value="{{ $op }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <input type="text" wire:model="edgeDraft.value" placeholder="value" class="fi-input rounded-lg border-gray-300 text-sm dark:bg-gray-900" />
                        </div>
                    @elseif ($edgeDraft['conditionType'] === 'expression')
                        <input type="text" wire:model="edgeDraft.expression" placeholder="expression" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900" />
                    @elseif ($edgeDraft['conditionType'] === 'unknown')
                        <select wire:model="edgeDraft.fact" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900">
                            <option value="">fact…</option>
                            @foreach ($this->factNames() as $fact)
                                <option value="{{ $fact }}">{{ $fact }}</option>
                            @endforeach
                        </select>
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
