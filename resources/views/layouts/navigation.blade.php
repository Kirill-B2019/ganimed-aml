{{-- | KB @CerberRus00 - Nexus Invest Team --}}
<nav x-data="{ open: false }" class="nav-ink sticky top-0 z-30 bg-ink">
    <div class="page">
        <div class="flex justify-between h-14">
            <div class="flex min-w-0">
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center" aria-label="GANIMED AML">
                        <x-application-logo inverse />
                    </a>
                </div>
                <div class="hidden sm:flex sm:items-stretch sm:ms-8 gap-6">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('aml.dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('checks.index')" :active="request()->routeIs('checks.index')">
                        {{ __('aml.history') }}
                    </x-nav-link>
                    <x-nav-link :href="route('tokens.index')" :active="request()->routeIs('tokens.*')">
                        {{ __('aml.api') }}
                    </x-nav-link>
                    @if (auth()->user()->is_admin)
                        <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">
                            {{ __('aml.users') }}
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-3">
                <a href="{{ route('checks.create') }}" class="inline-flex items-center px-3 py-1.5 bg-white text-sm font-medium text-ink hover:bg-ink-paper {{ request()->routeIs('checks.create') ? 'ring-1 ring-white/40' : '' }}">
                    {{ __('aml.new_check') }}
                </a>
                <div class="locale-switch" role="group" aria-label="{{ __('aml.language') }}">
                    <a href="{{ route('locale.switch', 'ru') }}" @aria-current="app()->getLocale() === 'ru' ? 'true' : 'false'">RU</a>
                    <a href="{{ route('locale.switch', 'en') }}" @aria-current="app()->getLocale() === 'en' ? 'true' : 'false'">EN</a>
                </div>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button type="button" class="inline-flex items-center px-2 py-1.5 text-sm text-white/75 hover:text-white">
                            <span class="max-w-[9rem] truncate">{{ Auth::user()->name }}</span>
                            <svg class="ms-1 h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">{{ __('aml.profile') }}</x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                {{ __('aml.log_out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center gap-2 sm:hidden">
                <a href="{{ route('checks.create') }}" class="inline-flex items-center px-2.5 py-1 bg-white text-xs font-medium text-ink">{{ __('aml.new_check') }}</a>
                <button type="button" @click="open = ! open" class="inline-flex items-center justify-center p-2 text-white/70 hover:text-white" :aria-expanded="open.toString()">
                    <span class="sr-only">Menu</span>
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t border-white/10 bg-ink-soft">
        <div class="py-2">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">{{ __('aml.dashboard') }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('checks.index')" :active="request()->routeIs('checks.index')">{{ __('aml.history') }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('tokens.index')" :active="request()->routeIs('tokens.*')">{{ __('aml.api') }}</x-responsive-nav-link>
            @if (auth()->user()->is_admin)
                <x-responsive-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">{{ __('aml.users') }}</x-responsive-nav-link>
            @endif
            <div class="px-3 py-2 flex items-center justify-between">
                <div class="locale-switch">
                    <a href="{{ route('locale.switch', 'ru') }}" @aria-current="app()->getLocale() === 'ru' ? 'true' : 'false'">RU</a>
                    <a href="{{ route('locale.switch', 'en') }}" @aria-current="app()->getLocale() === 'en' ? 'true' : 'false'">EN</a>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-white/70 hover:text-white">{{ __('aml.log_out') }}</button>
                </form>
            </div>
        </div>
    </div>
</nav>
