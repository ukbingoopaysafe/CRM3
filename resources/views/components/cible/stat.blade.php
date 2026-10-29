@props([
    'label',
    'value',
    'icon',
    'accent' => null,
    'chip' => null,
])

@php
    // Only the four accents the palette defines; anything else falls back to teal.
    $accent = in_array($accent, ['success', 'warning', 'danger', 'info'], true) ? $accent : null;
@endphp

<div {{ $attributes->class(['cb-stat', 'cb-stat--' . $accent => $accent !== null]) }}>
    <div class="cb-stat-top">
        <span class="cb-stat-icon">
            <x-filament::icon :icon="$icon" />
        </span>

        @if (filled($chip))
            <span class="cb-stat-chip">{{ $chip }}</span>
        @endif
    </div>

    <span class="cb-stat-value cb-num">{{ $value }}</span>
    <p class="cb-stat-label">{{ $label }}</p>
</div>
