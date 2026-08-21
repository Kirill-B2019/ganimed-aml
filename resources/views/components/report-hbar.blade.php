{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['label', 'value' => 0, 'max' => 100, 'tone' => 'info', 'color' => null, 'hint' => null])

@php
    $max = max(1, (float) $max);
    $width = max(0, min(100, (int) round(100 * ((float) $value) / $max)));
    $fill = $color ?: match ($tone) {
        'success' => '#047857',
        'warning' => '#b45309',
        'danger' => '#be123c',
        default => '#0C0C0D',
    };
@endphp

<div {{ $attributes->merge(['class' => 'grid grid-cols-[minmax(7.5rem,12rem)_minmax(0,1fr)_auto] items-center gap-x-3 text-sm']) }}>
    <div class="text-ink leading-5">{{ $label }}</div>
    <div class="min-w-0 bg-ink-line" style="height: 8px;">
        @if ($width > 0)
            <div style="height: 8px; width: {{ $width }}%; background-color: {{ $fill }};"></div>
        @endif
    </div>
    <div class="font-mono text-ink-muted text-right tabular-nums whitespace-nowrap">
        {{ $value }}@if ($hint !== null && $hint !== '') · {{ $hint }}@endif
    </div>
</div>
