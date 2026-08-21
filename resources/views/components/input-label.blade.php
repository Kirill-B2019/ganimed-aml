{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-ink']) }}>
    {{ $value ?? $slot }}
</label>
