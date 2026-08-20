{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-slate-700']) }}>
    {{ $value ?? $slot }}
</label>
