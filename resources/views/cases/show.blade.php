{{-- | KB @CerberRus00 - Nexus Invest Team --}}
<x-app-layout :title="$case->name">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold tracking-tight text-ink">{{ $case->name }}</h1>
        @if ($case->note)
            <p class="mt-1 text-sm text-ink-muted">{{ $case->note }}</p>
        @endif
    </x-slot>
    <div class="py-8">
        <div class="page">
            <div class="ui-panel overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>{{ __('aml.subject') }}</th>
                            <th>{{ __('aml.type') }}</th>
                            <th>{{ __('aml.verdict') }}</th>
                            <th>{{ __('aml.created') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($case->checks as $check)
                            <tr>
                                <td class="font-mono"><a class="ui-link" href="{{ route('checks.show', $check) }}">{{ \Illuminate\Support\Str::limit($check->subject, 36) }}</a></td>
                                <td>{{ $check->type->label() }}</td>
                                <td><x-verdict-badge :verdict="$check->verdict" /></td>
                                <td class="text-ink-muted">{{ $check->created_at->format('d.m.Y H:i') }}</td>
                                <td>
                                    @if ($check->isCompleted())
                                        <a class="ui-link text-sm" href="{{ route('checks.pdf', [$check, 'variant' => 'file']) }}">{{ __('aml.pdf_file') }}</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-ink-muted">{{ __('aml.no_checks') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
