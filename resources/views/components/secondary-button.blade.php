{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@if ($attributes->has('href'))
    <a {{ $attributes->merge(['class' => 'inline-flex items-center justify-center px-3 py-2 border border-ink-line bg-white text-sm font-medium text-ink hover:border-ink hover:bg-ink-paper']) }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center px-3 py-2 border border-ink-line bg-white text-sm font-medium text-ink hover:border-ink hover:bg-ink-paper']) }}>
        {{ $slot }}
    </button>
@endif
