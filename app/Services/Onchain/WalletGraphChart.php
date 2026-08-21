<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Services\Onchain;

class WalletGraphChart
{
    /** @var array<string, string> */
    private const COLORS = [
        'subject' => '#111827',
        'eoa' => '#6366f1',
        'contract' => '#0f766e',
        'token' => '#4f46e5',
        'spam' => '#be123c',
        'unknown' => '#64748b',
    ];

    /**
     * @param  array<string, mixed>  $graph
     */
    public function svg(array $graph, int $width = 560, int $height = 320): string
    {
        $nodes = is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [];
        if ($nodes === []) {
            return '';
        }

        $cx = $width / 2;
        $cy = $height / 2;
        $hop1 = [];
        $hop2 = [];
        $subject = null;
        foreach ($nodes as $node) {
            if (! is_array($node) || empty($node['id'])) {
                continue;
            }
            $hop = (int) ($node['hop'] ?? 1);
            if ($hop === 0 || ($node['kind'] ?? '') === 'subject') {
                $subject = $node;
                continue;
            }
            if ($hop >= 2) {
                $hop2[] = $node;
            } else {
                $hop1[] = $node;
            }
        }

        $placed = [];
        $markup = '';
        foreach ($hop1 as $i => $node) {
            $pos = $this->ringPoint($cx, $cy, 118, 70, $i, max(count($hop1), 1), -90);
            $placed[(string) $node['id']] = $pos;
            $markup .= $this->edge($pos[0], $pos[1], $cx, $cy, '#c7d2fe');
        }
        foreach ($hop2 as $i => $node) {
            $pos = $this->ringPoint($cx, $cy, 200, 118, $i, max(count($hop2), 1), -75);
            $placed[(string) $node['id']] = $pos;
            $nearest = $this->nearestHop1($pos, $placed, $hop1);
            $markup .= $this->edge($pos[0], $pos[1], $nearest[0], $nearest[1], '#e2e8f0');
        }

        foreach (array_merge($hop2, $hop1) as $node) {
            $pos = $placed[(string) $node['id']] ?? [$cx, $cy];
            $kind = (string) ($node['kind'] ?? 'unknown');
            $color = self::COLORS[$kind] ?? self::COLORS['unknown'];
            $label = $this->shortId((string) $node['id']);
            $r = ((int) ($node['hop'] ?? 1)) >= 2 ? 7 : 9;
            $markup .= sprintf(
                '<circle cx="%s" cy="%s" r="%s" fill="%s"/><text x="%s" y="%s" text-anchor="middle" font-size="9" fill="#374151">%s</text>',
                $pos[0],
                $pos[1],
                $r,
                $color,
                $pos[0],
                $pos[1] - $r - 6,
                htmlspecialchars($label, ENT_QUOTES | ENT_XML1, 'UTF-8')
            );
        }

        $subjectId = (string) ($subject['id'] ?? '');
        $markup .= sprintf(
            '<circle cx="%s" cy="%s" r="16" fill="%s"/><text x="%s" y="%s" text-anchor="middle" font-size="9" fill="#fff">src</text>',
            $cx,
            $cy,
            self::COLORS['subject'],
            $cx,
            $cy + 3
        );
        if ($subjectId !== '') {
            $markup .= sprintf(
                '<text x="%s" y="%s" text-anchor="middle" font-size="10" fill="#111827">%s</text>',
                $cx,
                $cy + 36,
                htmlspecialchars($this->shortId($subjectId), ENT_QUOTES | ENT_XML1, 'UTF-8')
            );
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' '.$height.'" class="w-full h-auto" role="img">'
            .$markup
            .'</svg>';
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function ringPoint(float $cx, float $cy, float $rx, float $ry, int $i, int $n, float $startDeg): array
    {
        $angle = deg2rad($startDeg + ($i * 360 / $n));

        return [$cx + $rx * cos($angle), $cy + $ry * sin($angle)];
    }

    /**
     * @param  array{0: float, 1: float}  $pos
     * @param  array<string, array{0: float, 1: float}>  $placed
     * @param  list<array<string, mixed>>  $hop1
     * @return array{0: float, 1: float}
     */
    private function nearestHop1(array $pos, array $placed, array $hop1): array
    {
        $best = $pos;
        $bestDist = PHP_FLOAT_MAX;
        foreach ($hop1 as $node) {
            $id = (string) ($node['id'] ?? '');
            if (! isset($placed[$id])) {
                continue;
            }
            $other = $placed[$id];
            $dist = hypot($pos[0] - $other[0], $pos[1] - $other[1]);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $other;
            }
        }

        return $best;
    }

    private function edge(float $x1, float $y1, float $x2, float $y2, string $color): string
    {
        return sprintf(
            '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="1.5"/>',
            $x1,
            $y1,
            $x2,
            $y2,
            $color
        );
    }

    private function shortId(string $id): string
    {
        if (strlen($id) < 12) {
            return $id;
        }

        return substr($id, 0, 6).'…'.substr($id, -4);
    }
}
