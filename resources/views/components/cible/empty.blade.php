@props([
    'title',
    'body' => null,
    'action' => null,
    'href' => null,
])

{{-- The mark is geometry, not a drawing: a sheet, three rules and a gold seal
     inside a dashed orbit. The same shapes mark every empty surface, so an
     empty board reads as a designed state and not as missing content. --}}
<div {{ $attributes->class('cb-empty') }}>
    <svg class="cb-empty-art" viewBox="0 0 96 96" fill="none" aria-hidden="true" focusable="false">
        <circle cx="48" cy="48" r="44" stroke="var(--cb-line-teal)" stroke-width="1"
            stroke-dasharray="2 6" stroke-linecap="round" />
        <rect x="30" y="22" width="36" height="48" rx="6" fill="#ffffff"
            stroke="var(--cb-line-teal)" stroke-width="1.5" />
        <path d="M38 37h20M38 47h14M38 57h9" stroke="var(--cb-teal-600)" stroke-width="1.5"
            stroke-linecap="round" opacity="0.4" />
        <circle cx="66" cy="24" r="4.5" fill="var(--cb-gold)" opacity="0.85" />
    </svg>

    <p class="cb-empty-title">{{ $title }}</p>

    @if (filled($body))
        <p class="cb-empty-body">{{ $body }}</p>
    @endif

    @if (filled($action) && filled($href))
        <a class="cb-empty-action" href="{{ $href }}">{{ $action }}</a>
    @endif
</div>
