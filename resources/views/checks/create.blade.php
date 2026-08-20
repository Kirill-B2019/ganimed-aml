{{-- | KB @CerberRus00 - Nexus Invest Team --}}
<x-app-layout :title="__('aml.new_check')">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-900">{{ __('aml.new_check') }}</h1>
    </x-slot>

    @php
        $tabs = [
            'address' => ['label' => 'aml.tab_address', 'hint' => 'aml.tab_hint_address'],
            'token' => ['label' => 'aml.tab_token', 'hint' => 'aml.tab_hint_token'],
            'phishing' => ['label' => 'aml.tab_phishing', 'hint' => 'aml.tab_hint_phishing'],
            'dapp' => ['label' => 'aml.tab_dapp', 'hint' => 'aml.tab_hint_dapp'],
            'scan' => ['label' => 'aml.tab_scan', 'hint' => 'aml.tab_hint_scan'],
        ];
    @endphp

    <div class="py-8">
        <div class="page">
            <div class="max-w-3xl">
            <div class="flex border-b border-slate-200 overflow-x-auto" role="tablist">
                @foreach ($tabs as $key => $meta)
                    <a href="{{ route('checks.create', ['tab' => $key]) }}"
                       role="tab"
                       aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                       class="px-4 py-3 text-sm whitespace-nowrap {{ $tab === $key ? 'border-b-2 border-indigo-600 text-indigo-700 font-medium' : 'text-slate-500 hover:text-slate-800' }}">
                        {{ __($meta['label']) }}
                    </a>
                @endforeach
            </div>

            <p class="mt-4 text-sm leading-6 text-slate-600">{{ __($tabs[$tab]['hint'] ?? $tabs['address']['hint']) }}</p>

            <div class="mt-5 max-w-xl">
                @if ($tab === 'address')
                    <form method="POST" action="{{ route('checks.address') }}" class="space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="address" :value="__('aml.address')" />
                            <x-text-input id="address" name="address" class="mt-1 block w-full font-mono" :value="old('address')" :placeholder="__('aml.address_placeholder')" required autofocus autocomplete="off" spellcheck="false" />
                            <x-input-error class="mt-2" :messages="$errors->get('address')" />
                        </div>
                        <div>
                            <x-input-label :value="__('aml.chain')" />
                            <input type="hidden" name="chain_id" value="tron">
                            <p class="mt-1 text-sm text-slate-800">Tron</p>
                            <p class="mt-1 text-xs text-slate-500">{{ __('aml.chain_tron_only') }}</p>
                        </div>
                        <x-primary-button>{{ __('aml.run_check') }}</x-primary-button>
                    </form>
                @elseif ($tab === 'token')
                    <form method="POST" action="{{ route('checks.token') }}" class="space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="contract" :value="__('aml.contract')" />
                            <x-text-input id="contract" name="contract" class="mt-1 block w-full font-mono" :value="old('contract')" :placeholder="__('aml.contract_placeholder')" required autofocus autocomplete="off" spellcheck="false" />
                            <x-input-error class="mt-2" :messages="$errors->get('contract')" />
                        </div>
                        <div>
                            <x-input-label :value="__('aml.chain')" />
                            <input type="hidden" name="chain_id" value="tron">
                            <p class="mt-1 text-sm text-slate-800">Tron</p>
                            <p class="mt-1 text-xs text-slate-500">{{ __('aml.chain_tron_only') }}</p>
                        </div>
                        <x-primary-button>{{ __('aml.run_check') }}</x-primary-button>
                    </form>
                @elseif (in_array($tab, ['phishing', 'dapp'], true))
                    <form method="POST" action="{{ $tab === 'phishing' ? route('checks.phishing') : route('checks.dapp') }}" class="space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="url" :value="__('aml.url')" />
                            <x-text-input id="url" name="url" class="mt-1 block w-full" :value="old('url')" :placeholder="__('aml.url_placeholder')" required autofocus autocomplete="off" spellcheck="false" />
                            <x-input-error class="mt-2" :messages="$errors->get('url')" />
                        </div>
                        <x-primary-button>{{ __('aml.run_check') }}</x-primary-button>
                    </form>
                @else
                    <form method="POST" action="{{ route('checks.scan') }}" class="space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="address" :value="__('aml.address')" />
                            <x-text-input id="address" name="address" class="mt-1 block w-full font-mono" :value="old('address')" :placeholder="__('aml.address_placeholder')" required autofocus autocomplete="off" spellcheck="false" />
                            <x-input-error class="mt-2" :messages="$errors->get('address')" />
                        </div>
                        <div>
                            <x-input-label :value="__('aml.chain')" />
                            <input type="hidden" name="chain_id" value="tron">
                            <p class="mt-1 text-sm text-slate-800">Tron</p>
                            <p class="mt-1 text-xs text-slate-500">{{ __('aml.chain_tron_only') }}</p>
                        </div>
                        <x-primary-button>{{ __('aml.run_scan') }}</x-primary-button>
                    </form>
                @endif
            </div>
            </div>
        </div>
    </div>
</x-app-layout>
