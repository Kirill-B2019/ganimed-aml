<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Http\Resources;

use App\Enums\RiskGrade;
use App\Models\Check;
use App\Services\Onchain\AssetNarrativeService;
use App\Services\Onchain\TokenCompositionChart;
use App\Services\Onchain\WalletUsdValuationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Check */
class CheckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'subject' => $this->subject,
            'chain_id' => $this->chain_id,
            'chain' => $this->chainName(),
            'status' => $this->status->value,
            'verdict' => $this->verdict?->value,
            'risk_score' => $this->risk_score,
            'risk_grade' => $this->type->isWallet() && $this->isCompleted()
                ? RiskGrade::fromScore((int) $this->risk_score)->value
                : null,
            'locale' => $this->locale,
            'flags' => $this->flags,
            'raw_response' => $this->raw_response,
            'enrichment' => $this->enrichment,
            'asset_narrative' => app(AssetNarrativeService::class)->describe($this->resource),
            'token_composition' => app(TokenCompositionChart::class)->slices($this->resource),
            'wallet_usd' => $this->type->isWallet()
                ? app(WalletUsdValuationService::class)->summarize($this->resource)
                : null,
            'error_message' => $this->error_message,
            'previous_check_id' => $this->previous_check_id,
            'case_id' => $this->case_id,
            'fetched_at' => is_array($this->enrichment) ? ($this->enrichment['fetched_at'] ?? null) : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
