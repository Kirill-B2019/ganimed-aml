{{-- | KB @CerberRus00 - Nexus Invest Team --}}
<x-app-layout :title="__('aml.profile')">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-900">{{ __('aml.profile') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="page">
            <div class="max-w-3xl space-y-6">
            <div class="border border-slate-200 p-5">
                @include('profile.partials.update-profile-information-form')
            </div>
            <div class="border border-slate-200 p-5">
                @include('profile.partials.update-password-form')
            </div>
            <div class="border border-slate-200 p-5">
                @include('profile.partials.delete-user-form')
            </div>
            </div>
        </div>
    </div>
</x-app-layout>
