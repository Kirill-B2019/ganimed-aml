{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['address'])
@php
    $href = \App\Support\TronAddress::explorerUrl((string) $address);
@endphp
@if ($href)
    <a
        href="{{ $href }}"
        target="_blank"
        rel="noopener noreferrer"
        {{ $attributes->merge(['class' => 'font-mono text-[11px] break-all text-ink underline decoration-ink-line hover:text-ink hover:decoration-ink']) }}
    >{{ $address }}</a>
@else
    <span {{ $attributes->merge(['class' => 'font-mono text-[11px] break-all text-ink-muted']) }}>{{ $address }}</span>
@endif
