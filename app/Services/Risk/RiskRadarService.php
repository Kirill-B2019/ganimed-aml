<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Services\Risk;

use App\Models\Check;

class RiskRadarService
{
    /**
     * @return list<array{key: string, value: int}>
     */
    public function axes(Check $check): array
    {
        $raw = is_array($check->raw_response) ? $check->raw_response : [];

        return $this->isScanPayload($raw)
            ? $this->scanAxes($raw)
            : $this->addressAxes($raw);
    }

    /**
     * @param  list<array{key: string, value: int}>  $axes
     */
    public function svg(array $axes, int $size = 320, bool $withLabels = true): string
    {
        $n = count($axes);
        if ($n < 3) {
            return '';
        }

        $cx = $size / 2;
        $cy = $size / 2;
        $radius = $size * 0.32;
        $levels = [0.25, 0.5, 0.75, 1];
        $grid = '';
        foreach ($levels as $level) {
            $grid .= '<polygon fill="none" stroke="#e5e7eb" stroke-width="1" points="'.$this->polygon($n, $cx, $cy, $radius * $level).'"/>';
        }

        $spokes = '';
        $labels = '';
        $valuePts = [];
        foreach ($axes as $i => $axis) {
            $angle = $this->angle($i, $n);
            $x = $cx + $radius * cos($angle);
            $y = $cy + $radius * sin($angle);
            $spokes .= sprintf('<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="#e5e7eb" stroke-width="1"/>', $cx, $cy, $x, $y);

            $lx = $cx + ($radius + 28) * cos($angle);
            $ly = $cy + ($radius + 28) * sin($angle);
            $anchor = abs(cos($angle)) < 0.2 ? 'middle' : (cos($angle) > 0 ? 'start' : 'end');
            $labels .= sprintf(
                '<text x="%s" y="%s" text-anchor="%s" font-size="10" fill="#374151">%s</text>',
                $lx,
                $ly + 3,
                $anchor,
                htmlspecialchars(__('aml.radar.'.$axis['key']), ENT_QUOTES | ENT_XML1, 'UTF-8')
            );

            $vr = $radius * (max(0, min(100, $axis['value'])) / 100);
            $valuePts[] = ($cx + $vr * cos($angle)).','.($cy + $vr * sin($angle));
        }

        $fill = implode(' ', $valuePts);

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 '.$size.' '.$size.'">'
            .$grid.$spokes
            .'<polygon fill="#c7d2fe" stroke="#4f46e5" stroke-width="2" points="'.$fill.'"/>'
            .($withLabels ? $labels : '')
            .'</svg>';
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return list<array{key: string, value: int}>
     */
    private function addressAxes(array $raw): array
    {
        $groups = [
            'sanctions' => ['sanctioned'],
            'laundering' => ['money_laundering'],
            'mixer' => ['mixer'],
            'fraud' => ['phishing_activities', 'stealing_attack', 'blackmail_activities', 'fake_kyc', 'fake_token'],
            'cybercrime' => ['cybercrime', 'financial_crime', 'darkweb_transactions'],
            'blacklist' => ['blacklist_doubt'],
            'honeypot' => ['honeypot_related_address', 'fake_standard_interface'],
            'abuse' => ['gas_abuse', 'malicious_mining_activities'],
        ];

        $axes = [];
        foreach ($groups as $key => $fields) {
            $hit = false;
            foreach ($fields as $field) {
                $value = $raw[$field] ?? 0;
                if ($value === 1 || $value === '1' || $value === true || (is_numeric($value) && (int) $value > 0)) {
                    $hit = true;
                    break;
                }
            }
            $axes[] = ['key' => $key, 'value' => $hit ? 100 : 0];
        }

        return $axes;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return list<array{key: string, value: int}>
     */
    private function scanAxes(array $raw): array
    {
        $keys = [
            'approval_risk',
            'risky_address_interaction',
            'address_poisoned',
            'token_risk',
            'nft_risk',
            'excessive_gas_fee',
            'stablecoin_depeg',
            'nft_stolen',
        ];

        $axes = [];
        foreach ($keys as $key) {
            $num = (int) ($raw[$key]['risk_num'] ?? 0);
            $axes[] = ['key' => $key, 'value' => min(100, $num * 20)];
        }

        return $axes;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function isScanPayload(array $raw): bool
    {
        foreach ($raw as $value) {
            if (is_array($value) && array_key_exists('risk_num', $value)) {
                return true;
            }
        }

        return false;
    }

    private function angle(int $index, int $count): float
    {
        return deg2rad(-90 + ($index * 360 / $count));
    }

    private function polygon(int $n, float $cx, float $cy, float $radius): string
    {
        $points = [];
        for ($i = 0; $i < $n; $i++) {
            $angle = $this->angle($i, $n);
            $points[] = ($cx + $radius * cos($angle)).','.($cy + $radius * sin($angle));
        }

        return implode(' ', $points);
    }
}
