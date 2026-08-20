{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['title' => null])

<div {{ $attributes->merge(['class' => 'border border-slate-200 p-4']) }}>
    @if ($title)
        <h3 class="text-sm font-semibold text-slate-900">{{ $title }}</h3>
    @endif
    <div @class(['mt-2 text-sm leading-6 text-slate-700' => (bool) $title])>
        {{ $slot }}
    </div>
</div>
