{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['tone' => 'neutral'])

@php
    $classes = match ($tone) {
        'success' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'warning' => 'bg-amber-50 text-amber-900 ring-amber-200',
        'danger' => 'bg-rose-50 text-rose-800 ring-rose-200',
        default => 'bg-slate-100 text-slate-700 ring-slate-200',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-md px-2.5 py-1 text-xs font-medium ring-1 ring-inset '.$classes]) }}>
    {{ $slot }}
</span>
