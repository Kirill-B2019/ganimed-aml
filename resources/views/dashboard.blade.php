{{-- | KB @CerberRus00 - Nexus Invest Team --}}
<x-app-layout :title="__('aml.dashboard')">
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">{{ __('aml.dashboard') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ __('aml.period') }}: {{ $from }} — {{ $to }}</p>
            </div>
            <a href="{{ route('checks.create') }}" class="inline-flex items-center justify-center px-4 py-2 bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-700">{{ __('aml.new_check') }}</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="page space-y-8">
            <form method="GET" class="flex flex-wrap gap-3 items-end">
                <div>
                    <x-input-label for="from" :value="__('aml.date_from')" />
                    <x-text-input id="from" name="from" type="date" class="mt-1" :value="$from" />
                </div>
                <div>
                    <x-input-label for="to" :value="__('aml.date_to')" />
                    <x-text-input id="to" name="to" type="date" class="mt-1" :value="$to" />
                </div>
                <x-primary-button>{{ __('aml.filter') }}</x-primary-button>
            </form>

            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                <x-report-stat :label="__('aml.stats_total')" :href="route('checks.index')">{{ $stats['total'] }}</x-report-stat>
                <x-report-stat :label="__('aml.stats_clear')" tone="success" :href="route('checks.index', ['verdict' => 'clear'])">{{ $stats['clear'] }}</x-report-stat>
                <x-report-stat :label="__('aml.stats_review')" tone="warning" :href="route('checks.index', ['verdict' => 'review'])">{{ $stats['review'] }}</x-report-stat>
                <x-report-stat :label="__('aml.stats_block')" tone="danger" :href="route('checks.index', ['verdict' => 'block'])">{{ $stats['block'] }}</x-report-stat>
                <x-report-stat :label="__('aml.stats_pending')" :href="route('checks.index', ['status' => 'pending'])">{{ $stats['pending'] }}</x-report-stat>
            </div>

            <x-report-section :title="__('aml.latest_checks')">
                @if ($latest->isEmpty())
                    <div class="py-8 text-center space-y-3">
                        <p class="text-slate-500 text-sm">{{ __('aml.no_checks') }}</p>
                        <p class="text-slate-500 text-sm">{{ __('aml.empty_cta') }}</p>
                        <a href="{{ route('checks.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-700">{{ __('aml.start_first') }}</a>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>{{ __('aml.subject') }}</th>
                                    <th>{{ __('aml.type') }}</th>
                                    <th>{{ __('aml.verdict') }}</th>
                                    <th>{{ __('aml.created') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($latest as $check)
                                    <tr>
                                        <td class="font-mono">
                                            <a class="text-indigo-700 hover:text-indigo-900" href="{{ route('checks.show', $check) }}">{{ \Illuminate\Support\Str::limit($check->subject, 28) }}</a>
                                        </td>
                                        <td>{{ $check->type->label() }}</td>
                                        <td><x-verdict-badge :verdict="$check->verdict" /></td>
                                        <td class="text-slate-500 whitespace-nowrap">{{ $check->created_at->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="pt-3">
                        <a href="{{ route('checks.index') }}" class="text-sm text-indigo-700 hover:text-indigo-900">{{ __('aml.view_all') }}</a>
                    </div>
                @endif
            </x-report-section>
        </div>
    </div>
</x-app-layout>
