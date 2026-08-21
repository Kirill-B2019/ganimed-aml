{{-- | KB @CerberRus00 - Nexus Invest Team --}}
<x-app-layout :title="__('aml.cases')">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold tracking-tight text-ink">{{ __('aml.cases') }}</h1>
    </x-slot>
    <div class="py-8">
        <div class="page space-y-6">
            @if (session('status'))
                <div class="ui-alert ui-alert-success">{{ session('status') }}</div>
            @endif
            <form method="POST" action="{{ route('cases.store') }}" class="ui-panel grid gap-4 md:grid-cols-2">
                @csrf
                <div>
                    <x-input-label for="name" :value="__('aml.case_name')" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" required />
                    <x-input-error class="mt-1" :messages="$errors->get('name')" />
                </div>
                <div>
                    <x-input-label for="note" :value="__('aml.case_note')" />
                    <x-text-input id="note" name="note" class="mt-1 block w-full" />
                </div>
                <div>
                    <x-primary-button>{{ __('aml.create_case') }}</x-primary-button>
                </div>
            </form>
            <div class="ui-panel overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>{{ __('aml.case_name') }}</th>
                            <th>{{ __('aml.case_note') }}</th>
                            <th>{{ __('aml.case_checks') }}</th>
                            <th>{{ __('aml.case_watches') }}</th>
                            <th>{{ __('aml.created') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($cases as $case)
                            <tr>
                                <td><a class="ui-link" href="{{ route('cases.show', $case) }}">{{ $case->name }}</a></td>
                                <td class="text-ink-muted">{{ $case->note }}</td>
                                <td class="tabular-nums">{{ $case->checks_count }}</td>
                                <td class="tabular-nums">{{ $case->watch_items_count }}</td>
                                <td class="text-ink-muted whitespace-nowrap">{{ \App\Support\MskTime::format($case->created_at) ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-ink-muted">{{ __('aml.no_cases') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $cases->links() }}</div>
        </div>
    </div>
</x-app-layout>
