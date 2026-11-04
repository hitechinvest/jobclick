<?php

namespace App\Enums;

enum TransactionType: string
{
    case Topup = 'topup';
    case Reserve = 'reserve';
    case Release = 'release';
    case Charge = 'charge';
    case Adjust = 'adjust';

    public function label(): string
    {
        return match ($this) {
            self::Topup => 'Пополнение',
            self::Reserve => 'Резерв на кампанию',
            self::Release => 'Возврат резерва',
            self::Charge => 'Списание за клик',
            self::Adjust => 'Корректировка',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
