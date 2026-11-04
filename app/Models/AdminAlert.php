<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAlert extends Model
{
    protected $guarded = ['id'];

    public const TYPES = [
        'moderation' => 'Новая кампания на модерации',
        'placement' => 'Ждёт размещения в сетях',
        'budget_80' => '80% бюджета',
        'budget_100' => 'Бюджет исчерпан — остановить в сетях',
        'paused' => 'Пользователь поставил на паузу',
        'resumed' => 'Пользователь возобновил показы',
        'cancelled' => 'Пользователь остановил кампанию',
        'vacancy_closed' => 'Вакансия закрыта',
        'invoice' => 'Выставлен счёт',
        'payment' => 'Поступила онлайн-оплата',
        'error' => 'Ошибка интеграции',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
