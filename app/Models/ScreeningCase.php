<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScreeningCase extends Model
{
    protected $fillable = [
        'user_id',
        'slug',
        'name',
        'note',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(Check::class, 'case_id');
    }

    public function watchItems(): HasMany
    {
        return $this->hasMany(WatchItem::class, 'case_id');
    }
}
