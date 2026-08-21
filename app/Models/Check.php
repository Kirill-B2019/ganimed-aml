<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Models;

use App\Enums\CheckStatus;
use App\Enums\CheckType;
use App\Enums\CheckVerdict;
use Database\Factories\CheckFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Check extends Model
{
    /** @use HasFactory<CheckFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'previous_check_id',
        'case_id',
        'type',
        'subject',
        'chain_id',
        'status',
        'verdict',
        'risk_score',
        'locale',
        'provider_request_id',
        'flags',
        'raw_response',
        'enrichment',
        'override',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'type' => CheckType::class,
            'status' => CheckStatus::class,
            'verdict' => CheckVerdict::class,
            'flags' => 'array',
            'raw_response' => 'array',
            'enrichment' => 'array',
            'override' => 'array',
            'risk_score' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function previousCheck(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_check_id');
    }

    public function screeningCase(): BelongsTo
    {
        return $this->belongsTo(ScreeningCase::class, 'case_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function chainName(): ?string
    {
        if (! $this->chain_id) {
            return null;
        }

        return config('goplus.chain_names.'.$this->chain_id)
            ?? config('goplus.chains.'.$this->chain_id, $this->chain_id);
    }

    public function isPending(): bool
    {
        return $this->status === CheckStatus::Pending;
    }

    public function isCompleted(): bool
    {
        return $this->status === CheckStatus::Completed;
    }

    public function canOverrideVerdict(): bool
    {
        return $this->isCompleted() && $this->verdict !== null && $this->verdict !== CheckVerdict::Clear;
    }

    /**
     * @return array<string, mixed>
     */
    public function overridePayload(): array
    {
        return is_array($this->override) ? $this->override : [];
    }

    /**
     * @return array<string, string>
     */
    public function tokenOverrides(): array
    {
        $tokens = is_array($this->overridePayload()['tokens'] ?? null) ? $this->overridePayload()['tokens'] : [];
        $clean = [];
        foreach ($tokens as $contract => $kind) {
            if (in_array($kind, ['lookalike', 'noise', 'ignore'], true)) {
                $clean[(string) $contract] = $kind;
            }
        }

        return $clean;
    }

    public function verdictIsLocked(): bool
    {
        return (bool) ($this->overridePayload()['verdict_locked'] ?? false);
    }

    public static function verdictRank(?CheckVerdict $verdict): int
    {
        return match ($verdict) {
            CheckVerdict::Block => 2,
            CheckVerdict::Review => 1,
            CheckVerdict::Clear, CheckVerdict::Manual, null => 0,
        };
    }
}
