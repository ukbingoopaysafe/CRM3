<link rel="stylesheet" href="{{ asset('css/cible-admin.css') }}">

@php
    $brandFont = public_path('fonts/beIN-Normal.ttf');
    $headingFont = public_path('fonts/Jost-Medium.ttf');
@endphp

@if (is_file($brandFont) || is_file($headingFont))
    <style>
        @if (is_file($brandFont))
            @@font-face {
                font-family: 'beIN Normal';
                src: url('{{ asset('fonts/beIN-Normal.ttf') }}') format('truetype');
                font-weight: 400 700;
                font-display: swap;
            }
        @endif

        @if (is_file($headingFont))
            @@font-face {
                font-family: 'Jost';
                src: url('{{ asset('fonts/Jost-Medium.ttf') }}') format('truetype');
                font-weight: 500;
                font-display: swap;
            }
        @endif
    </style>
@endif
