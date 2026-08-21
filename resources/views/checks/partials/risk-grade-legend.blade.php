{{-- | KB @CerberRus00 - Nexus Invest Team --}}
@php
    $grade = $riskGrade ?? null;
    $items = $riskGradeLegend ?? [];
@endphp
@if ($grade && $items !== [])
    <div>
        <div class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ __('aml.risk_grade_legend') }}</div>
        <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-2 text-xs text-ink">
            @foreach ($items as $item)
                <li @class([
                    'inline-flex items-center gap-1.5',
                    'font-semibold' => $grade->value === $item['key'],
                ])>
                    <span class="inline-block h-2.5 w-2.5 rounded-full" style="background-color: {{ $item['swatch'] }}"></span>
                    {{ $item['label'] }}
                    <span class="text-ink-muted">{{ $item['range'] }}</span>
                </li>
            @endforeach
        </ul>
        <p class="mt-2 text-xs text-ink-muted">{{ $grade->hint() }}</p>
    </div>
@endif
