<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Services\Onchain;

use App\Support\TronAddress;

class WalletGraphChart
{
    /** @var array<string, string> */
    public const COLORS = [
        'subject' => '#111827',
        'eoa' => '#6366f1',
        'contract' => '#0f766e',
        'token' => '#4f46e5',
        'spam' => '#be123c',
        'dust' => '#d97706',
        'unknown' => '#94a3b8',
        'in' => '#c7d2fe',
        'out' => '#99f6e4',
        'internal' => '#e2e8f0',
    ];

    /**
     * @param  array<string, mixed>  $graph
     */
    public function svg(array $graph, int $width = 720, int $height = 520): string
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
        foreach ($hop1 as $i => $node) {
            $placed[(string) $node['id']] = $this->ringPoint($cx, $cy, 180, 135, $i, max(count($hop1), 1), -90);
        }
        foreach ($hop2 as $i => $node) {
            $placed[(string) $node['id']] = $this->ringPoint($cx, $cy, 236, 180, $i, max(count($hop2), 1), -75);
        }
        if (is_array($subject) && ! empty($subject['id'])) {
            $placed[(string) $subject['id']] = [$cx, $cy];
        }

        $markup = '';
        foreach ($edges as $edge) {
            if (! is_array($edge)) {
                continue;
            }
            $from = (string) ($edge['from'] ?? '');
            $to = (string) ($edge['to'] ?? '');
            if (! isset($placed[$from], $placed[$to])) {
                continue;
            }
            $hygiene = (string) ($edge['hygiene'] ?? '');
            $direction = (string) ($edge['direction'] ?? 'in');
            $color = match (true) {
                $hygiene === 'spam' => self::COLORS['spam'],
                $hygiene === 'dust' || ! empty($edge['any_dust']) => self::COLORS['dust'],
                default => self::COLORS[$direction] ?? self::COLORS['in'],
            };
            $widthStroke = ($hygiene === 'spam' || $hygiene === 'dust' || ! empty($edge['any_dust'])) ? '2.4' : '1.4';
            $markup .= sprintf(
                '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="%s" pointer-events="none"/>',
                $placed[$from][0],
                $placed[$from][1],
                $placed[$to][0],
                $placed[$to][1],
                $color,
                $widthStroke
            );
        }

