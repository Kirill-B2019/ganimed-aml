{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['verdict'])

@if ($verdict)
    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium {{ $verdict->css() }}">
        {{ $verdict->label() }}
    </span>
@else
    <span class="text-slate-400">—</span>
@endif
