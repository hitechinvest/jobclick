<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $guarded = ['id'];

    public const STATUSES = ['issued' => 'Выставлен', 'paid' => 'Оплачен', 'cancelled' => 'Отменён'];

    protected function casts(): array
    {
        return [
            'payer' => 'array',
            'payee' => 'array',
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function purpose(): string
    {
        return 'Оплата рекламных услуг по счёту № '.$this->number.' от '.$this->created_at->format('d.m.Y').'. Без НДС';
    }
}
