@props([
    'source' => '',
    'theme' => null,
])

{{-- Mermaid preview container. The bundled decision-support asset finds every
     element with this data attribute and renders its source, re-running after
     each Livewire DOM morph. `wire:ignore` keeps the rendered SVG from being
     clobbered on every round-trip, while the source-keyed `wire:key` forces
     Livewire to replace the element (so the JS re-renders) whenever the source
     actually changes — e.g. after saving a node or edge, or advancing a run. --}}
<div
    wire:ignore
    wire:key="decision-support-mermaid-{{ md5((string) $source) }}"
    data-decision-support-mermaid
    data-mermaid-source="{{ $source }}"
    data-mermaid-theme="{{ $theme ?? config('decision-support-filament.mermaid.theme', 'default') }}"
    {{ $attributes->merge([
        'class' => 'decision-support-mermaid',
        'style' => 'overflow-x: auto; border-radius: 0.5rem; padding: 1rem;',
    ]) }}
>
    {{-- Placeholder shown until the bundled asset renders the SVG (it reads the
         source from data-mermaid-source, not from here), so the raw Mermaid code
         is never displayed. --}}
    <div data-mermaid-placeholder style="display: flex; align-items: center; justify-content: center; min-height: 6rem; color: rgb(156 163 175); font-size: 0.875rem;">
        Rendering preview…
    </div>
</div>
