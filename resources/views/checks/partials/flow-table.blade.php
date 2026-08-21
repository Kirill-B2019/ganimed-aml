{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@php
    $peerKey = $peerKey ?? 'from';
@endphp
<div class="mt-4 sm:hidden divide-y divide-ink-line">
    @foreach ($rows as $row)
        @php
            $rowBg = match ($row['tone'] ?? '') {
                'success' => 'bg-emerald-50',
                'warning' => 'bg-amber-50',
                'danger' => 'bg-rose-50',
                default => '',
            };
            $peer = $row[$peerKey] ?? '';
        @endphp
        <div class="py-2.5 px-1 {{ $rowBg }}">
            <x-tronscan-link :address="$peer" :short="true" />
            <p class="mt-1 text-sm">
                <span>{{ $row['symbol'] }}</span>
                <span class="ms-2 font-mono">{{ $row['amount'] }}</span>
                @if (! empty($row['tx_count']))
                    <span class="ms-2 text-xs text-ink-muted">×{{ $row['tx_count'] }}</span>
                @endif
            </p>
            @if (! empty($row['contract']))
                <div class="mt-0.5">
                    <x-tronscan-link :address="$row['contract']" :short="true" class="text-ink-muted" />
                </div>
            @endif
            @if (! empty($row['comment']))
                <p class="mt-1 text-xs text-ink-muted">{{ $row['comment'] }}</p>
            @endif
        </div>
    @endforeach
</div>
<div class="mt-4 hidden overflow-x-auto sm:block">
    <table class="min-w-full text-sm">
        <thead>
            <tr class="text-left text-xs text-ink-muted">
                <th class="pb-2 pr-3 font-medium">{{ $peerKey === 'to' ? __('aml.outflow_to') : __('aml.inflow_from') }}</th>
                <th class="pb-2 pr-3 font-medium">{{ __('aml.inflow_asset') }}</th>
                <th class="pb-2 pr-3 font-medium text-right">{{ __('aml.inflow_amount') }}</th>
                <th class="pb-2 pr-3 font-medium">{{ __('aml.inflow_count') }}</th>
                <th class="pb-2 font-medium">{{ __('aml.comment') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                @php
                    $rowBg = match ($row['tone'] ?? '') {
                        'success' => 'bg-emerald-50',
                        'warning' => 'bg-amber-50',
                        'danger' => 'bg-rose-50',
                        default => '',
                    };
                    $peer = $row[$peerKey] ?? '';
                @endphp
                <tr class="border-t border-ink-line align-top {{ $rowBg }}">
                    <td class="py-2 pr-3">
                        <x-tronscan-link :address="$peer" />
                    </td>
                    <td class="py-2 pr-3">
                        <div>{{ $row['symbol'] }}</div>
                        @if (! empty($row['contract']))
                            <x-tronscan-link :address="$row['contract']" :short="true" class="text-ink-muted" />
                        @endif
                    </td>
                    <td class="py-2 pr-3 font-mono text-right">{{ $row['amount'] }}</td>
                    <td class="py-2 pr-3">{{ $row['tx_count'] ?? '' }}</td>
                    <td class="py-2 text-ink-muted">{{ $row['comment'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
