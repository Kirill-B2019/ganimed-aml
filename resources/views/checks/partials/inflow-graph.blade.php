{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@php
    $unique = [];
    foreach ($inflows as $row) {
        $unique[$row['from']] = ($unique[$row['from']] ?? 0) + 1;
    }
    $peers = array_slice(array_keys($unique), 0, 8);
    $cx = 280;
    $cy = 140;
@endphp
@if (count($peers) > 0)
    <svg viewBox="0 0 560 280" class="w-full max-h-72">
        @foreach ($peers as $i => $peer)
            @php
                $angle = deg2rad(-90 + ($i * 360 / max(count($peers), 1)));
                $x = $cx + 160 * cos($angle);
                $y = $cy + 90 * sin($angle);
                $label = substr($peer, 0, 6).'…'.substr($peer, -4);
            @endphp
            <line x1="{{ $x }}" y1="{{ $y }}" x2="{{ $cx }}" y2="{{ $cy }}" stroke="#c7d2fe" stroke-width="2"/>
            <circle cx="{{ $x }}" cy="{{ $y }}" r="8" fill="#6366f1"/>
            <text x="{{ $x }}" y="{{ $y - 14 }}" text-anchor="middle" font-size="10" fill="#374151">{{ $label }}</text>
        @endforeach
        <circle cx="{{ $cx }}" cy="{{ $cy }}" r="16" fill="#111827"/>
        <text x="{{ $cx }}" y="{{ $cy + 4 }}" text-anchor="middle" font-size="9" fill="#fff">to</text>
        <text x="{{ $cx }}" y="{{ $cy + 36 }}" text-anchor="middle" font-size="10" fill="#111827">{{ substr($subject, 0, 6) }}…{{ substr($subject, -4) }}</text>
    </svg>
@endif
