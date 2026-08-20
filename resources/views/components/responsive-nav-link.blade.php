{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['active'])

@php
$classes = ($active ?? false)
    ? 'block w-full px-4 py-2.5 border-l-2 border-indigo-600 text-start text-sm font-medium text-slate-900 bg-indigo-50'
    : 'block w-full px-4 py-2.5 border-l-2 border-transparent text-start text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-50';
@endphp

<a {{ $attributes->merge(['class' => $classes, 'aria-current' => ($active ?? false) ? 'page' : null]) }}>
    {{ $slot }}
</a>
