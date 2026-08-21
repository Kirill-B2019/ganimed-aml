{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@php
    $points = (int) ($row['points'] ?? 0);
@endphp
@if ($points === 100)
    {{ __('aml.verdicts.block') }} (100)
@elseif (($row['points_mode'] ?? '') === 'floor' && $points > 0)
    {{ __('aml.score_floor', ['floor' => $points]) }}
@elseif ($points > 0)
    +{{ $points }}
@else
    0
@endif
