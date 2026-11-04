<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public const LABELS = [
        'created' => 'Кампания создана',
        'submitted' => 'Отправлена на модерацию',
        'pending_payment' => 'Ожидает оплаты',
        'approved' => 'Одобрена модератором',
        'rejected' => 'Отклонена модератором',
        'needs_changes' => 'Возвращена на доработку',
        'placed' => 'Размещена на площадке',
        'started' => 'Показы запущены',
        'paused' => 'Поставлена на паузу',
        'resumed' => 'Показы возобновлены',
        'budget_80' => 'Израсходовано 80% бюджета',
        'completed' => 'Кампания завершена',
        'cancelled' => 'Кампания отменена',
        'topped_up' => 'Бюджет кампании увеличен',
        'vacancy_closed' => 'Вакансия закрыта или в архиве',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->type] ?? $this->type;
    }
}
