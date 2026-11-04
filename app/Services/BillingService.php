<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Campaign;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Деньги пользователя: баланс и резерв под кампании. Все движения — через транзакции с блокировкой строки.
 * Доступно = balance - reserved. Клик списывается из резерва кампании.
 */
class BillingService
{
    public function topup(User $user, float $amount, ?Model $source = null, ?string $comment = null, ?int $by = null): Transaction
    {
        $this->assertPositive($amount);

        return DB::transaction(function () use ($user, $amount, $source, $comment, $by) {
            $u = User::lockForUpdate()->findOrFail($user->id);
            $u->balance = round((float) $u->balance + $amount, 2);
            $u->save();

            return $this->record($u, TransactionType::Topup, $amount, null, $comment, $source, $by);
        });
    }

    /** Ручная корректировка баланса админом (может быть отрицательной, но не ниже резерва). */
    public function adjust(User $user, float $amount, string $comment, ?int $by = null): Transaction
    {
        return DB::transaction(function () use ($user, $amount, $comment, $by) {
            $u = User::lockForUpdate()->findOrFail($user->id);
            $new = round((float) $u->balance + $amount, 2);
            if ($new < (float) $u->reserved) {
                throw new InvalidArgumentException('Баланс не может стать меньше суммы в резерве кампаний');
            }
            $u->balance = $new;
            $u->save();

            return $this->record($u, TransactionType::Adjust, $amount, null, $comment, null, $by);
        });
    }

    public function reserve(Campaign $campaign, float $amount): Transaction
    {
        $this->assertPositive($amount);

        return DB::transaction(function () use ($campaign, $amount) {
            $u = User::lockForUpdate()->findOrFail($campaign->user_id);
            $available = round((float) $u->balance - (float) $u->reserved, 2);
            if ($available + 0.001 < $amount) {
                throw new InsufficientFundsException(round($amount - $available, 2));
            }
            $c = Campaign::lockForUpdate()->findOrFail($campaign->id);
            $u->reserved = round((float) $u->reserved + $amount, 2);
            $u->save();
            $c->reserved = round((float) $c->reserved + $amount, 2);
            $c->saveQuietly();
            $campaign->setRawAttributes($c->getAttributes(), true);

            return $this->record($u, TransactionType::Reserve, $amount, $c, 'Резерв бюджета кампании #'.$c->id);
        });
    }

    /** Возвращает весь (или часть) резерв кампании на свободный баланс. */
    public function release(Campaign $campaign, ?float $amount = null, string $comment = 'Возврат неизрасходованного бюджета'): ?Transaction
    {
        return DB::transaction(function () use ($campaign, $amount, $comment) {
            $c = Campaign::lockForUpdate()->findOrFail($campaign->id);
            $amount = round(min((float) $c->reserved, $amount ?? (float) $c->reserved), 2);
            if ($amount <= 0) {
                return null;
            }
            $u = User::lockForUpdate()->findOrFail($c->user_id);
            $u->reserved = round(max(0, (float) $u->reserved - $amount), 2);
            $u->save();
            $c->reserved = round((float) $c->reserved - $amount, 2);
            $c->saveQuietly();
            $campaign->setRawAttributes($c->getAttributes(), true);

            return $this->record($u, TransactionType::Release, $amount, $c, $comment.' (кампания #'.$c->id.')');
        });
    }

    /** Списание за клик. Возвращает false, если в резерве кампании не хватает на клик. */
    public function charge(Campaign $campaign, float $amount, ?Model $source = null): bool
    {
        $this->assertPositive($amount);

        return DB::transaction(function () use ($campaign, $amount, $source) {
            $c = Campaign::lockForUpdate()->findOrFail($campaign->id);
            if ((float) $c->reserved + 0.001 < $amount) {
                return false;
            }
            $u = User::lockForUpdate()->findOrFail($c->user_id);
            $u->balance = round((float) $u->balance - $amount, 2);
            $u->reserved = round(max(0, (float) $u->reserved - $amount), 2);
            $u->save();
            $c->reserved = round((float) $c->reserved - $amount, 2);
            $c->spent = round((float) $c->spent + $amount, 2);
            $c->clicks = $c->clicks + 1;
            $c->saveQuietly();
            $campaign->setRawAttributes($c->getAttributes(), true);

            $this->record($u, TransactionType::Charge, -$amount, $c, 'Клик по объявлению', $source);

            return true;
        });
    }

    protected function record(User $u, TransactionType $type, float $amount, ?Campaign $campaign, ?string $comment, ?Model $source = null, ?int $by = null): Transaction
    {
        return Transaction::create([
            'user_id' => $u->id,
            'campaign_id' => $campaign?->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $u->balance,
            'reserved_after' => $u->reserved,
            'comment' => $comment,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'created_by' => $by,
        ]);
    }

    protected function assertPositive(float $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Сумма должна быть больше нуля');
        }
    }
}
