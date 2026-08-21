<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'webhook_url',
        'webhook_secret',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function checks(): HasMany
    {
        return $this->hasMany(Check::class);
    }

    public function screeningCases(): HasMany
    {
        return $this->hasMany(ScreeningCase::class);
    }

    public function watchItems(): HasMany
    {
        return $this->hasMany(WatchItem::class);
    }
}
