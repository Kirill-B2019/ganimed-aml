{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['code'])

<div {{ $attributes->merge(['class' => 'border border-slate-200 bg-slate-50']) }}>
    <div class="flex items-center justify-end border-b border-slate-200 px-3 py-1.5">
        <x-copy-button :text="$code" />
    </div>
    <pre class="overflow-x-auto p-3 text-[12px] leading-5 font-mono text-slate-800 whitespace-pre">{{ $code }}</pre>
</div>
