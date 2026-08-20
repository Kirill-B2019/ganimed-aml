<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Services\Risk;

use App\Enums\CheckType;
use App\Enums\CheckVerdict;
use App\Models\Check;
use App\Services\Onchain\AssetNarrativeService;

class RiskScoringService
{
    public const REVIEW_BASE = 20;

    public const REVIEW_PER_FLAG = 15;

    public const REVIEW_CAP = 90;

    public const BLOCK_SCORE = 100;

    public const ONCHAIN_FLOOR = 20;

    /** @var list<string> */
    private array $addressBlockFlags = [
        'sanctioned',
        'money_laundering',
    ];

    /** @var list<string> */
    private array $addressReviewFlags = [
        'mixer',
        'cybercrime',
        'financial_crime',
        'darkweb_transactions',
        'phishing_activities',
        'stealing_attack',
        'blackmail_activities',
        'honeypot_related_address',
        'blacklist_doubt',
        'fake_kyc',
        'malicious_mining_activities',
        'gas_abuse',
        'fake_token',
        'fake_standard_interface',
    ];

    /** @var list<string> */
    private array $tokenBlockFlags = [
        'is_honeypot',
        'honeypot_with_same_creator',
        'is_airdrop_scam',
        'is_blacklisted',
    ];

    /** @var list<string> */
    private array $tokenReviewFlags = [
        'hidden_owner',
        'can_take_back_ownership',
        'owner_change_balance',
        'selfdestruct',
        'is_proxy',
        'trading_cooldown',
        'cannot_buy',
        'cannot_sell_all',
        'slippage_modifiable',
        'personal_slippage_modifiable',
        'is_anti_whale',
        'anti_whale_modifiable',
        'is_mintable',
        'transfer_pausable',
        'is_true_token',
        'is_fake_token',
        'malicious_address',
        'freezable',
        'closable',
        'balance_mutable_authority',
        'metadata_mutable_authority',
    ];

