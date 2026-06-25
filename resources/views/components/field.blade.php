@props([
    'label' => null,
    'help' => null,
])

{{-- A native-looking labelled field wrapper: a Filament field label, the input
     (passed as the slot), and optional hint text below. Mirrors the spacing and
     typography of a Filament schema field without a full Schema component. --}}
<div class="grid gap-y-1">
    @if (filled($label))
        <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
            {{ $label }}
        </span>
    @endif

    {{ $slot }}

    @if (filled($help))
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $help }}
        </p>
    @endif
</div>
