{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['code'])

<div {{ $attributes->merge(['class' => 'border border-ink-line bg-ink-paper']) }}>
    <div class="flex items-center justify-end border-b border-ink-line px-3 py-1.5">
        <x-copy-button :text="$code" />
    </div>
    <pre class="overflow-x-auto p-3 text-[12px] leading-5 font-mono text-ink whitespace-pre">{{ $code }}</pre>
</div>
