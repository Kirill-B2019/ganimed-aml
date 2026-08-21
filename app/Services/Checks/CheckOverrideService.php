<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Services\Checks;

use App\Enums\CheckVerdict;
use App\Models\Check;
use App\Models\User;
use App\Services\Onchain\AssetNarrativeService;
use App\Services\Risk\RiskScoringService;

class CheckOverrideService
{
    public function __construct(
        private AssetNarrativeService $narrative,
        private RiskScoringService $scoring,
    ) {}

    /**
     * @param  array<string, string>  $tokens
     */
    public function apply(Check $check, User $user, string $verdict, ?string $note, array $tokens): Check
    {
        abort_unless($check->isCompleted(), 409, __('aml.pdf_not_ready'));
        abort_if($check->verdict === CheckVerdict::Clear, 422, __('aml.override_clear_locked'));

        $new = CheckVerdict::from($verdict);
        abort_if($new === CheckVerdict::Clear, 422, __('aml.override_cannot_clear'));

        $override = is_array($check->override) ? $check->override : [];
        $override['provider_verdict'] ??= $check->verdict?->value;
        $override['provider_score'] ??= $check->risk_score;
        $override['verdict_locked'] = true;
        $override['by'] = $user->id;
        $override['at'] = now()->toIso8601String();
        $override['note'] = $note !== null && trim($note) !== '' ? trim($note) : ($override['note'] ?? null);
        $override['tokens'] = $this->sanitizeTokens($check, $tokens);

        $check->override = $override;
        $check->verdict = $new;
        $check->risk_score = $new === CheckVerdict::Block
            ? RiskScoringService::BLOCK_SCORE
            : max($this->scoring->breakdown($check)['total'], RiskScoringService::ONCHAIN_FLOOR);
        $check->save();

        app(\App\Services\Ops\ActivityLogger::class)->record($user, 'verdict', $check, [
            'verdict' => $new->value,
        ]);
        \App\Jobs\DispatchAmlWebhookJob::forCheck('check.verdict.changed', $check->loadMissing('user'));

        return $check->refresh();
    }

    /**
     * @param  array<string, string>  $tokens
     * @return array<string, string>
     */
    private function sanitizeTokens(Check $check, array $tokens): array
    {
        $allowed = $this->overridableContracts($check);
        $clean = [];
        foreach ($tokens as $contract => $kind) {
            $contract = (string) $contract;
            if (isset($allowed[$contract]) && in_array($kind, ['lookalike', 'noise', 'ignore'], true)) {
                $clean[$contract] = $kind;
            }
        }

        return $clean;
    }

    /**
     * @return array<string, string>
     */
    public function overridableContracts(Check $check): array
    {
        $map = [];
        $onchain = is_array($check->enrichment) ? $check->enrichment : [];
        foreach ($onchain['balances'] ?? [] as $row) {
            if (! is_array($row) || $this->narrative->isStatusLocked($row)) {
                continue;
            }
            $contract = (string) ($row['contract'] ?? '');
            if ($contract === '') {
                continue;
            }
            $map[$contract] = $this->narrative->autoKind($row);
        }

        return $map;
    }
}
