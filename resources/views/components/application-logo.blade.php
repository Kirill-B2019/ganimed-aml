{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['showText' => true])

@php
    $width = $showText ? '220' : '40';
    $view = $showText ? '0 0 220 40' : '0 0 40 40';
@endphp

<svg viewBox="{{ $view }}" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="GANIMED AML" {{ $attributes }}>
    <rect x="1" y="1" width="38" height="38" rx="9" fill="#312e81"/>
    <path d="M20 6.5 L31 12.2 V24.8 L20 30.5 L9 24.8 V12.2 Z" fill="#4f46e5"/>
    <path d="M20 9.2 L28.2 13.4 V23.6 L20 27.8 L11.8 23.6 V13.4 Z" fill="#1e1b4b"/>
    <path d="M24.6 14.2c-1.1-1.6-3-2.6-5.1-2.6-3.5 0-6.2 2.5-6.2 6.2 0 3.7 2.7 6.2 6.4 6.2 2.2 0 4.1-1 5.2-2.6h-2.7c-.7.7-1.6 1.1-2.6 1.1-2 0-3.4-1.5-3.4-4.7 0-3.1 1.4-4.7 3.4-4.7 1 0 1.9.4 2.6 1.1h2.4z" fill="#eef2ff"/>
    <circle cx="28.2" cy="12.4" r="2.2" fill="#c4a35a"/>
    @if ($showText)
        <text x="50" y="18" fill="currentColor" font-family="Figtree, Arial, sans-serif" font-size="14" font-weight="700" letter-spacing="1.4">GANIMED</text>
        <text x="50" y="32" fill="#818cf8" font-family="Figtree, Arial, sans-serif" font-size="9" font-weight="600" letter-spacing="3.2">AML</text>
    @endif
</svg>
