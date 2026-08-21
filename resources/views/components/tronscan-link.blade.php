{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['address', 'short' => false])
@php
    $raw = (string) $address;
    $href = \App\Support\TronAddress::explorerUrl($raw);
    $label = $short ? \App\Support\TronAddress::short($raw) : $raw;
@endphp
@if ($href)
    <a
        href="{{ $href }}"
        target="_blank"
        rel="noopener noreferrer"
        title="{{ $raw }}"
        {{ $attributes->merge(['class' => 'font-mono text-[11px] break-all text-ink underline decoration-ink-line hover:text-ink hover:decoration-ink']) }}
    >{{ $label }}</a>
@else
    <span {{ $attributes->merge(['class' => 'font-mono text-[11px] break-all text-ink-muted']) }}>{{ $label }}</span>
@endif
