{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['title' => null, 'hint' => null])

<section {{ $attributes->merge(['class' => 'space-y-3']) }}>
    @if ($title)
        <h2 class="text-base font-semibold tracking-tight text-ink">{{ $title }}</h2>
    @endif
    @if ($hint)
        <p class="text-xs text-ink-muted leading-5">{{ $hint }}</p>
    @endif
    <div class="ui-panel">
        {{ $slot }}
    </div>
</section>
