{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props([
    'variant' => 'lockup',
    'inverse' => false,
])

@php
    $mark = asset('images/logo-gnd-mark.png');
    $hero = asset('images/logo-gnd.png');
    $titleClass = $inverse ? 'text-white' : 'text-ink';
    $amlClass = $inverse ? 'text-white/45' : 'text-ink-muted';
@endphp

@if ($variant === 'hero')
    <span {{ $attributes->merge(['class' => 'inline-flex flex-col items-center']) }}>
        <img src="{{ $hero }}" alt="GANIMED AML" width="144" height="144" class="h-36 w-36 object-contain">
        <span class="mt-2 text-[11px] font-semibold tracking-[0.34em] text-ink-muted">AML</span>
    </span>
@elseif ($variant === 'mark')
    <img src="{{ $mark }}" alt="GANIMED AML" width="36" height="36" {{ $attributes->merge(['class' => 'h-9 w-9 object-cover']) }}>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
        <img src="{{ $mark }}" alt="" width="36" height="36" class="h-9 w-9 object-cover">
        <span class="leading-tight">
            <span class="block text-[13px] font-semibold tracking-[0.14em] {{ $titleClass }}">GANIMED</span>
            <span class="block text-[10px] font-semibold tracking-[0.28em] {{ $amlClass }}">AML</span>
        </span>
    </span>
@endif
