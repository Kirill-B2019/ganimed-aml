{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@php
    $kinds = ['eoa', 'contract', 'token', 'dust', 'spam', 'unknown'];
@endphp
@if (! empty($walletGraphPending))
    <p class="text-sm text-ink-muted">{{ __('aml.graph_pending') }}</p>
@endif
@if (! empty($walletGraphSvg))
    <div class="mt-3 overflow-x-auto">
        {!! $walletGraphSvg !!}
    </div>
    <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-muted">
        <li>{{ __('aml.graph_edge_in') }}</li>
        <li>{{ __('aml.graph_edge_out') }}</li>
        @foreach ($kinds as $kind)
            <li><span class="font-medium text-ink">{{ __('aml.graph_kind_'.$kind) }}</span></li>
        @endforeach
    </ul>
    @if (! empty($walletGraph['truncated']))
        <p class="mt-2 text-xs text-ink-muted">{{ __('aml.graph_truncated') }}</p>
    @endif
@elseif (empty($walletGraphPending))
    <p class="text-sm text-ink-muted">{{ __('aml.graph_empty') }}</p>
@endif
