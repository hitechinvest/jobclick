<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $guarded = ['id'];

    public const STATUSES = ['pending' => 'Ожидает оплаты', 'succeeded' => 'Оплачен', 'canceled' => 'Отменён'];

    protected function casts(): array
    {
        return ['raw' => 'array', 'amount' => 'decimal:2', 'credited_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
