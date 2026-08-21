{{-- | KB @CerberRus00 - Nexus Invest Team --}}
<section>
    <header>
        <h2 class="text-lg font-medium text-ink">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-ink-muted">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    @if (Route::has('verification.send'))
    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>
    @endif

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-ink">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-ink-muted hover:text-ink focus:outline-none">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div>
            <x-input-label for="webhook_url" :value="__('aml.webhook_url')" />
            <x-text-input id="webhook_url" name="webhook_url" type="url" class="mt-1 block w-full" :value="old('webhook_url', $user->webhook_url)" />
            <p class="mt-1 text-xs text-ink-muted">{{ __('aml.webhook_hint') }}</p>
            <x-input-error class="mt-2" :messages="$errors->get('webhook_url')" />
        </div>
        <div>
            <x-input-label for="webhook_secret" :value="__('aml.webhook_secret')" />
            <x-text-input id="webhook_secret" name="webhook_secret" type="text" class="mt-1 block w-full font-mono" :value="old('webhook_secret', $user->webhook_secret)" />
            <x-input-error class="mt-2" :messages="$errors->get('webhook_secret')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-ink-muted"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
