{{-- | KB @CerberRus00 - Nexus Invest Team --}}
<x-app-layout :title="__('aml.activity')">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold tracking-tight text-ink">{{ __('aml.activity') }}</h1>
    </x-slot>
    <div class="py-8">
        <div class="page">
            <div class="ui-panel overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>{{ __('aml.created') }}</th>
                            <th>{{ __('aml.operator') }}</th>
                            <th>{{ __('aml.type') }}</th>
                            <th>{{ __('aml.subject') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="text-ink-muted whitespace-nowrap">{{ $log->created_at->format('d.m.Y H:i') }}</td>
                                <td>{{ $log->user?->name ?? '—' }}</td>
                                <td>{{ $log->label() }}
                                    @if ($log->note())
                                        <div class="text-xs text-ink-muted">{{ $log->note() }}</div>
                                    @endif
                                </td>
                                <td class="font-mono">
                                    @if ($log->check)
                                        <a class="ui-link" href="{{ route('checks.show', $log->check) }}">#{{ $log->check_id }}</a>
                                    @elseif (! empty($log->meta['subject']))
                                        {{ $log->meta['subject'] }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-ink-muted">{{ __('aml.activity_empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pt-4">{{ $logs->links() }}</div>
        </div>
    </div>
</x-app-layout>
