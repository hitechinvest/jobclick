<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignPlatform extends Model
{
    protected $guarded = ['id'];

    public const STATUSES = [
        'planned' => 'Ждёт размещения',
        'placed' => 'Размещено',
        'paused' => 'На паузе',
        'stopped' => 'Остановлено',
        'rejected' => 'Отклонено площадкой',
    ];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'placed_at' => 'datetime',
            'daily_limit' => 'decimal:2',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
