<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Payment;
use App\Models\User;
use App\Services\Integrations\YooKassaClient;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Онлайн-оплата через ЮKassa. Зачисление — только после подтверждения статуса запросом к API. */
class PaymentService
{
    public function __construct(
        protected YooKassaClient $client,
        protected BillingService $billing,
        protected CampaignService $campaigns,
        protected AlertService $alerts,
    ) {}

    public function start(User $user, float $amount, ?Campaign $campaign = null): Payment
    {
        if ($amount < 1) {
            throw new InvalidArgumentException('Сумма пополнения должна быть больше нуля');
        }

        $payment = Payment::create(['user_id' => $user->id, 'campaign_id' => $campaign?->id, 'amount' => round($amount, 2)]);
        $returnUrl = route('payments.return', $payment);
        $response = $this->client->createPayment($payment, $returnUrl, $user->email);

        $payment->update([
            'provider_id' => $response['id'] ?? null,
            'status' => $response['status'] ?? 'pending',
            'confirmation_url' => $response['confirmation']['confirmation_url'] ?? null,
            'raw' => $response,
        ]);

        return $payment;
    }

    /** Синхронизация со статусом в ЮKassa (webhook, возврат пользователя, плановая сверка). */
    public function sync(Payment $payment): Payment
    {
        if (! $payment->provider_id || $payment->credited_at || $payment->status === 'canceled') {
            return $payment;
        }

        $remote = $this->client->getPayment($payment->provider_id);
        $status = $remote['status'] ?? 'pending';
        $paid = $status === 'succeeded' && ($remote['paid'] ?? true);

        $credited = DB::transaction(function () use ($payment, $remote, $status, $paid) {
            $p = Payment::lockForUpdate()->findOrFail($payment->id);
            $p->status = in_array($status, ['succeeded', 'canceled'], true) ? $status : 'pending';
            $p->raw = $remote;

            if ($paid && ! $p->credited_at) {
                $amount = (float) ($remote['amount']['value'] ?? $p->amount);
                $p->credited_at = now();
                $p->save();
                $this->billing->topup($p->user, $amount, $p, 'Онлайн-оплата ЮKassa');

                return true;
            }
            $p->save();

            return false;
        });

        $payment->refresh();
        if ($credited) {
            $this->alerts->raise('payment', 'Онлайн-оплата '.Money::format($payment->amount).' от '.$payment->user->displayName(), $payment->campaign,
                url('/admin/payments'));
            $this->campaigns->processPendingPayments($payment->user);
        }

        return $payment;
    }
}
