{{-- | KB @CerberRus00 - Nexus Invest Team --}}
<x-app-layout :title="__('aml.watchlist')">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold tracking-tight text-ink">{{ __('aml.watchlist') }}</h1>
    </x-slot>
    @php
        $intervals = [1, 3, 7, 14, 30];
        $cases = $cases ?? collect();
    @endphp
    <div class="py-8">
        <div class="page space-y-6">
            @if (session('status'))
                <div class="ui-alert ui-alert-success">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('watch.store') }}" class="ui-panel grid gap-4 md:grid-cols-3">
                @csrf
                <div class="md:col-span-2">
                    <x-input-label for="subject" :value="__('aml.watch_subject')" />
                    <x-text-input id="subject" name="subject" class="mt-1 block w-full font-mono" required autocomplete="off" spellcheck="false" />
                    <x-input-error class="mt-1" :messages="$errors->get('subject')" />
                </div>
                <div>
                    <x-input-label for="interval_days" :value="__('aml.watch_interval')" />
                    <select id="interval_days" name="interval_days" class="ui-select mt-1 w-full">
                        @foreach ($intervals as $n)
                            <option value="{{ $n }}" @selected((int) old('interval_days', 7) === $n)>{{ __('aml.watch_interval_n', ['n' => $n]) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="case_id" :value="__('aml.optional_case')" />
                    <select id="case_id" name="case_id" class="ui-select mt-1 w-full">
                        <option value="">{{ __('aml.no_case') }}</option>
                        @foreach ($cases as $case)
                            <option value="{{ $case->id }}" @selected((string) old('case_id') === (string) $case->id)>{{ $case->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <x-primary-button>{{ __('aml.watch_add') }}</x-primary-button>
                </div>
            </form>

            <div class="ui-panel overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>{{ __('aml.watch_subject') }}</th>
                            <th>{{ __('aml.case') }}</th>
                            <th>{{ __('aml.watch_interval') }}</th>
                            <th>{{ __('aml.verdict') }}</th>
                            <th>{{ __('aml.watch_last') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td class="font-mono">
                                    @if ($item->lastCheck)
                                        <a class="ui-link" href="{{ route('checks.show', $item->lastCheck) }}">{{ $item->subject }}</a>
                                    @else
                                        {{ $item->subject }}
                                    @endif
                                </td>
                                <td>
                                    @if ($item->screeningCase)
                                        <a class="ui-link" href="{{ route('cases.show', $item->screeningCase) }}">{{ $item->screeningCase->name }}</a>
                                    @else
                                        <span class="text-ink-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if (Route::has('watch.update'))
                                        <form method="POST" action="{{ route('watch.update', $item) }}" class="flex items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <select name="interval_days" class="ui-select text-sm" onchange="this.form.submit()">
                                                @foreach ($intervals as $n)
                                                    <option value="{{ $n }}" @selected((int) $item->interval_days === $n)>{{ __('aml.watch_interval_n', ['n' => $n]) }}</option>
                                                @endforeach
                                                @if (! in_array((int) $item->interval_days, $intervals, true))
                                                    <option value="{{ $item->interval_days }}" selected>{{ __('aml.watch_interval_n', ['n' => $item->interval_days]) }}</option>
                                                @endif
                                            </select>
                                            <input type="hidden" name="case_id" value="{{ $item->case_id }}">
                                        </form>
                                    @else
                                        {{ __('aml.watch_interval_n', ['n' => $item->interval_days]) }}
                                    @endif
                                </td>
                                <td><x-verdict-badge :verdict="$item->last_verdict" /></td>
                                <td class="text-ink-muted whitespace-nowrap">{{ $item->last_run_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('watch.destroy', $item) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm text-rose-700">{{ __('aml.watch_remove') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-8 text-center text-ink-muted">{{ __('aml.watch_empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pt-4">{{ $items->links() }}</div>
        </div>
    </div>
</x-app-layout>
