{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['text'])

<button
    type="button"
    x-data="{ copied: false }"
    @click="navigator.clipboard.writeText(@js($text)).then(() => { copied = true; setTimeout(() => copied = false, 1600) })"
    {{ $attributes->merge(['class' => 'text-xs text-ink-muted hover:text-ink underline decoration-ink-line underline-offset-2']) }}
>
    <span x-show="!copied">{{ __('aml.copy') }}</span>
    <span x-show="copied" x-cloak>{{ __('aml.copied') }}</span>
</button>
