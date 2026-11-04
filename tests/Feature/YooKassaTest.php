<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class YooKassaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Settings::setMany(['yookassa.shop_id' => '123456', 'yookassa.secret_key' => 'test_secret', 'requisites.tax_system_code' => 2]);
    }

    public function test_payment_created_with_receipt_without_vat(): void
    {
        Http::fake(['api.yookassa.ru/v3/payments' => Http::response([
            'id' => 'pay-1', 'status' => 'pending', 'confirmation' => ['confirmation_url' => 'https://yoomoney.ru/checkout/pay-1'],
        ])]);
        $campaign = $this->readyCampaign();

        $payment = app(PaymentService::class)->start($campaign->user, 3000, $campaign);

        $this->assertSame('pay-1', $payment->provider_id);
        $this->assertSame('https://yoomoney.ru/checkout/pay-1', $payment->confirmation_url);
        Http::assertSent(function ($request) {
            $receipt = $request->data()['receipt'];

            return $request->hasHeader('Idempotence-Key')
                && $receipt['tax_system_code'] === 2
                && $receipt['items'][0]['vat_code'] === 1
                && $receipt['items'][0]['payment_subject'] === 'service'
                && $request->data()['amount']['value'] === '3000.00';
        });
    }

    public function test_webhook_credits_once_after_api_confirmation(): void
    {
        Http::fake(['api.yookassa.ru/v3/payments/pay-2' => Http::response(['id' => 'pay-2', 'status' => 'succeeded', 'paid' => true, 'amount' => ['value' => '5000.00', 'currency' => 'RUB']])]);
        $campaign = $this->readyCampaign();
        $payment = Payment::create(['user_id' => $campaign->user_id, 'amount' => 5000, 'provider_id' => 'pay-2']);

        $body = ['type' => 'notification', 'event' => 'payment.succeeded', 'object' => ['id' => 'pay-2', 'status' => 'succeeded']];
        $this->postJson('/webhooks/yookassa', $body)->assertOk();
        $this->postJson('/webhooks/yookassa', $body)->assertOk();

        $this->assertSame('succeeded', $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->credited_at);
        $this->assertEquals(5000, (float) $campaign->user->fresh()->balance);
        $this->assertSame(1, $campaign->user->transactions()->where('type', 'topup')->count());
    }

    public function test_webhook_with_fake_body_does_not_credit_if_api_says_pending(): void
    {
        Http::fake(['api.yookassa.ru/v3/payments/pay-3' => Http::response(['id' => 'pay-3', 'status' => 'pending'])]);
        $campaign = $this->readyCampaign();
        Payment::create(['user_id' => $campaign->user_id, 'amount' => 5000, 'provider_id' => 'pay-3']);

        $this->postJson('/webhooks/yookassa', ['event' => 'payment.succeeded', 'object' => ['id' => 'pay-3', 'status' => 'succeeded']])->assertOk();

        $this->assertEquals(0, (float) $campaign->user->fresh()->balance);
    }
}
