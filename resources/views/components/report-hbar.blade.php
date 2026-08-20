{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['label', 'value' => 0, 'max' => 100, 'tone' => 'info', 'color' => null, 'hint' => null])

@php
    $max = max(1, (float) $max);
    $width = max(0, min(100, (int) round(100 * ((float) $value) / $max)));
    $fill = $color ?: match ($tone) {
        'success' => '#059669',
        'warning' => '#d97706',
        'danger' => '#e11d48',
        default => '#4f46e5',
    };
@endphp

<div {{ $attributes->merge(['class' => 'grid grid-cols-[minmax(7.5rem,12rem)_minmax(0,1fr)_auto] items-center gap-x-3 text-sm']) }}>
    <div class="text-slate-700 leading-5">{{ $label }}</div>
    <div class="min-w-0" style="height: 10px; background: #e2e8f0;">
        @if ($width > 0)
            <div style="height: 10px; width: {{ $width }}%; background-color: {{ $fill }};"></div>
        @endif
    </div>
    <div class="font-mono text-slate-500 text-right tabular-nums whitespace-nowrap">
        {{ $value }}@if ($hint !== null && $hint !== '') · {{ $hint }}@endif
    </div>
</div>
