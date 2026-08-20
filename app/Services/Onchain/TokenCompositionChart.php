<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Services\Onchain;

use App\Models\Check;

class TokenCompositionChart
{
    /** @var array<string, string> */
    private const COLORS = [
        'native' => '#0f766e',
        'canonical' => '#4f46e5',
        'lookalike' => '#d97706',
        'noise' => '#be123c',
        'ignore' => '#64748b',
    ];

    public function __construct(private AssetNarrativeService $narrative) {}

    /**
     * @return list<array{key: string, value: int, color: string}>
     */
    public function slices(Check $check): array
    {
        $onchain = $check->enrichment;
        if (! is_array($onchain) || ! empty($onchain['skipped']) || ! empty($onchain['error'])) {
            return [];
        }

        $counts = ['native' => 0, 'canonical' => 0, 'lookalike' => 0, 'noise' => 0, 'ignore' => 0];
        foreach ($onchain['balances'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = $this->narrative->classify($row, $check);
            if (! isset($counts[$key])) {
                $key = 'noise';
            }
            $counts[$key]++;
        }

        $slices = [];
        foreach ($counts as $key => $value) {
            if ($value < 1) {
                continue;
            }
            $slices[] = [
                'key' => $key,
                'value' => $value,
                'color' => self::COLORS[$key],
            ];
        }

        return $slices;
    }

    /**
     * @param  list<array{key: string, value: int, color: string}>  $slices
     */
    public function svg(array $slices, int $size = 260): string
    {
        if ($slices === []) {
            return '';
        }

        $total = array_sum(array_column($slices, 'value'));
        if ($total <= 0) {
            return '';
        }

        $cx = $size / 2;
        $cy = $size / 2;
        $radius = $size * 0.36;
        $paths = '';

        if (count($slices) === 1) {
            $paths = sprintf(
                '<circle cx="%s" cy="%s" r="%s" fill="%s"/>',
                $cx,
                $cy,
                $radius,
                $slices[0]['color']
            );
        } else {
            $angle = -M_PI / 2;
            foreach ($slices as $slice) {
                $next = $angle + (2 * M_PI * ($slice['value'] / $total));
                $paths .= '<path d="'.$this->slicePath($cx, $cy, $radius, $angle, $next).'" fill="'.$slice['color'].'"/>';
                $angle = $next;
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$size.' '.$size.'" class="w-full h-auto" role="img">'
            .$paths
            .'</svg>';
    }

    private function slicePath(float $cx, float $cy, float $radius, float $start, float $end): string
    {
        $x1 = $cx + $radius * cos($start);
        $y1 = $cy + $radius * sin($start);
        $x2 = $cx + $radius * cos($end);
        $y2 = $cy + $radius * sin($end);
        $large = ($end - $start) > M_PI ? 1 : 0;

        return sprintf(
            'M %s %s L %s %s A %s %s 0 %d 1 %s %s Z',
            $cx,
            $cy,
            $x1,
            $y1,
            $radius,
            $radius,
            $large,
            $x2,
            $y2
        );
    }
}
