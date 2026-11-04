<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CampaignStatus: string implements HasColor, HasLabel
{
    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return $this->color();
    }

    case Draft = 'draft';
    case PendingPayment = 'pending_payment';
    case Moderation = 'moderation';
    case NeedsChanges = 'needs_changes';
    case Approved = 'approved';
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::PendingPayment => 'Ожидает оплаты',
            self::Moderation => 'На модерации',
            self::NeedsChanges => 'Нужны правки',
            self::Approved => 'Одобрено, размещаем',
            self::Active => 'Идут показы',
            self::Paused => 'На паузе',
            self::Completed => 'Завершена',
            self::Rejected => 'Отклонена',
            self::Cancelled => 'Отменена',
        };
    }

    /** Цвет для бейджа: tailwind-класс в кабинете и цвет Filament в админке. */
    public function color(): string
    {
        return match ($this) {
            self::Draft, self::Cancelled => 'gray',
            self::PendingPayment, self::NeedsChanges => 'warning',
            self::Moderation, self::Approved => 'info',
            self::Active => 'success',
            self::Paused => 'warning',
            self::Completed => 'gray',
            self::Rejected => 'danger',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::PendingPayment, self::NeedsChanges], true);
    }

    public function isLive(): bool
    {
        return in_array($this, [self::Approved, self::Active, self::Paused], true);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
