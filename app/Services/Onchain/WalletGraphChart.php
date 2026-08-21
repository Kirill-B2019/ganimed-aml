<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Services\Onchain;

use App\Support\TronAddress;

class WalletGraphChart
{
    /** @var array<string, string> */
    private const COLORS = [
        'subject' => '#111827',
        'eoa' => '#6366f1',
        'contract' => '#0f766e',
        'token' => '#4f46e5',
        'spam' => '#be123c',
        'dust' => '#d97706',
        'unknown' => '#64748b',
        'in' => '#6366f1',
        'out' => '#0f766e',
        'internal' => '#94a3b8',
    ];

    /**
     * @param  array<string, mixed>  $graph
     */
    public function svg(array $graph, int $width = 640, int $height = 400): string
    {
        $nodes = is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [];
        $edges = is_array($graph['edges'] ?? null) ? $graph['edges'] : [];
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
            $placed[(string) $node['id']] = $this->ringPoint($cx, $cy, 150, 95, $i, max(count($hop1), 1), -90);
        }
        foreach ($hop2 as $i => $node) {
            $placed[(string) $node['id']] = $this->ringPoint($cx, $cy, 250, 150, $i, max(count($hop2), 1), -75);
        }
        if (is_array($subject) && ! empty($subject['id'])) {
            $placed[(string) $subject['id']] = [$cx, $cy];
        }

        foreach ($edges as $edge) {
            if (! is_array($edge)) {
                continue;
            }
            $from = (string) ($edge['from'] ?? '');
            $to = (string) ($edge['to'] ?? '');
            if (! isset($placed[$from], $placed[$to])) {
                continue;
            }
            $direction = (string) ($edge['direction'] ?? 'in');
            $hygiene = (string) ($edge['hygiene'] ?? '');
            $color = match (true) {
                $hygiene === 'spam' => self::COLORS['spam'],
                $hygiene === 'dust' => self::COLORS['dust'],
                default => self::COLORS[$direction] ?? self::COLORS['in'],
            };
            $x1 = $placed[$from][0];
            $y1 = $placed[$from][1];
            $x2 = $placed[$to][0];
            $y2 = $placed[$to][1];
            $markup .= $this->edge($x1, $y1, $x2, $y2, $color);
            $markup .= $this->edgeLabel(($x1 + $x2) / 2, ($y1 + $y2) / 2, $this->edgeCaption($edge), $color);
        }

