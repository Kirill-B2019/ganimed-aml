{{-- | KB @CerberRus00 - Nexus Invest Team --}}
<x-app-layout :title="__('aml.history')">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold tracking-tight text-ink">{{ __('aml.history') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="page space-y-6">
            @if (session('status'))
                <div class="ui-alert ui-alert-success">{{ session('status') }}</div>
            @endif

            <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 items-end" method="GET" aria-label="{{ __('aml.filters') }}">
                <div class="sm:col-span-2">
                    <x-input-label for="q" :value="__('aml.search')" />
                    <x-text-input id="q" name="q" :value="request('q')" :placeholder="__('aml.search_placeholder')" class="mt-1 w-full" />
                </div>
                <div>
                    <x-input-label for="type" :value="__('aml.type')" />
                    <select id="type" name="type" class="ui-select mt-1 w-full">
                        <option value="">{{ __('aml.all_types') }}</option>
                        @foreach (\App\Enums\CheckType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="verdict" :value="__('aml.verdict')" />
                    <select id="verdict" name="verdict" class="ui-select mt-1 w-full">
                        <option value="">{{ __('aml.all_verdicts') }}</option>
                        @foreach (\App\Enums\CheckVerdict::cases() as $verdict)
                            <option value="{{ $verdict->value }}" @selected(request('verdict') === $verdict->value)>{{ $verdict->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="status" :value="__('aml.status')" />
                    <select id="status" name="status" class="ui-select mt-1 w-full">
                        <option value="">{{ __('aml.all_statuses') }}</option>
                        @foreach (\App\Enums\CheckStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="from" :value="__('aml.date_from')" />
                    <x-text-input id="from" name="from" type="date" class="mt-1" :value="request('from')" />
                </div>
                <div>
                    <x-input-label for="to" :value="__('aml.date_to')" />
                    <x-text-input id="to" name="to" type="date" class="mt-1" :value="request('to')" />
                </div>
                <div class="flex gap-2 sm:col-span-2 lg:col-span-4">
                    <x-primary-button>{{ __('aml.filter') }}</x-primary-button>
                    <x-secondary-button :href="route('checks.index')">{{ __('aml.reset_filters') }}</x-secondary-button>
                    <x-secondary-button :href="route('checks.export', request()->query())">{{ __('aml.export_csv') }}</x-secondary-button>
                </div>
            </form>

            <div class="ui-panel overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>{{ __('aml.subject') }}</th>
                            <th>{{ __('aml.type') }}</th>
                            <th>{{ __('aml.status') }}</th>
                            <th>{{ __('aml.verdict') }}</th>
                            <th>{{ __('aml.score') }}</th>
                            <th>{{ __('aml.operator') }}</th>
                            <th>{{ __('aml.created') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($checks as $check)
                            <tr>
                                <td class="font-mono">
                                    <a class="ui-link" href="{{ route('checks.show', $check) }}">{{ \Illuminate\Support\Str::limit($check->subject, 36) }}</a>
                                </td>
                                <td>{{ $check->type->label() }}</td>
                                <td>{{ $check->status->label() }}</td>
                                <td><x-verdict-badge :verdict="$check->verdict" /></td>
                                <td class="font-mono text-ink-muted tabular-nums">{{ $check->risk_score ?? '—' }}</td>
                                <td class="text-ink-muted">{{ $check->user?->name ?? '—' }}</td>
                                <td class="text-ink-muted whitespace-nowrap">{{ $check->created_at?->format('d.m.Y H:i') }}</td>
                                <td>
                                    <div class="flex flex-wrap items-center gap-3">
                                        @if ($check->isCompleted())
                                            <form method="POST" action="{{ route('checks.rerun', $check) }}">
                                                @csrf
                                                <button type="submit" class="text-sm ui-link">{{ __('aml.rerun') }}</button>
                                            </form>
                                        @endif
                                        @if (auth()->user()->is_admin)
                                            <form method="POST" action="{{ route('checks.destroy', $check) }}" onsubmit="return confirm(@js(__('aml.delete_check_confirm')))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm text-rose-800 hover:underline">{{ __('aml.delete_check') }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-10 text-center">
                                    <p class="text-ink-muted">{{ __('aml.no_checks') }}</p>
                                    <a href="{{ route('checks.create') }}" class="mt-3 inline-flex items-center px-4 py-2 bg-ink text-sm font-medium text-white hover:bg-ink-soft">{{ __('aml.start_first') }}</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $checks->links() }}</div>
        </div>
    </div>
</x-app-layout>
