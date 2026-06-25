<x-filament-panels::page>
    {{-- The form (native Filament schema: Nodes/Edges sections of repeaters) sits in
         column 1; the live preview and validation in column 2. Save/Publish/Test run
         live in the page header. Only this outer two-column grid (and the small issue
         list) uses literal CSS — Filament does not ship the `grid-cols-*` utilities a
         package view would otherwise need. --}}
    <style>
        .ds-editor-grid { display: grid; gap: 1.5rem; align-items: start; }
        @media (min-width: 1280px) { .ds-editor-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .ds-editor-side { display: flex; flex-direction: column; gap: 1.5rem; }
        .ds-editor-issues { display: flex; flex-direction: column; gap: 0.25rem; list-style: disc; padding-left: 1.25rem; font-size: 0.875rem; color: rgb(190 18 60); }
        .dark .ds-editor-issues { color: rgb(253 164 175); }
        .ds-editor-ok { font-size: 0.875rem; color: rgb(21 128 61); }
        .dark .ds-editor-ok { color: rgb(134 239 172); }
    </style>

    <div class="ds-editor-grid">
        <div>{{ $this->form }}</div>

        <div class="ds-editor-side">
            @php($issues = $this->validationIssues())
            <x-filament::section>
                <x-slot name="heading">{{ __('decision-support-filament::editor.section.validation') }}</x-slot>
                <x-slot name="description">{{ __('decision-support-filament::editor.section.validation_description') }}</x-slot>

                @if (filled($issues))
                    <ul data-validation-issues class="ds-editor-issues">
                        @foreach ($issues as $issue)
                            <li>{{ $issue }}</li>
                        @endforeach
                    </ul>
                @else
                    <p data-validation-ok class="ds-editor-ok">{{ __('decision-support-filament::editor.validation.ok') }}</p>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">{{ __('decision-support-filament::editor.section.preview') }}</x-slot>
                <x-slot name="description">{{ __('decision-support-filament::editor.section.preview_description') }}</x-slot>

                <x-decision-support-filament::mermaid :source="$this->mermaidSource" />
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
