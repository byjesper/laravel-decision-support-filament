<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="space-y-6">
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

                    <div data-interaction class="space-y-4">
                        <p class="text-sm font-medium">{{ $interaction->prompt }}</p>

                        @if ($interaction->inputType === 'boolean')
                            <div class="flex gap-2">
                                <x-filament::button wire:click="submit('true')" color="success">Yes</x-filament::button>
                                <x-filament::button wire:click="submit('false')" color="danger">No</x-filament::button>
                            </div>
                        @elseif ($interaction->inputType === 'select')
                            <div class="space-y-2">
                                @foreach ($interaction->options as $option)
                                    <x-filament::button wire:click="submit('{{ $option['value'] }}')" color="gray" class="w-full">
                                        {{ $option['label'] }}
                                    </x-filament::button>
                                @endforeach
                            </div>
                        @else
                            <div class="flex gap-2">
                                <input
                                    type="{{ $interaction->inputType === 'number' ? 'number' : ($interaction->inputType === 'date' ? 'date' : 'text') }}"
                                    wire:model="input"
                                    wire:keydown.enter="submit"
                                    class="fi-input w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900"
                                />
                                <x-filament::button wire:click="submit">Submit</x-filament::button>
                            </div>
                        @endif
                    </div>
                </x-filament::section>
            @endif

            @if ($outcome !== null)
                <x-filament::section>
                    <x-slot name="heading">Result</x-slot>

                    <div data-outcome class="space-y-3">
                        <x-filament::badge :color="$outcome->unknown ? 'warning' : 'success'" size="lg">
                            {{ $outcome->verdict }}
                        </x-filament::badge>

                        @if (filled($outcome->text))
                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $outcome->text }}</p>
                        @endif

                        @if (filled($outcome->warnings))
                            <ul class="list-disc space-y-1 rounded-lg bg-warning-50 p-4 text-sm text-warning-700 dark:bg-warning-950 dark:text-warning-300">
                                @foreach ($outcome->warnings as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                        @endif

                        <x-filament::button wire:click="restart" color="gray" icon="heroicon-o-arrow-path">
                            Start over
                        </x-filament::button>
                    </div>
                </x-filament::section>
            @endif
        </div>

        <div class="space-y-3">
            <x-filament::section>
                <x-slot name="heading">Path</x-slot>
                <x-decision-support-filament::mermaid :source="$this->mermaidSource" />
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
