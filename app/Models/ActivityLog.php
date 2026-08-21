<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Models;

use App\Enums\CheckVerdict;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'check_id',
        'case_id',
        'action',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function check(): BelongsTo
    {
        return $this->belongsTo(Check::class);
    }

    public function screeningCase(): BelongsTo
    {
        return $this->belongsTo(ScreeningCase::class, 'case_id');
    }

    public function label(): string
    {
        $key = 'aml.activity_'.$this->action;
        $base = __($key);
        if ($base === $key) {
            $base = $this->action;
        }

        if ($this->action !== 'verdict') {
            return $base;
        }

        $value = is_array($this->meta) ? ($this->meta['verdict'] ?? null) : null;
        $verdict = is_string($value) ? CheckVerdict::tryFrom($value) : null;

        return $verdict ? $base.' · '.$verdict->label() : $base;
    }

    public function note(): ?string
    {
        $note = is_array($this->meta) ? ($this->meta['note'] ?? null) : null;

        return is_string($note) && $note !== '' ? $note : null;
    }
}
