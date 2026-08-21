{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@php
    $peers = $walletGraphPeers ?? [];
    $legend = $walletGraphLegend ?? [];
@endphp
@if (! empty($walletGraphPending))
    <p class="text-sm text-ink-muted">{{ __('aml.graph_pending') }}</p>
@endif
@if (! empty($walletGraphSvg))
    <div class="mt-3 overflow-x-auto">
        <div class="min-w-[40rem] max-w-3xl mx-auto sm:min-w-0">
            {!! $walletGraphSvg !!}
        </div>
    </div>
    @if ($legend !== [])
        <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-xs text-ink">
            @foreach ($legend as $kind => $color)
                <li class="inline-flex items-center gap-1.5">
                    <span class="inline-block h-2.5 w-2.5 rounded-full" style="background-color: {{ $color }}"></span>
                    {{ __('aml.graph_kind_'.$kind) }}
                </li>
            @endforeach
        </ul>
    @endif
    @if ($peers !== [])
        <h3 class="mt-5 text-sm font-semibold text-ink">{{ __('aml.graph_peers') }}</h3>
        <div class="mt-2 sm:hidden divide-y divide-ink-line">
            @foreach ($peers as $peer)
                <div class="py-2.5">
                    <div class="flex items-start gap-2">
                        <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold {{ ($peer['tone'] ?? '') === 'unknown' ? 'text-ink' : 'text-white' }}" style="background-color: {{ $peer['color'] }}">{{ $peer['n'] }}</span>
                        <div class="min-w-0">
                            <x-tronscan-link :address="$peer['id']" :short="true" />
                            <p class="mt-1 text-xs">
                                @foreach ($peer['status'] as $status)
                                    <span class="mr-1 {{ $status === 'spam' ? 'text-rose-700' : ($status === 'dust' ? 'text-amber-700' : 'text-ink-muted') }}">{{ __('aml.graph_kind_'.$status) }}</span>
                                @endforeach
                            </p>
                            <p class="mt-0.5 text-xs text-ink-muted">
                                {{ $peer['assets'] !== '' ? $peer['assets'] : '—' }}
                                · {{ $peer['in_count'] }} / {{ $peer['out_count'] }}
                            </p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-2 hidden overflow-x-auto sm:block">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-ink-muted">
                        <th class="pb-2 pr-3 font-medium">{{ __('aml.graph_peer') }}</th>
                        <th class="pb-2 pr-3 font-medium">{{ __('aml.graph_peer_status') }}</th>
                        <th class="pb-2 pr-3 font-medium">{{ __('aml.graph_peer_assets') }}</th>
                        <th class="pb-2 font-medium">{{ __('aml.graph_edge_in') }} / {{ __('aml.graph_edge_out') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($peers as $peer)
                        <tr class="border-t border-ink-line align-top">
                            <td class="py-2 pr-3">
                                <span class="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-semibold align-middle {{ ($peer['tone'] ?? '') === 'unknown' ? 'text-ink' : 'text-white' }}" style="background-color: {{ $peer['color'] }}">{{ $peer['n'] }}</span>
                                <x-tronscan-link :address="$peer['id']" />
                            </td>
                            <td class="py-2 pr-3">
                                @foreach ($peer['status'] as $status)
                                    <span class="mr-1 text-xs {{ $status === 'spam' ? 'text-rose-700' : ($status === 'dust' ? 'text-amber-700' : 'text-ink-muted') }}">{{ __('aml.graph_kind_'.$status) }}</span>
                                @endforeach
                            </td>
                            <td class="py-2 pr-3 text-ink-muted">{{ $peer['assets'] !== '' ? $peer['assets'] : '—' }}</td>
                            <td class="py-2 font-mono text-xs text-ink-muted">{{ $peer['in_count'] }} / {{ $peer['out_count'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @if (! empty($walletGraph['truncated']))
        <p class="mt-2 text-xs text-ink-muted">{{ __('aml.graph_truncated') }}</p>
    @endif
@elseif (empty($walletGraphPending))
    <p class="text-sm text-ink-muted">{{ __('aml.graph_empty') }}</p>
@endif
