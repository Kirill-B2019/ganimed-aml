{{-- | KB @CerberRus00 - Nexus Invest Team --}}
<x-guest-layout :title="__('aml.log_in')">
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <h1 class="text-lg font-semibold text-slate-900">{{ __('aml.log_in') }}</h1>
        <p class="mt-1 mb-5 text-sm text-slate-500">{{ __('aml.sign_in_hint') }}</p>

        <div>
            <x-input-label for="email" :value="__('aml.email')" />
            <x-text-input id="email" class="mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('aml.password')" />
            <x-text-input id="password" class="mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="mt-4 inline-flex items-center">
            <input id="remember_me" type="checkbox" class="border-slate-300 text-indigo-600 focus:ring-indigo-600" name="remember">
            <span class="ms-2 text-sm text-slate-600">{{ __('aml.remember_me') }}</span>
        </label>

        <div class="mt-5">
            <x-primary-button class="w-full">{{ __('aml.log_in') }}</x-primary-button>
        </div>
    </form>
</x-guest-layout>
