{{-- | KB @CerberRus00 - Nexus Invest Team --}}
<x-app-layout :title="__('aml.users')">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-900">{{ __('aml.users') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="page space-y-6">
            @if (session('status'))
                <div class="border border-emerald-200 bg-emerald-50 text-emerald-800 p-3">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('users.store') }}" class="grid md:grid-cols-2 gap-4 border border-slate-200 p-4">
                @csrf
                <div>
                    <x-input-label for="name" :value="__('aml.name')" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required />
                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                </div>
                <div>
                    <x-input-label for="email" :value="__('aml.email')" />
                    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required />
                    <x-input-error class="mt-2" :messages="$errors->get('email')" />
                </div>
                <div>
                    <x-input-label for="password" :value="__('aml.password')" />
                    <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required />
                    <x-input-error class="mt-2" :messages="$errors->get('password')" />
                </div>
                <div>
                    <x-input-label for="password_confirmation" :value="__('aml.password_confirmation')" />
                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required />
                </div>
                <label class="md:col-span-2 inline-flex items-center gap-2">
                    <input type="checkbox" name="is_admin" value="1" class="rounded border-gray-300">
                    <span>{{ __('aml.admin') }}</span>
                </label>
                <div>
                    <x-primary-button>{{ __('aml.create_user') }}</x-primary-button>
                </div>
            </form>

            <div>
                <table class="ui-table">
                    <thead>
                        <tr class="text-left text-xs text-slate-500">
                            <th class="py-2 pr-3 font-medium">{{ __('aml.name') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ __('aml.email') }}</th>
                            <th class="py-2 font-medium">{{ __('aml.admin') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $i => $user)
                            <tr class="border-t border-slate-100 {{ $i % 2 === 1 ? 'bg-slate-50' : '' }}">
                                <td class="py-2 pr-3">{{ $user->name }}</td>
                                <td class="py-2 pr-3">{{ $user->email }}</td>
                                <td class="py-2">{{ $user->is_admin ? __('aml.yes') : __('aml.no') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="pt-4">{{ $users->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
