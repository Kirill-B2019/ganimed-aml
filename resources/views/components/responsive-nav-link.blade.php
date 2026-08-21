{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['active'])

@php
$classes = ($active ?? false)
    ? 'block w-full px-4 py-2.5 border-l-2 border-white text-start text-sm font-medium text-white bg-white/5'
    : 'block w-full px-4 py-2.5 border-l-2 border-transparent text-start text-sm font-medium text-white/60 hover:text-white hover:bg-white/5';
@endphp

<a {{ $attributes->merge(['class' => $classes, 'aria-current' => ($active ?? false) ? 'page' : null]) }}>
    {{ $slot }}
</a>
