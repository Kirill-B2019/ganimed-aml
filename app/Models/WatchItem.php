<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Models;

use App\Enums\CheckType;
use App\Enums\CheckVerdict;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WatchItem extends Model
{
    protected $fillable = [
        'user_id',
        'case_id',
        'last_check_id',
        'type',
        'subject',
        'chain_id',
        'interval_days',
        'last_verdict',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => CheckType::class,
            'last_verdict' => CheckVerdict::class,
            'last_run_at' => 'datetime',
            'interval_days' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function screeningCase(): BelongsTo
    {
        return $this->belongsTo(ScreeningCase::class, 'case_id');
    }

    public function lastCheck(): BelongsTo
    {
        return $this->belongsTo(Check::class, 'last_check_id');
    }

    public function isDue(): bool
    {
        if ($this->last_run_at === null) {
            return true;
        }

        return $this->last_run_at->lte(now()->subDays(max(1, $this->interval_days)));
    }
}
