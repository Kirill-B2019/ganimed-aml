{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['label', 'tone' => 'neutral', 'href' => null])

@php
    $valueClass = match ($tone) {
        'success' => 'text-emerald-800',
        'caution' => 'text-yellow-800',
        'warning' => 'text-amber-800',
        'severe' => 'text-orange-800',
        'danger' => 'text-rose-800',
        default => 'text-ink',
    };
    $stripe = match ($tone) {
        'success' => 'border-l-emerald-700',
        'caution' => 'border-l-yellow-600',
        'warning' => 'border-l-amber-600',
        'severe' => 'border-l-orange-600',
        'danger' => 'border-l-rose-700',
        default => 'border-l-ink',
    };
    $classes = 'border border-ink-line border-l-[3px] bg-white px-4 py-3 '.$stripe.($href ? ' block hover:bg-ink-paper' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        <div class="text-xl font-semibold leading-tight tabular-nums {{ $valueClass }}">{{ $slot }}</div>
        <div class="mt-1 text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ $label }}</div>
    </a>
@else
    <div {{ $attributes->merge(['class' => $classes]) }}>
        <div class="text-xl font-semibold leading-tight tabular-nums {{ $valueClass }}">{{ $slot }}</div>
        <div class="mt-1 text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ $label }}</div>
    </div>
@endif
