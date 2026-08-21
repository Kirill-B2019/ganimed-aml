{{-- | KB @CerberRus00 - Nexus Invest Team --}}
<x-app-layout :title="__('aml.new_check')">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold tracking-tight text-ink">{{ __('aml.new_check') }}</h1>
    </x-slot>

    @php
        $tabs = [
            'address' => ['label' => 'aml.tab_address', 'hint' => 'aml.tab_hint_address'],
            'token' => ['label' => 'aml.tab_token', 'hint' => 'aml.tab_hint_token'],
            'phishing' => ['label' => 'aml.tab_phishing', 'hint' => 'aml.tab_hint_phishing'],
            'dapp' => ['label' => 'aml.tab_dapp', 'hint' => 'aml.tab_hint_dapp'],
        ];
        $cases = $cases ?? collect();
    @endphp

    <div class="py-8">
        <div class="page">
            <div class="max-w-3xl">
            <div class="flex border-b border-ink-line overflow-x-auto" role="tablist">
                @foreach ($tabs as $key => $meta)
                    <a href="{{ route('checks.create', ['tab' => $key]) }}"
                       role="tab"
                       aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                       class="px-4 py-3 text-sm whitespace-nowrap {{ $tab === $key ? 'border-b-2 border-ink text-ink font-medium' : 'text-ink-muted hover:text-ink' }}">
                        {{ __($meta['label']) }}
                    </a>
                @endforeach
            </div>

            <p class="mt-4 text-sm leading-6 text-ink-muted">{{ __($tabs[$tab]['hint'] ?? $tabs['address']['hint']) }}</p>

            <div class="mt-5 max-w-xl ui-panel">
                @if ($tab === 'address')
                    <form
                        method="POST"
                        action="{{ route('checks.address') }}"
                        class="space-y-4"
                        data-processing
                        x-data="{ deep: false }"
                        :action="deep ? @js(route('checks.scan')) : @js(route('checks.address'))"
                    >
                        @csrf
                        <div>
                            <x-input-label for="address" :value="__('aml.address')" />
                            <x-text-input id="address" name="address" class="mt-1 block w-full font-mono" :value="old('address')" :placeholder="__('aml.address_placeholder')" required autofocus autocomplete="off" spellcheck="false" />
                            <x-input-error class="mt-2" :messages="$errors->get('address')" />
                        </div>
                        <div>
                            <x-input-label for="addresses" :value="__('aml.batch_addresses')" />
                            <textarea id="addresses" name="addresses" rows="4" class="mt-1 block w-full font-mono text-sm border-ink-line" placeholder="T…">{{ old('addresses') }}</textarea>
                            <p class="mt-1 text-xs text-ink-muted">{{ __('aml.batch_hint') }}</p>
                        </div>
                        <div>
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" name="deep" value="1" class="rounded border-ink-line" x-model="deep">
                                <span>{{ __('aml.deep_window') }}</span>
                            </label>
                            <p class="mt-1 text-xs text-ink-muted">{{ __('aml.deep_window_hint') }}</p>
                        </div>
                        @include('checks.partials.case-select')
                        <div>
                            <x-input-label :value="__('aml.chain')" />
                            <input type="hidden" name="chain_id" value="tron">
                            <p class="mt-1 text-sm text-ink">Tron</p>
                            <p class="mt-1 text-xs text-ink-muted">{{ __('aml.chain_tron_only') }}</p>
                        </div>
                        <x-primary-button>{{ __('aml.run_check') }}</x-primary-button>
                    </form>
                @elseif ($tab === 'token')
                    <form method="POST" action="{{ route('checks.token') }}" class="space-y-4" data-processing>
                        @csrf
                        <div>
                            <x-input-label for="contract" :value="__('aml.contract')" />
                            <x-text-input id="contract" name="contract" class="mt-1 block w-full font-mono" :value="old('contract')" :placeholder="__('aml.contract_placeholder')" required autofocus autocomplete="off" spellcheck="false" />
                            <x-input-error class="mt-2" :messages="$errors->get('contract')" />
                        </div>
                        @include('checks.partials.case-select')
                        <div>
                            <x-input-label :value="__('aml.chain')" />
                            <input type="hidden" name="chain_id" value="tron">
                            <p class="mt-1 text-sm text-ink">Tron</p>
                            <p class="mt-1 text-xs text-ink-muted">{{ __('aml.chain_tron_only') }}</p>
                        </div>
                        <x-primary-button>{{ __('aml.run_check') }}</x-primary-button>
                    </form>
                @else
                    <form method="POST" action="{{ $tab === 'phishing' ? route('checks.phishing') : route('checks.dapp') }}" class="space-y-4" data-processing>
                        @csrf
                        <div>
                            <x-input-label for="url" :value="__('aml.url')" />
                            <x-text-input id="url" name="url" class="mt-1 block w-full" :value="old('url')" :placeholder="__('aml.url_placeholder')" required autofocus autocomplete="off" spellcheck="false" />
                            <x-input-error class="mt-2" :messages="$errors->get('url')" />
                        </div>
                        @include('checks.partials.case-select')
                        <x-primary-button>{{ __('aml.run_check') }}</x-primary-button>
                    </form>
                @endif
            </div>
            </div>
        </div>
    </div>
</x-app-layout>
