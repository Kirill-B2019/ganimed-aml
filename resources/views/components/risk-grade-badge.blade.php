{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['grade'])

@if ($grade)
    <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-medium tracking-[0.06em] uppercase {{ $grade->css() }}">
        {{ $grade->label() }}
    </span>
@endif
