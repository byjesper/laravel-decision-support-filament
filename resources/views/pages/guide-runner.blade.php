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
        /* Required marker (red asterisk) and the blank-answer validation message.
           Spelled out literally for the same reason as the warning colours below.
           A required prompt renders its (markdown) paragraph inline so the asterisk
           sits on the same line as the question text instead of dropping below it. */
        .ds-runner-prompt-required p { display: inline; }
        .ds-runner-required { color: rgb(220 38 38); font-weight: 600; margin-left: 0.125rem; }
        .dark .ds-runner-required { color: rgb(248 113 113); }
        .ds-runner-error { font-size: 0.875rem; color: rgb(220 38 38); margin-top: 0.5rem; }
        .dark .ds-runner-error { color: rgb(248 113 113); }
        .ds-runner-text { font-size: 0.875rem; color: rgb(55 65 81); }
        .dark .ds-runner-text { color: rgb(209 213 219); }
        /* Markdown output (outcome text / prompts). Host apps don't compile a
           prose plugin, so the list/paragraph rhythm is spelled out literally. */
        .ds-runner-prose > :first-child { margin-top: 0; }
        .ds-runner-prose > :last-child { margin-bottom: 0; }
        .ds-runner-prose p { margin: 0 0 0.75rem; }
        .ds-runner-prose ul { list-style: disc; padding-left: 1.5rem; margin: 0.5rem 0; }
        .ds-runner-prose ol { list-style: decimal; padding-left: 1.5rem; margin: 0.5rem 0; }
        .ds-runner-prose li { margin: 0.25rem 0; }
        .ds-runner-prose strong { font-weight: 600; }
        .ds-runner-prose a { text-decoration: underline; }
        .ds-runner-prose code { font-family: ui-monospace, monospace; font-size: 0.8125rem; }
        /* Warning box colours live here (amber), not in Tailwind utility classes:
           a host's Tailwind build does not scan this package view, so utilities
           like bg-warning-* would never compile. */
        .ds-runner-warnings { display: flex; flex-direction: column; gap: 0.25rem; list-style: disc; padding: 1rem 1rem 1rem 2.25rem; border-radius: 0.5rem; font-size: 0.875rem; background: rgb(255 251 235); color: rgb(146 64 14); }
        .dark .ds-runner-warnings { background: rgb(69 26 3); color: rgb(252 211 77); }
    </style>

    <div class="ds-runner-grid">
        <div class="ds-runner-stack">
            @php($state = $this->runState())
            @php($interaction = $this->interaction())
            @php($outcome = $this->outcome())

            @if ($state === null)
                <x-filament::section>
                    <x-slot name="heading">{{ __('decision-support-filament::runner.section.start') }}</x-slot>
                    <x-filament::button wire:click="start" icon="heroicon-o-play">
                        {{ __('decision-support-filament::runner.action.start') }}
                    </x-filament::button>
                </x-filament::section>
            @endif

            @if ($interaction !== null)
                <x-filament::section>
                    <x-slot name="heading">{{ __('decision-support-filament::runner.section.question') }}</x-slot>

                    <div data-interaction class="ds-runner-block">
                        <div class="ds-runner-prompt ds-runner-prose{{ $interaction->required ? ' ds-runner-prompt-required' : '' }}">
                            {!! $this->markdown($interaction->prompt) !!}
                            {{-- A required free question flags its prompt with a red asterisk,
                                 kept inline with the question text (see ds-runner-prompt-required). --}}
                            @if ($interaction->required)
                                <span class="ds-runner-required" aria-hidden="true" data-required>*</span>
                                <span class="fi-sr-only">{{ __('decision-support-filament::runner.required') }}</span>
                            @endif
                        </div>

                        {{-- Every answer control targets the `submit` method (not its own
                             specific call), so during any in-flight submit they all disable
                             together — you can't fire a second answer (e.g. click "No" while
                             "Yes" is still resolving an external fact lookup). --}}
                        @if ($interaction->inputType === 'boolean')
                            <div class="ds-runner-row">
                                <x-filament::button wire:click="submit('true')" wire:target="submit" wire:loading.attr="disabled" color="success">{{ __('decision-support-filament::runner.action.yes') }}</x-filament::button>
                                <x-filament::button wire:click="submit('false')" wire:target="submit" wire:loading.attr="disabled" color="danger">{{ __('decision-support-filament::runner.action.no') }}</x-filament::button>
                            </div>
                        @elseif ($interaction->inputType === 'select')
                            <div class="ds-runner-options">
                                @foreach ($interaction->options as $option)
                                    <x-filament::button wire:click="submit('{{ $option['value'] }}')" wire:target="submit" wire:loading.attr="disabled" color="gray">
                                        {{ $option['label'] }}
                                    </x-filament::button>
                                @endforeach
                            </div>
                        @else
                            {{-- Advancing can trigger an external fact lookup that takes a while.
                                 Spin the Submit button and disable the input for the whole submit
                                 request (whether triggered by click or Enter) so the wait reads as
                                 progress, not a frozen form. A required question is validated on
                                 submit (the button stays enabled): a blank answer shows the error
                                 below rather than advancing, and the engine re-suspends on blank as
                                 the authoritative backstop. --}}
                            <div>
                                <div class="ds-runner-row">
                                    <x-filament::input.wrapper class="ds-runner-grow" wire:target="submit" wire:loading.attr="disabled">
                                        <x-filament::input
                                            type="{{ $interaction->inputType === 'number' ? 'number' : ($interaction->inputType === 'date' ? 'date' : 'text') }}"
                                            wire:model="input"
                                            wire:keydown.enter="submit"
                                            wire:target="submit"
                                            wire:loading.attr="disabled"
                                        />
                                    </x-filament::input.wrapper>
                                    <x-filament::button wire:click="submit" wire:target="submit" wire:loading.attr="disabled">
                                        {{ __('decision-support-filament::runner.action.submit') }}
                                    </x-filament::button>
                                </div>
                                @if ($inputError !== null)
                                    <p class="ds-runner-error" data-input-error>{{ $inputError }}</p>
                                @endif
                            </div>
                        @endif

                        @if ($this->canGoBack())
                            <div>
                                <x-filament::button wire:click="back" color="gray" size="sm" icon="heroicon-o-arrow-left">
                                    {{ __('decision-support-filament::runner.action.back') }}
                                </x-filament::button>
                            </div>
                        @endif
                    </div>
                </x-filament::section>
            @endif

            @if ($outcome !== null)
                <x-filament::section>
                    <x-slot name="heading">{{ __('decision-support-filament::runner.section.result') }}</x-slot>

                    <div data-outcome class="ds-runner-block">
                        <div>
                            <x-filament::badge :color="$outcome->unknown ? 'warning' : 'success'" size="lg">
                                {{ $outcome->verdict }}
                            </x-filament::badge>
                        </div>

                        @if (filled($outcome->text))
                            <div data-outcome-text class="ds-runner-text ds-runner-prose">{!! $this->markdown($outcome->text) !!}</div>
                        @endif

                        @if (filled($outcome->warnings))
                            <ul data-warnings class="ds-runner-warnings">
                                @foreach ($outcome->warnings as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="ds-runner-row">
                            @if ($this->canGoBack())
                                <x-filament::button wire:click="back" color="gray" icon="heroicon-o-arrow-left">
                                    {{ __('decision-support-filament::runner.action.back') }}
                                </x-filament::button>
                            @endif
                            <x-filament::button wire:click="restart" color="gray" icon="heroicon-o-arrow-path">
                                {{ __('decision-support-filament::runner.action.restart') }}
                            </x-filament::button>
                        </div>
                    </div>
                </x-filament::section>
            @endif
        </div>

        <div class="ds-runner-stack">
            <x-filament::section>
                <x-slot name="heading">{{ __('decision-support-filament::runner.section.path') }}</x-slot>
                <x-decision-support-filament::mermaid :source="$this->mermaidSource" />
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
