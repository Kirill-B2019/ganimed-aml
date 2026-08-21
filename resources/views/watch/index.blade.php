{{-- | KB @CerberRus00 - Nexus Invest Team --}}
<x-app-layout :title="__('aml.watchlist')">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold tracking-tight text-ink">{{ __('aml.watchlist') }}</h1>
    </x-slot>
    <div class="py-8">
        <div class="page">
            @if (session('status'))
                <div class="ui-alert ui-alert-success mb-6">{{ session('status') }}</div>
            @endif
            <div class="ui-panel overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>{{ __('aml.subject') }}</th>
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
                                        <a class="ui-link" href="{{ route('checks.show', $item->lastCheck) }}">{{ \Illuminate\Support\Str::limit($item->subject, 36) }}</a>
                                    @else
                                        {{ $item->subject }}
                                    @endif
                                </td>
                                <td>{{ $item->interval_days }}</td>
                                <td><x-verdict-badge :verdict="$item->last_verdict" /></td>
                                <td class="text-ink-muted">{{ $item->last_run_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('watch.destroy', $item) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm text-rose-700">{{ __('aml.revoke') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-ink-muted">{{ __('aml.watch_empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pt-4">{{ $items->links() }}</div>
        </div>
    </div>
</x-app-layout>
