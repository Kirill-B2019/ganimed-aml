<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Services\Onchain;

use App\Models\Check;

class AssetNarrativeService
{
    /** @var array<string, string> */
    private const CANONICAL = [
        'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t' => 'USDT',
        'TEkxiTehnzSmSe2XqrBj4w32RUN966rdz8' => 'USDC',
    ];

    public function describe(Check $check): ?string
    {
        $onchain = $check->enrichment;
        if (! is_array($onchain) || ! empty($onchain['skipped']) || ! empty($onchain['error'])) {
            return null;
        }

        $balances = is_array($onchain['balances'] ?? null) ? $onchain['balances'] : [];
        if ($balances === []) {
            return __('aml.narrative_empty');
        }

        $native = [];
        $canonical = [];
        $lookalike = [];
        $noise = [];

        $ignored = [];
        foreach ($balances as $row) {
            if (! is_array($row)) {
                continue;
            }
            $bucket = $this->classify($row, $check);
            $label = $this->itemLabel($row);
            match ($bucket) {
                'native' => $native[] = $label,
                'canonical' => $canonical[] = $label,
                'lookalike' => $lookalike[] = $label,
                'ignore' => $ignored[] = $label,
                default => $noise[] = $label,
            };
        }

        $parts = [];
        if ($native !== []) {
            $parts[] = __('aml.narrative_native', ['list' => $this->andList($native)]);
        }
        if ($canonical !== []) {
            $parts[] = __('aml.narrative_canonical', ['list' => $this->andList($canonical)]);
        } else {
            $parts[] = __('aml.narrative_no_canonical');
        }
        if ($lookalike !== []) {
            $parts[] = __('aml.narrative_lookalike', ['list' => $this->andList($lookalike)]);
        }
        if ($noise !== []) {
            $parts[] = __('aml.narrative_noise', [
                'count' => count($noise),
                'list' => $this->andList(array_slice($noise, 0, 6)),
            ]);
        }
        if ($ignored !== []) {
            $parts[] = __('aml.narrative_ignored', ['list' => $this->andList($ignored)]);
        }
        $parts[] = __('aml.narrative_closing');

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $onchain
     */
    public function needsReview(array $onchain, ?Check $check = null): bool
    {
        if ($onchain === [] || ! empty($onchain['skipped']) || ! empty($onchain['error'])) {
            return false;
        }

        if (($onchain['control']['type'] ?? '') === 'multisig') {
            return true;
        }

        foreach ($onchain['balances'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (in_array($this->classify($row, $check), ['lookalike', 'noise'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function classify(array $row, ?Check $check = null): string
    {
        $auto = $this->bucket($row);
        if ($this->isStatusLocked($row)) {
            return $auto;
        }

        $contract = (string) ($row['contract'] ?? '');
        $overrides = $check?->tokenOverrides() ?? [];
        if ($contract !== '' && isset($overrides[$contract])) {
            return $overrides[$contract];
        }

        return $auto;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function autoKind(array $row): string
    {
        return $this->bucket($row);
    }

    /**
     * Native TRX and canonical USDT/USDC cannot be reclassified.
     *
     * @param  array<string, mixed>  $row
     */
    public function isStatusLocked(array $row): bool
    {
        return in_array($this->bucket($row), ['native', 'canonical'], true);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function bucket(array $row): string
    {
        $contract = (string) ($row['contract'] ?? '');
        $symbol = strtoupper((string) ($row['symbol'] ?? ''));
        $name = (string) ($row['name'] ?? '');
        $kind = (string) ($row['kind'] ?? '');

        if ($kind === 'native' || $symbol === 'TRX') {
            return 'native';
        }
        if ($contract !== '' && isset(self::CANONICAL[$contract])) {
            return 'canonical';
        }
        if (preg_match('/USDT|USDC|UDST|TETHER/', $symbol.' '.$name) === 1) {
            return 'lookalike';
        }

        return 'noise';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function itemLabel(array $row): string
    {
        $symbol = (string) ($row['symbol'] ?? '');
        $amount = (string) ($row['amount'] ?? '');

        return trim($amount.' '.$symbol);
    }

    /**
     * @param  list<string>  $items
     */
    private function andList(array $items): string
    {
        $items = array_values(array_filter($items));
        if ($items === []) {
            return '';
        }
        if (count($items) === 1) {
            return $items[0];
        }
        $last = array_pop($items);

        return implode(', ', $items).' '.__('aml.and').' '.$last;
    }
}
