<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentService;
use Throwable;

class PaymentReturnController extends Controller
{
    public function __invoke(Payment $payment, PaymentService $payments)
    {
        abort_unless($payment->user_id === auth()->id(), 404);

        try {
            $payment = $payments->sync($payment);
        } catch (Throwable) {
            // статус придёт вебхуком
        }

        $message = match ($payment->status) {
            'succeeded' => 'Оплата прошла, деньги зачислены на баланс',
            'canceled' => 'Оплата отменена',
            default => 'Ждём подтверждения оплаты от банка — обычно это пара минут',
        };

        $target = $payment->campaign_id ? route('cabinet.campaigns.show', $payment->campaign_id) : route('cabinet.finance');

        return redirect($target)->with($payment->status === 'canceled' ? 'error' : 'success', $message);
    }
}