    /**
     * @param  array<string, mixed>  $result
     * @return array{verdict: CheckVerdict, score: int, flags: list<array{key: string, value: mixed, severity: string}>}
     */
    public function score(CheckType $type, array $result): array
    {
        return match ($type) {
            CheckType::Address => $this->scoreAddress($result),
            CheckType::Token => $this->scoreToken($result),
            CheckType::Phishing => $this->scorePhishing($result),
            CheckType::Dapp => $this->scoreDapp($result),
            CheckType::Scan => $this->scoreScan($result),
        };
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{verdict: CheckVerdict, score: int, flags: list<array{key: string, value: mixed, severity: string}>}
     */
    private function scoreAddress(array $result): array
    {
        return $this->scoreKeyedFlags($result, $this->addressBlockFlags, $this->addressReviewFlags);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{verdict: CheckVerdict, score: int, flags: list<array{key: string, value: mixed, severity: string}>}
     */
    private function scoreToken(array $result): array
    {
        $token = $this->firstTokenPayload($result);

        $flags = [];
        $block = false;
        $review = false;

        foreach ($this->tokenBlockFlags as $key) {
            if ($this->isPositive($token[$key] ?? null)) {
                $block = true;
                $flags[] = ['key' => $key, 'value' => $token[$key], 'severity' => 'block'];
            }
        }

        foreach ($this->tokenReviewFlags as $key) {
            if ($this->isPositive($token[$key] ?? null)) {
                $review = true;
                $flags[] = ['key' => $key, 'value' => $token[$key], 'severity' => 'review'];
            }
        }

        foreach (['buy_tax', 'sell_tax'] as $taxKey) {
            if (! isset($token[$taxKey]) || ! is_numeric($token[$taxKey])) {
                continue;
            }
            $tax = (float) $token[$taxKey];
            if ($tax >= 50) {
                $block = true;
                $flags[] = ['key' => $taxKey, 'value' => $token[$taxKey], 'severity' => 'block'];
            } elseif ($tax >= 10) {
                $review = true;
                $flags[] = ['key' => $taxKey, 'value' => $token[$taxKey], 'severity' => 'review'];
            }
        }

        return $this->finalize($block, $review, $flags);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{verdict: CheckVerdict, score: int, flags: list<array{key: string, value: mixed, severity: string}>}
     */
    private function scorePhishing(array $result): array
    {
        $positive = $this->isPositive($result['phishing_site'] ?? 0);
        $flags = [];
        if ($positive) {
            $flags[] = ['key' => 'phishing_site', 'value' => $result['phishing_site'], 'severity' => 'block'];
        }

        return $this->finalize($positive, false, $flags);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{verdict: CheckVerdict, score: int, flags: list<array{key: string, value: mixed, severity: string}>}
     */
    private function scoreDapp(array $result): array
    {
        $flags = [];
        $block = false;
        $review = false;

        if (($result['trust_list'] ?? null) === '1' || ($result['trust_list'] ?? null) === 1) {
            $flags[] = ['key' => 'trust_list', 'value' => $result['trust_list'], 'severity' => 'clear'];
        }

        foreach ($result['contracts_security'] ?? [] as $chain) {
            foreach ($chain['contracts'] ?? [] as $contract) {
                if ($this->isPositive($contract['malicious_contract'] ?? 0)) {
                    $block = true;
                    $flags[] = ['key' => 'malicious_contract', 'value' => $contract['contract_address'] ?? '1', 'severity' => 'block'];
                }
                if ($this->isPositive($contract['malicious_creator'] ?? 0)) {
                    $block = true;
                    $flags[] = ['key' => 'malicious_creator', 'value' => $contract['creator_address'] ?? '1', 'severity' => 'block'];
                }
                if (isset($contract['is_open_source']) && ! $this->isPositive($contract['is_open_source'])) {
                    $review = true;
                    $flags[] = ['key' => 'is_open_source', 'value' => $contract['is_open_source'], 'severity' => 'review'];
                }
            }
        }

        if (! $this->isPositive($result['is_audit'] ?? 0) && ! $block && ($result['trust_list'] ?? null) != 1) {
            $review = true;
            $flags[] = ['key' => 'is_audit', 'value' => $result['is_audit'] ?? 0, 'severity' => 'review'];
        }

        return $this->finalize($block, $review, $flags);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{verdict: CheckVerdict, score: int, flags: list<array{key: string, value: mixed, severity: string}>}
     */
    private function scoreScan(array $result): array
    {
        $hasScanAxes = false;
        foreach ($result as $value) {
            if (is_array($value) && array_key_exists('risk_num', $value)) {
                $hasScanAxes = true;
                break;
            }
        }

        if (! $hasScanAxes) {
            return $this->scoreAddress($result);
        }
        $flags = [];
        $total = 0;
        $max = 0;

        foreach ($result as $key => $value) {
            if (! is_array($value) || ! array_key_exists('risk_num', $value)) {
                continue;
            }
            $num = (int) $value['risk_num'];
            if ($num <= 0) {
                continue;
            }
            $total += $num;
            $max = max($max, $num);
            $flags[] = [
                'key' => (string) $key,
                'value' => $num,
                'severity' => $num >= 5 ? 'block' : 'review',
            ];
        }

        $block = $max >= 5 || $total >= 10;
        $review = $total > 0;

        return $this->finalize($block, $review, $flags);
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<string>  $blockKeys
     * @param  list<string>  $reviewKeys
     * @return array{verdict: CheckVerdict, score: int, flags: list<array{key: string, value: mixed, severity: string}>}
     */
    private function scoreKeyedFlags(array $result, array $blockKeys, array $reviewKeys): array
    {
        $flags = [];
        $block = false;
        $review = false;

        foreach ($blockKeys as $key) {
            if ($this->isPositive($result[$key] ?? null)) {
                $block = true;
                $flags[] = ['key' => $key, 'value' => $result[$key], 'severity' => 'block'];
            }
        }

        foreach ($reviewKeys as $key) {
            if ($this->isPositive($result[$key] ?? null)) {
                $review = true;
                $flags[] = ['key' => $key, 'value' => $result[$key], 'severity' => 'review'];
            }
        }

        if (isset($result['number_of_malicious_contracts_created']) && (int) $result['number_of_malicious_contracts_created'] > 0) {
            $review = true;
            $flags[] = [
                'key' => 'number_of_malicious_contracts_created',
                'value' => $result['number_of_malicious_contracts_created'],
                'severity' => 'review',
            ];
        }

        return $this->finalize($block, $review, $flags);
    }

    /**
     * @param  list<array{key: string, value: mixed, severity: string}>  $flags
     * @return array{verdict: CheckVerdict, score: int, flags: list<array{key: string, value: mixed, severity: string}>}
     */
    private function finalize(bool $block, bool $review, array $flags): array
    {
        if ($block) {
            return ['verdict' => CheckVerdict::Block, 'score' => self::BLOCK_SCORE, 'flags' => $flags];
        }

        if ($review) {
            $score = min(self::REVIEW_CAP, self::REVIEW_BASE + (count($flags) * self::REVIEW_PER_FLAG));

            return ['verdict' => CheckVerdict::Review, 'score' => $score, 'flags' => $flags];
        }

        return ['verdict' => CheckVerdict::Clear, 'score' => 0, 'flags' => $flags];
    }

    /**
     * @return array{
     *     total: int,
     *     formula: string,
     *     lines: list<array{label: string, points: int, rule: string, severity: string}>
     * }
     */
    public function breakdown(Check $check): array
    {
        $flags = is_array($check->flags) ? $check->flags : [];
        $lines = [];
        $hasBlock = false;
        $reviewCount = 0;
        $hasOnchainFlag = false;

        foreach ($flags as $flag) {
            if (! is_array($flag)) {
                continue;
            }
            $key = (string) ($flag['key'] ?? '');
            $severity = (string) ($flag['severity'] ?? 'review');
            if ($severity === 'clear' || $key === '') {
                continue;
            }
            if ($key === 'onchain_hygiene') {
                $hasOnchainFlag = true;
                continue;
            }
            if ($severity === 'block') {
                $hasBlock = true;
                $lines[] = [
                    'label' => $this->flagLabel($key),
                    'points' => self::BLOCK_SCORE,
                    'rule' => __('aml.score_rule_block'),
                    'severity' => 'block',
                ];
                continue;
            }
            $reviewCount++;
            $lines[] = [
                'label' => $this->flagLabel($key),
                'points' => self::REVIEW_PER_FLAG,
                'rule' => __('aml.score_rule_flag', ['points' => self::REVIEW_PER_FLAG]),
                'severity' => 'review',
            ];
        }

        if ($hasBlock) {
            return $this->withAnalystOverride($check, [
                'total' => self::BLOCK_SCORE,
                'formula' => __('aml.score_formula_block', ['score' => self::BLOCK_SCORE]),
                'lines' => $lines,
            ]);
        }

        $onchain = is_array($check->enrichment) ? $check->enrichment : [];
        $onchainReview = $hasOnchainFlag || (new AssetNarrativeService)->needsReview($onchain, $check);

        if ($reviewCount > 0) {
            array_unshift($lines, [
                'label' => __('aml.score_base_label'),
                'points' => self::REVIEW_BASE,
                'rule' => __('aml.score_rule_base', ['points' => self::REVIEW_BASE]),
                'severity' => 'review',
            ]);
            $raw = self::REVIEW_BASE + ($reviewCount * self::REVIEW_PER_FLAG);
            $total = min(self::REVIEW_CAP, $raw);
            if ($raw > self::REVIEW_CAP) {
                $lines[] = [
                    'label' => __('aml.score_cap_label'),
                    'points' => $total - $raw,
                    'rule' => __('aml.score_rule_cap', ['cap' => self::REVIEW_CAP]),
                    'severity' => 'review',
                ];
            }
            if ($onchainReview && $total < self::ONCHAIN_FLOOR) {
                $lines[] = [
                    'label' => __('aml.score_onchain_label'),
                    'points' => self::ONCHAIN_FLOOR - $total,
                    'rule' => __('aml.score_rule_onchain', ['floor' => self::ONCHAIN_FLOOR]),
                    'severity' => 'review',
                ];
                $total = self::ONCHAIN_FLOOR;
            }

            return $this->withAnalystOverride($check, [
                'total' => $total,
                'formula' => __('aml.score_formula_review', [
                    'base' => self::REVIEW_BASE,
                    'per' => self::REVIEW_PER_FLAG,
                    'count' => $reviewCount,
                    'cap' => self::REVIEW_CAP,
                    'total' => $total,
                ]),
                'lines' => $lines,
            ]);
        }

        if ($onchainReview) {
            return $this->withAnalystOverride($check, [
                'total' => self::ONCHAIN_FLOOR,
                'formula' => __('aml.score_formula_onchain', ['floor' => self::ONCHAIN_FLOOR]),
                'lines' => [[
                    'label' => __('aml.score_onchain_label'),
                    'points' => self::ONCHAIN_FLOOR,
                    'rule' => __('aml.score_rule_onchain', ['floor' => self::ONCHAIN_FLOOR]),
                    'severity' => 'review',
                ]],
            ]);
        }

        return $this->withAnalystOverride($check, [
            'total' => 0,
            'formula' => __('aml.score_formula_clear'),
            'lines' => [],
        ]);
    }

    /**
     * @param  array{total: int, formula: string, lines: list<array{label: string, points: int, rule: string, severity: string}>}  $computed
     * @return array{total: int, formula: string, lines: list<array{label: string, points: int, rule: string, severity: string}>}
     */
    private function withAnalystOverride(Check $check, array $computed): array
    {
        if (! $check->verdictIsLocked() || $check->verdict !== CheckVerdict::Block || $computed['total'] >= self::BLOCK_SCORE) {
            return $computed;
        }

        $computed['lines'][] = [
            'label' => __('aml.score_analyst_label'),
            'points' => self::BLOCK_SCORE - $computed['total'],
            'rule' => __('aml.score_rule_analyst_block'),
            'severity' => 'block',
        ];
        $computed['total'] = self::BLOCK_SCORE;
        $computed['formula'] = __('aml.score_formula_block', ['score' => self::BLOCK_SCORE]);

        return $computed;
    }

    private function flagLabel(string $key): string
    {
        $help = __('aml.flag_help.'.$key);
        if ($help !== 'aml.flag_help.'.$key) {
            return $help;
        }
        $flag = __('aml.flags.'.$key);
        if ($flag !== 'aml.flags.'.$key) {
            return $flag;
        }

        return $key;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function firstTokenPayload(array $result): array
    {
        if ($result === []) {
            return [];
        }

        $first = reset($result);

        return is_array($first) ? $first : $result;
    }

    private function isPositive(mixed $value): bool
    {
        return $value === 1 || $value === '1' || $value === true;
    }
}
