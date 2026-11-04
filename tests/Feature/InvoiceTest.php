<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Services\CampaignService;
use App\Services\InvoiceService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Settings::setMany([
            'requisites.name' => 'ООО «Джобклик»', 'requisites.inn' => '7700000000', 'requisites.kpp' => '770001001',
            'requisites.bank_name' => 'АО «ТБанк»', 'requisites.bik' => '044525974',
            'requisites.account' => '40702810010000000001', 'requisites.corr_account' => '30101810145250000974',
        ]);
    }

    public function test_qr_payload_follows_gost_format(): void
    {
        $campaign = $this->readyCampaign();
        $invoice = app(InvoiceService::class)->create($campaign->user, 12500.5, $campaign);

        $payload = InvoiceService::qrPayload($invoice);
        $this->assertStringStartsWith('ST00012|Name=ООО «Джобклик»|PersonalAcc=40702810010000000001|', $payload);
        $this->assertStringContainsString('|BIC=044525974|', $payload);
        $this->assertStringContainsString('|Sum=1250050|', $payload);
        $this->assertStringContainsString('Без НДС', $payload);
        $this->assertStringContainsString('|PayerINN=7707083893', $payload);
        $this->assertMatchesRegularExpression('/^JC-\d{4}-00001$/', $invoice->number);
    }

    public function test_pdf_renders(): void
    {
        $campaign = $this->readyCampaign();
        $invoice = app(InvoiceService::class)->create($campaign->user, 3000);

        $this->assertStringStartsWith('%PDF', app(InvoiceService::class)->pdf($invoice));
        $this->actingAs($campaign->user)->get(route('cabinet.invoices.pdf', $invoice))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_mark_paid_credits_balance_and_submits_waiting_campaign(): void
    {
        $campaign = $this->readyCampaign(['total_budget' => 3000]);
        app(CampaignService::class)->submit($campaign);
        $invoice = app(InvoiceService::class)->create($campaign->user, 3000, $campaign);

        app(InvoiceService::class)->markPaid($invoice, 'п/п 15 от 05.11');

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertEquals(3000, (float) $campaign->user->fresh()->balance);
        $this->assertSame(CampaignStatus::Moderation, $campaign->fresh()->status);
    }

    public function test_invoice_requires_requisites(): void
    {
        Settings::set('requisites.account', null);
        $campaign = $this->readyCampaign();
        $this->expectException(\InvalidArgumentException::class);
        app(InvoiceService::class)->create($campaign->user, 3000);
    }
}
