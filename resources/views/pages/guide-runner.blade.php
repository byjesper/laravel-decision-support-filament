<x-filament-panels::page>
    {{-- Native Filament sections + inputs. Only layout/typography use literal CSS,
         since Filament does not ship the grid/spacing/text utilities a package view
         would otherwise need (it ships its own fi-* component classes). --}}
    <style>
        .ds-runner-grid { display: grid; gap: 1.5rem; align-items: start; }
        @media (min-width: 1280px) { .ds-runner-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .ds-runner-stack { display: flex; flex-direction: column; gap: 1.5rem; }
        .ds-runner-block { display: flex; flex-direction: column; gap: 1rem; }
        .ds-runner-row { display: flex; gap: 0.5rem; }
        .ds-runner-options { display: flex; flex-direction: column; gap: 0.5rem; }
        .ds-runner-grow { flex: 1 1 auto; }
        .ds-runner-prompt { font-size: 0.875rem; font-weight: 500; color: rgb(17 24 39); }
        .dark .ds-runner-prompt { color: rgb(255 255 255); }
        .ds-runner-text { font-size: 0.875rem; color: rgb(55 65 81); }
        .dark .ds-runner-text { color: rgb(209 213 219); }
        .ds-runner-warnings { display: flex; flex-direction: column; gap: 0.25rem; list-style: disc; padding: 1rem 1rem 1rem 2.25rem; border-radius: 0.5rem; font-size: 0.875rem; }
    </style>

    <div class="ds-runner-grid">
        <div class="ds-runner-stack">
            @php($state = $this->runState())
            @php($interaction = $this->interaction())
            @php($outcome = $this->outcome())

            @if ($state === null)
                <x-filament::section>
                    <x-slot name="heading">Start</x-slot>
                    <x-filament::button wire:click="start" icon="heroicon-o-play">
                        Start run
                    </x-filament::button>
                </x-filament::section>
            @endif

            @if ($interaction !== null)
                <x-filament::section>
                    <x-slot name="heading">Question</x-slot>

                    <div data-interaction class="ds-runner-block">
                        <p class="ds-runner-prompt">{{ $interaction->prompt }}</p>

                        @if ($interaction->inputType === 'boolean')
                            <div class="ds-runner-row">
                                <x-filament::button wire:click="submit('true')" color="success">Yes</x-filament::button>
                                <x-filament::button wire:click="submit('false')" color="danger">No</x-filament::button>
                            </div>
                        @elseif ($interaction->inputType === 'select')
                            <div class="ds-runner-options">
                                @foreach ($interaction->options as $option)
                                    <x-filament::button wire:click="submit('{{ $option['value'] }}')" color="gray">
                                        {{ $option['label'] }}
                                    </x-filament::button>
                                @endforeach
                            </div>
                        @else
                            <div class="ds-runner-row">
                                <x-filament::input.wrapper class="ds-runner-grow">
                                    <x-filament::input
                                        type="{{ $interaction->inputType === 'number' ? 'number' : ($interaction->inputType === 'date' ? 'date' : 'text') }}"
                                        wire:model="input"
                                        wire:keydown.enter="submit"
                                    />
                                </x-filament::input.wrapper>
                                <x-filament::button wire:click="submit">Submit</x-filament::button>
                            </div>
                        @endif

                        @if ($this->canGoBack())
                            <div>
                                <x-filament::button wire:click="back" color="gray" size="sm" icon="heroicon-o-arrow-left">
                                    Back
                                </x-filament::button>
                            </div>
                        @endif
                    </div>
                </x-filament::section>
            @endif

            @if ($outcome !== null)
                <x-filament::section>
                    <x-slot name="heading">Result</x-slot>

                    <div data-outcome class="ds-runner-block">
                        <div>
                            <x-filament::badge :color="$outcome->unknown ? 'warning' : 'success'" size="lg">
                                {{ $outcome->verdict }}
                            </x-filament::badge>
                        </div>

                        @if (filled($outcome->text))
                            <p class="ds-runner-text">{{ $outcome->text }}</p>
                        @endif

                        @if (filled($outcome->warnings))
                            <ul class="ds-runner-warnings rounded-lg bg-warning-50 p-4 text-sm text-warning-700 dark:bg-warning-950 dark:text-warning-300">
                                @foreach ($outcome->warnings as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="ds-runner-row">
                            @if ($this->canGoBack())
                                <x-filament::button wire:click="back" color="gray" icon="heroicon-o-arrow-left">
                                    Back
                                </x-filament::button>
                            @endif
                            <x-filament::button wire:click="restart" color="gray" icon="heroicon-o-arrow-path">
                                Start over
                            </x-filament::button>
                        </div>
                    </div>
                </x-filament::section>
            @endif
        </div>

        <div class="ds-runner-stack">
            <x-filament::section>
                <x-slot name="heading">Path</x-slot>
                <x-decision-support-filament::mermaid :source="$this->mermaidSource" />
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
