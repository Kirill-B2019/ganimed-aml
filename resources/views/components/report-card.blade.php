{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['title' => null])

<div {{ $attributes->merge(['class' => 'border border-ink-line bg-white p-4']) }}>
    @if ($title)
        <h3 class="text-sm font-semibold text-ink">{{ $title }}</h3>
    @endif
    <div @class(['mt-2 text-sm leading-6 text-ink/80' => (bool) $title])>
        {{ $slot }}
    </div>
</div>
