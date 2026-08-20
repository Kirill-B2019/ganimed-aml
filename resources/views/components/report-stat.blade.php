{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['label', 'tone' => 'neutral', 'href' => null])

@php
    $valueClass = match ($tone) {
        'success' => 'text-emerald-800',
        'warning' => 'text-amber-800',
        'danger' => 'text-rose-800',
        default => 'text-slate-900',
    };
    $boxClass = match ($tone) {
        'success' => 'border-emerald-200 bg-emerald-50',
        'warning' => 'border-amber-200 bg-amber-50',
        'danger' => 'border-rose-200 bg-rose-50',
        default => 'border-slate-200 bg-white',
    };
    $classes = 'border px-4 py-3 '.$boxClass.($href ? ' block hover:border-slate-400' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        <div class="text-xl font-semibold leading-tight {{ $valueClass }}">{{ $slot }}</div>
        <div class="mt-1 text-xs text-slate-500">{{ $label }}</div>
    </a>
@else
    <div {{ $attributes->merge(['class' => $classes]) }}>
        <div class="text-xl font-semibold leading-tight {{ $valueClass }}">{{ $slot }}</div>
        <div class="mt-1 text-xs text-slate-500">{{ $label }}</div>
    </div>
@endif
