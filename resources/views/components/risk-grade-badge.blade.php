{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['grade'])

@if ($grade)
    <span
        class="inline-flex items-center px-2 py-0.5 text-[11px] font-medium tracking-[0.06em] uppercase ring-1 ring-inset"
        style="background-color: {{ $grade->fill() }}; color: {{ $grade->swatch() }}; box-shadow: inset 0 0 0 1px {{ $grade->swatch() }}33;"
    >
        {{ $grade->label() }}
    </span>
@endif
