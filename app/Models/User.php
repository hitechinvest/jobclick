<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'is_admin', 'yandex_id', 'mailru_id', 'last_login_at', 'is_blocked'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_blocked' => 'boolean',
            'balance' => 'decimal:2',
            'reserved' => 'decimal:2',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin && ! $this->is_blocked;
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Свободные деньги: баланс минус резерв под кампании. */
    public function available(): float
    {
        return round((float) $this->balance - (float) $this->reserved, 2);
    }

    public function displayName(): string
    {
        return $this->company?->name_short ?: ($this->name ?: $this->email);
    }
}