        foreach (array_merge($hop2, $hop1) as $node) {
            $id = (string) $node['id'];
            $markup .= $this->nodeMarkup($node, $placed[$id] ?? [$cx, $cy], false);
        }
        if (is_array($subject)) {
            $markup .= $this->nodeMarkup($subject, [$cx, $cy], true);
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 '.$width.' '.$height.'" class="w-full h-auto" role="img">'
            .$markup
            .'</svg>';
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return list<array<string, mixed>>
     */
    public function peers(array $graph): array
    {
        $nodes = is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [];
        $edges = is_array($graph['edges'] ?? null) ? $graph['edges'] : [];
        $assets = [];
        foreach ($edges as $edge) {
            if (! is_array($edge)) {
                continue;
            }
            foreach (['from', 'to'] as $side) {
                $id = (string) ($edge[$side] ?? '');
                if ($id === '') {
                    continue;
                }
                $label = $this->edgeAssetLabel($edge);
                if ($label === '') {
                    continue;
                }
                $assets[$id][$label] = true;
            }
        }

        $rows = [];
        foreach ($nodes as $node) {
            if (! is_array($node) || empty($node['id']) || (int) ($node['hop'] ?? 1) === 0) {
                continue;
            }
            $id = (string) $node['id'];
            $kind = (string) ($node['kind'] ?? 'unknown');
            $flags = is_array($node['flags'] ?? null) ? $node['flags'] : [];
            $tone = $this->toneKey($kind, $flags);
            $rows[] = [
                'id' => $id,
                'short' => $this->shortId($id),
                'explorer' => TronAddress::explorerUrl($id),
                'color' => $this->fillColor($kind, $flags, false),
                'tone' => $tone,
                'status' => $this->statusParts($kind, $flags),
                'in_count' => (int) ($node['in_count'] ?? 0),
                'out_count' => (int) ($node['out_count'] ?? 0),
                'assets' => implode(', ', array_keys($assets[$id] ?? [])),
                'hop' => (int) ($node['hop'] ?? 1),
            ];
        }

        usort($rows, function (array $a, array $b) {
            $rank = ['spam' => 0, 'dust' => 1, 'contract' => 2, 'token' => 3, 'eoa' => 4, 'unknown' => 5];

            return ($rank[$a['tone']] ?? 9) <=> ($rank[$b['tone']] ?? 9)
                ?: ($b['in_count'] + $b['out_count']) <=> ($a['in_count'] + $a['out_count']);
        });

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $peers
     * @return array<string, string>
     */
    public function legend(array $peers): array
    {
        $present = [];
        foreach ($peers as $peer) {
            if (! is_array($peer)) {
                continue;
            }
            $tone = (string) ($peer['tone'] ?? '');
            if ($tone !== '') {
                $present[$tone] = true;
            }
        }

        $out = [];
        foreach (['spam', 'dust', 'contract', 'token', 'eoa', 'unknown'] as $key) {
            if ($key === 'spam' || $key === 'dust' || isset($present[$key])) {
                $out[$key] = self::COLORS[$key];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array{0: float, 1: float}  $pos
     */
    private function nodeMarkup(array $node, array $pos, bool $center): string
    {
        $id = (string) ($node['id'] ?? '');
        $kind = (string) ($node['kind'] ?? 'unknown');
        $flags = is_array($node['flags'] ?? null) ? $node['flags'] : [];
        $fill = $this->fillColor($kind, $flags, $center);
        $r = $center ? 20 : (((int) ($node['hop'] ?? 1)) >= 2 ? 10 : 14);
        $url = TronAddress::explorerUrl($id);
        $inner = sprintf(
            '<title>%s</title><circle cx="%s" cy="%s" r="%s" fill="%s" stroke="#fff" stroke-width="2"/>',
            htmlspecialchars($id, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            $pos[0],
            $pos[1],
            $r,
            $fill
        );

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
    public function fillColor(string $kind, array $flags, bool $center): string
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
    private function toneKey(string $kind, array $flags): string
    {
        if (in_array('spam', $flags, true) || $kind === 'spam') {
            return 'spam';
        }
        if (in_array('dust', $flags, true)) {
            return 'dust';
        }
        if (in_array($kind, ['eoa', 'contract', 'token', 'unknown'], true)) {
            return $kind;
        }

        return 'unknown';
    }

    /**
     * @param  list<string>  $flags
     * @return list<string>
     */
    private function statusParts(string $kind, array $flags): array
    {
        $parts = [];
        $kindKey = in_array($kind, ['eoa', 'contract', 'token', 'spam', 'unknown'], true) ? $kind : 'unknown';
        if ($kindKey !== 'spam') {
            $parts[] = $kindKey;
        }
        if (in_array('dust', $flags, true)) {
            $parts[] = 'dust';
        }
        if (in_array('spam', $flags, true) || $kind === 'spam') {
            $parts[] = 'spam';
        }

        return array_values(array_unique($parts));
    }

    /**
     * @param  array<string, mixed>  $edge
     */
    private function edgeAssetLabel(array $edge): string
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

        return $asset;
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function ringPoint(float $cx, float $cy, float $rx, float $ry, int $i, int $n, float $startDeg): array
    {
        $angle = deg2rad($startDeg + ($i * 360 / $n));

        return [$cx + $rx * cos($angle), $cy + $ry * sin($angle)];
    }

    private function shortId(string $id): string
    {
        if (strlen($id) < 12) {
            return $id;
        }

        return substr($id, 0, 6).'…'.substr($id, -4);
    }
}
