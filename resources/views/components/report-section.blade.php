{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['title' => null, 'hint' => null])

<section {{ $attributes->merge(['class' => 'space-y-3']) }}>
    @if ($title)
        <h2 class="text-lg font-semibold text-slate-900">{{ $title }}</h2>
    @endif
    @if ($hint)
        <p class="text-xs text-slate-500 leading-5">{{ $hint }}</p>
    @endif
    <div>
        {{ $slot }}
    </div>
</section>
