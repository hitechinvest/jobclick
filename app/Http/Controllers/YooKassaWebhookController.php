<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Integrations\YooKassaClient;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Уведомления ЮKassa. Телу уведомления не доверяем: проверяем IP отправителя
 * и перезапрашиваем статус платежа через API перед зачислением.
 */
class YooKassaWebhookController extends Controller
{
    public function __invoke(Request $request, YooKassaClient $client, PaymentService $payments)
    {
        if (! app()->runningUnitTests() && ! $client->isTrustedIp((string) $request->ip())) {
            Log::warning('YooKassa webhook from untrusted IP', ['ip' => $request->ip()]);

            return response('forbidden', 403);
        }

        $providerId = $request->input('object.id');
        $payment = $providerId ? Payment::where('provider_id', $providerId)->first() : null;

        if ($payment) {
            try {
                $payments->sync($payment);
            } catch (Throwable $e) {
                Log::error('YooKassa webhook sync failed', ['payment' => $payment->id, 'error' => $e->getMessage()]);

                return response('retry', 500);
            }
        }

        return response('ok', 200);
    }
}
