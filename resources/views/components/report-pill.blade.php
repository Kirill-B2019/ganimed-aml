{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['tone' => 'neutral'])

@php
    $classes = match ($tone) {
        'success' => 'bg-emerald-50 text-emerald-900 ring-emerald-200',
        'caution' => 'bg-yellow-50 text-yellow-900 ring-yellow-200',
        'warning' => 'bg-amber-50 text-amber-950 ring-amber-200',
        'severe' => 'bg-orange-50 text-orange-950 ring-orange-200',
        'danger' => 'bg-rose-50 text-rose-900 ring-rose-200',
        default => 'bg-ink-paper text-ink ring-ink-line',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2 py-0.5 text-[11px] font-medium tracking-[0.04em] ring-1 ring-inset '.$classes]) }}>
    {{ $slot }}
</span>
