<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Company extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['dadata' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->state === null || $this->state === 'ACTIVE';
    }

    public function displayName(): string
    {
        return $this->name_short ?: $this->name_full;
    }

    /** Снимок реквизитов плательщика для счёта. */
    public function payerSnapshot(): array
    {
        return [
            'name' => $this->name_full,
            'inn' => $this->inn,
            'kpp' => $this->kpp,
            'address' => $this->legal_address,
        ];
    }
}
