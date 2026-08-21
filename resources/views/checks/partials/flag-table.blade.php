{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@php
    $rows = $rows ?? [];
@endphp
@if ($rows === [])
    <p class="text-sm text-ink-muted">{{ __('aml.flags_none') }}</p>
@else
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-ink-muted">
                    <th class="pb-2 pr-4 font-medium">{{ __('aml.api_field') }}</th>
                    <th class="pb-2 pr-4 font-medium">{{ __('aml.flag_value') }}</th>
                    <th class="pb-2 pr-4 font-medium">{{ __('aml.score_points') }}</th>
                    <th class="pb-2 font-medium">{{ __('aml.meaning') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $i => $row)
                    @php
                        $hot = \App\Services\Reports\CheckReportPresenter::isHotFlag($row);
                    @endphp
                    <tr class="border-t border-ink-line {{ $hot ? 'bg-amber-50' : ($i % 2 === 1 ? 'bg-ink-paper' : '') }}">
                        <td class="py-2 pr-4 font-mono text-xs text-ink-muted">{{ $row['field'] }}</td>
                        <td @class(['py-2 pr-4 font-mono', 'font-semibold text-amber-900' => $hot])>{{ $row['value'] }}</td>
                        <td class="py-2 pr-4 font-mono text-ink">
                            @if ((int) ($row['points'] ?? 0) === 100)
                                {{ __('aml.verdicts.block') }} (100)
                            @elseif ((int) ($row['points'] ?? 0) > 0)
                                +{{ (int) $row['points'] }}
                            @else
                                0
                            @endif
                        </td>
                        <td class="py-2 text-ink">{{ $row['meaning'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