        foreach (array_merge($hop2, $hop1) as $node) {
            $markup .= $this->nodeMarkup($node, $placed[(string) $node['id']] ?? [$cx, $cy]);
        }
        if (is_array($subject)) {
            $markup .= $this->nodeMarkup($subject, [$cx, $cy], true);
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 '.$width.' '.$height.'" class="w-full h-auto" role="img">'
            .$markup
            .'</svg>';
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array{0: float, 1: float}  $pos
     */
    private function nodeMarkup(array $node, array $pos, bool $center = false): string
    {
        $id = (string) ($node['id'] ?? '');
        $kind = (string) ($node['kind'] ?? 'unknown');
        $flags = is_array($node['flags'] ?? null) ? $node['flags'] : [];
        $fill = $this->nodeColor($kind, $flags, $center);
        $r = $center ? 18 : (((int) ($node['hop'] ?? 1)) >= 2 ? 8 : 10);
        $url = TronAddress::explorerUrl($id);
        $status = $this->statusCaption($kind, $flags);
        $flow = $this->flowCaption($node);
        $label = $this->shortId($id);
        $statusColor = in_array('spam', $flags, true)
            ? self::COLORS['spam']
            : (in_array('dust', $flags, true) ? self::COLORS['dust'] : '#374151');

        $inner = sprintf(
            '<circle cx="%s" cy="%s" r="%s" fill="%s"/>',
            $pos[0],
            $pos[1],
            $r,
            $fill
        );
        $inner .= sprintf(
            '<text x="%s" y="%s" text-anchor="middle" font-size="10" fill="#111827">%s</text>',
            $pos[0],
            $pos[1] + $r + 12,
            htmlspecialchars($label, ENT_QUOTES | ENT_XML1, 'UTF-8')
        );
        $inner .= sprintf(
            '<text x="%s" y="%s" text-anchor="middle" font-size="8" fill="%s">%s</text>',
            $pos[0],
            $pos[1] + $r + 24,
            $statusColor,
            htmlspecialchars($status, ENT_QUOTES | ENT_XML1, 'UTF-8')
        );
        if ($flow !== '') {
            $inner .= sprintf(
                '<text x="%s" y="%s" text-anchor="middle" font-size="8" fill="#6b7280">%s</text>',
                $pos[0],
                $pos[1] + $r + 35,
                htmlspecialchars($flow, ENT_QUOTES | ENT_XML1, 'UTF-8')
            );
        }

        if ($url === null) {
            return $inner;
        }

        return sprintf(
            '<a href="%s" xlink:href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
            $inner
        );
    }

    /**
     * @param  list<string>  $flags
     */
    private function nodeColor(string $kind, array $flags, bool $center): string
    {
        if ($center) {
            return self::COLORS['subject'];
        }
        if (in_array('spam', $flags, true) || $kind === 'spam') {
            return self::COLORS['spam'];
        }
        if (in_array('dust', $flags, true)) {
            return self::COLORS['dust'];
        }

        return self::COLORS[$kind] ?? self::COLORS['unknown'];
    }

    /**
     * @param  list<string>  $flags
     */
    private function statusCaption(string $kind, array $flags): string
    {
        $parts = [];
        $kindKey = in_array($kind, ['eoa', 'contract', 'token', 'spam', 'unknown'], true) ? $kind : 'unknown';
        if ($kindKey !== 'spam') {
            $parts[] = __('aml.graph_kind_'.$kindKey);
        }
        if (in_array('dust', $flags, true)) {
            $parts[] = __('aml.graph_kind_dust');
        }
        if (in_array('spam', $flags, true) || $kind === 'spam') {
            $parts[] = __('aml.graph_kind_spam');
        }

        return implode(' · ', array_unique($parts));
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function flowCaption(array $node): string
    {
        $in = (int) ($node['in_count'] ?? 0);
        $out = (int) ($node['out_count'] ?? 0);
        if ($in < 1 && $out < 1) {
            return '';
        }
        $bits = [];
        if ($in > 0) {
            $bits[] = __('aml.graph_edge_in').' '.$in;
        }
        if ($out > 0) {
            $bits[] = __('aml.graph_edge_out').' '.$out;
        }

        return implode(' · ', $bits);
    }

    /**
     * @param  array<string, mixed>  $edge
     */
    private function edgeCaption(array $edge): string
    {
        $asset = (string) ($edge['asset'] ?? '');
        $count = (int) ($edge['count'] ?? 1);
        $hygiene = (string) ($edge['hygiene'] ?? '');
        if ($hygiene === 'dust') {
            return __('aml.graph_kind_dust').' TRX';
        }
        if ($count > 1 && $asset !== '') {
            return $asset.'×'.$count;
        }

        return $asset !== '' ? $asset : (string) ($edge['direction'] ?? '');
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function ringPoint(float $cx, float $cy, float $rx, float $ry, int $i, int $n, float $startDeg): array
    {
        $angle = deg2rad($startDeg + ($i * 360 / $n));

        return [$cx + $rx * cos($angle), $cy + $ry * sin($angle)];
    }

    private function edge(float $x1, float $y1, float $x2, float $y2, string $color): string
    {
        return sprintf(
            '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="1.5" pointer-events="none"/>',
            $x1,
            $y1,
            $x2,
            $y2,
            $color
        );
    }

    private function edgeLabel(float $x, float $y, string $text, string $color): string
    {
        return sprintf(
            '<text x="%s" y="%s" text-anchor="middle" font-size="8" fill="%s" pointer-events="none">%s</text>',
            $x,
            $y - 4,
            $color,
            htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8')
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
