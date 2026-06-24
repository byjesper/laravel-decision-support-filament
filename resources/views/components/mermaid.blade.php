@props([
    'source' => '',
    'theme' => null,
])

{{-- Mermaid preview container. The bundled decision-support asset finds every
     element with this data attribute and renders its source, re-running after
     each Livewire DOM morph. `wire:ignore` keeps the rendered SVG from being
     clobbered on the next round-trip. --}}
<div
    wire:ignore
    data-decision-support-mermaid
    data-mermaid-source="{{ $source }}"
    data-mermaid-theme="{{ $theme ?? config('decision-support-filament.mermaid.theme', 'default') }}"
    {{ $attributes->merge(['class' => 'decision-support-mermaid overflow-x-auto rounded-lg bg-white p-4 dark:bg-gray-900']) }}
>
    <pre class="text-xs text-gray-500 dark:text-gray-400">{{ $source }}</pre>
</div>
