<?php

namespace App\Services\Integrations;

use App\Models\Payment;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use YooKassa\Client as SdkClient;

/** ЮKassa REST API v3: платежи с чеками 54-ФЗ (без НДС, система налогообложения — из админки). */
class YooKassaClient
{
    protected const BASE = 'https://api.yookassa.ru/v3';

    public function isConfigured(): bool
    {
        return Settings::has('yookassa.shop_id') && Settings::has('yookassa.secret_key');
    }

    /** Создаёт платёж и возвращает ответ ЮKassa (id, status, confirmation.confirmation_url). */
    public function createPayment(Payment $payment, string $returnUrl, string $customerEmail): array
    {
        if (! $this->isConfigured()) {
            throw IntegrationException::notConfigured('ЮKassa');
        }

        $amount = ['value' => number_format((float) $payment->amount, 2, '.', ''), 'currency' => 'RUB'];
        $description = 'Пополнение баланса JobClick'.($payment->campaign_id ? ', кампания #'.$payment->campaign_id : '');

        $body = [
            'amount' => $amount,
            'capture' => true,
            'confirmation' => ['type' => 'redirect', 'return_url' => $returnUrl],
            'description' => mb_substr($description, 0, 128),
            'metadata' => ['payment_id' => (string) $payment->id, 'user_id' => (string) $payment->user_id],
        ];

        if (Settings::bool('yookassa.receipts')) {
            $body['receipt'] = [
                'customer' => ['email' => $customerEmail],
                'tax_system_code' => Settings::int('requisites.tax_system_code'),
                'items' => [[
                    'description' => 'Рекламные услуги (пополнение баланса)',
                    'quantity' => '1.00',
                    'amount' => $amount,
                    'vat_code' => Settings::int('requisites.vat_code'),
                    'payment_mode' => 'full_prepayment',
                    'payment_subject' => 'service',
                ]],
            ];
        }

        return $this->request('post', '/payments', $body, 'payment-'.$payment->id.'-'.Str::random(8));
    }

    public function getPayment(string $providerId): array
    {
        return $this->request('get', '/payments/'.$providerId);
    }

    public function isTrustedIp(string $ip): bool
    {
        try {
            return (new SdkClient)->isNotificationIPTrusted($ip);
        } catch (Throwable) {
            return false;
        }
    }

    protected function request(string $method, string $path, array $body = [], ?string $idempotenceKey = null): array
    {
        try {
            $http = Http::withBasicAuth((string) Settings::get('yookassa.shop_id'), (string) Settings::get('yookassa.secret_key'))
                ->acceptJson()->timeout(20);
            if ($idempotenceKey) {
                $http = $http->withHeaders(['Idempotence-Key' => $idempotenceKey]);
            }
            $response = $method === 'get' ? $http->get(self::BASE.$path) : $http->post(self::BASE.$path, $body);
        } catch (Throwable $e) {
            Log::error('YooKassa request failed', ['error' => $e->getMessage()]);
            throw new IntegrationException('ЮKassa недоступна, попробуйте позже');
        }

        if (! $response->successful()) {
            Log::error('YooKassa error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new IntegrationException('ЮKassa: '.($response->json('description') ?: 'ошибка '.$response->status()));
        }

        return $response->json();
    }
}
