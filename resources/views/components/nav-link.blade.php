{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['active'])

@php
$classes = ($active ?? false)
    ? 'inline-flex items-center border-b-2 border-white text-sm font-medium text-white'
    : 'inline-flex items-center border-b-2 border-transparent text-sm font-medium text-white/55 hover:text-white hover:border-white/25';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if ($active ?? false) aria-current="page" @endif>
    {{ $slot }}
</a>
