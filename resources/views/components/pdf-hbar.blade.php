{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['pct' => 0, 'tone' => 'success', 'color' => null])

@php
    $pct = max(0, min(100, (int) $pct));
    $fill = $color ?: match ($tone) {
        'success' => '#059669',
        'warning' => '#d97706',
        'danger' => '#e11d48',
        default => '#0C0C0D',
    };
    $rest = 100 - $pct;
@endphp

<table class="hbar" cellpadding="0" cellspacing="0">
    <tr>
        @if ($pct > 0)
            <td class="hbar-fill" style="width: {{ $pct }}%; background-color: {{ $fill }};">&nbsp;</td>
        @endif
        @if ($rest > 0)
            <td class="hbar-track" style="width: {{ $rest }}%;">&nbsp;</td>
        @endif
    </tr>
</table>
