{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['verdict'])

@if ($verdict)
    <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-medium tracking-[0.06em] uppercase {{ $verdict->css() }}">
        {{ $verdict->label() }}
    </span>
@else
    <span class="text-ink-faint">—</span>
@endif
