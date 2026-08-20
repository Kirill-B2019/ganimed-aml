{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['active'])

@php
$classes = ($active ?? false)
    ? 'inline-flex items-center border-b-2 border-indigo-600 text-sm font-medium text-slate-900'
    : 'inline-flex items-center border-b-2 border-transparent text-sm font-medium text-slate-500 hover:text-slate-800 hover:border-slate-300';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if ($active ?? false) aria-current="page" @endif>
    {{ $slot }}
</a>
