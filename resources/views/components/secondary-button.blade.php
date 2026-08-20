{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@if ($attributes->has('href'))
    <a {{ $attributes->merge(['class' => 'inline-flex items-center justify-center px-3 py-2 border border-slate-300 bg-white text-sm font-medium text-slate-800 hover:border-slate-400 hover:bg-slate-50']) }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center px-3 py-2 border border-slate-300 bg-white text-sm font-medium text-slate-800 hover:border-slate-400 hover:bg-slate-50']) }}>
        {{ $slot }}
    </button>
@endif
