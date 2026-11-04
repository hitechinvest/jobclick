<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Mail\CampaignStatusMail;
use App\Models\AdminAlert;
use App\Models\User;
use App\Services\BillingService;
use App\Services\CampaignService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CampaignFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_lifecycle_from_payment_to_budget_exhaustion(): void
    {
        Mail::fake();
        $this->seedRegions();
        $service = app(CampaignService::class);
        $campaign = $this->readyCampaign(['total_budget' => 3000]);

        // Денег нет — ждём оплату
        $missing = $service->submit($campaign);
        $this->assertEquals(3000, $missing);
        $this->assertSame(CampaignStatus::PendingPayment, $campaign->fresh()->status);

        // Пополнение → автоматически на модерацию
        app(BillingService::class)->topup($campaign->user, 3000);
        $service->processPendingPayments($campaign->user->fresh());
        $campaign->refresh();
        $this->assertSame(CampaignStatus::Moderation, $campaign->status);
        $this->assertEquals(3000, (float) $campaign->reserved);
        $this->assertEquals(10, (float) $campaign->cpc);
        $this->assertTrue(AdminAlert::where('type', 'moderation')->whereNull('resolved_at')->exists());

        // Модерация → площадки ждут размещения
        $service->approve($campaign);
        $this->assertSame(CampaignStatus::Approved, $campaign->fresh()->status);
        $this->assertSame(2, $campaign->placements()->count());

        // Админ разместил обе площадки → показы
        foreach ($campaign->placements as $placement) {
            $service->markPlaced($placement, ['external_id' => '123', 'erid' => 'ERID'.$placement->id]);
        }
        $this->assertSame(CampaignStatus::Active, $campaign->fresh()->status);

        // Клики: первый оплачивается, повтор с того же IP и бот — нет
        $ua = ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/130'];
        $this->get('/go/'.$campaign->slug.'?p=vkontakte', $ua)->assertRedirectContains('utm_campaign=jobclick_'.$campaign->id);
        $this->get('/go/'.$campaign->slug, $ua)->assertRedirect();
        $this->get('/go/'.$campaign->slug, ['HTTP_USER_AGENT' => 'Googlebot/2.1'])->assertRedirect();
        $campaign->refresh();
        $this->assertSame(1, $campaign->clicks);
        $this->assertEquals(10, (float) $campaign->spent);
        $this->assertSame(3, $campaign->clickEvents()->count());

        // Исчерпание бюджета: 299 кликов с разных IP
        for ($i = 2; $i <= 300; $i++) {
            $this->get('/go/'.$campaign->slug, $ua + ['REMOTE_ADDR' => '10.0.'.intdiv($i, 250).'.'.($i % 250)]);
        }
        $campaign->refresh();
        $this->assertSame(CampaignStatus::Completed, $campaign->status);
        $this->assertSame('budget', $campaign->stop_reason);
        $this->assertEquals(3000, (float) $campaign->spent);
        $this->assertEquals(0, (float) $campaign->user->fresh()->balance);
        $this->assertTrue(AdminAlert::where('type', 'budget_100')->exists());
        $this->assertTrue(AdminAlert::where('type', 'budget_80')->exists());
        Mail::assertQueued(CampaignStatusMail::class, fn ($m) => $m->type === 'started');
    }

    public function test_reject_returns_money_to_balance(): void
    {
        Mail::fake();
        $campaign = $this->readyCampaign();
        app(BillingService::class)->topup($campaign->user, 3000);
        app(CampaignService::class)->submit($campaign);

        app(CampaignService::class)->reject($campaign->fresh(), 'Дискриминационные требования');

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Rejected, $campaign->status);
        $this->assertEquals(0, (float) $campaign->reserved);
        $this->assertEquals(3000, $campaign->user->fresh()->available());
    }

    public function test_needs_changes_resubmit_does_not_charge_twice(): void
    {
        Mail::fake();
        $campaign = $this->readyCampaign();
        app(BillingService::class)->topup($campaign->user, 3000);
        $service = app(CampaignService::class);
        $service->submit($campaign);
        $service->requestChanges($campaign->fresh(), 'Уберите возраст из текста');

        $this->assertEquals(0, $service->submit($campaign->fresh()));
        $this->assertSame(CampaignStatus::Moderation, $campaign->fresh()->status);
        $this->assertEquals(3000, (float) $campaign->user->fresh()->reserved);
    }

    public function test_daily_limit_stops_billing_for_today(): void
    {
        Mail::fake();
        Settings::set('tariff.min_daily_budget', 10);
        $campaign = $this->readyCampaign(['total_budget' => 3000, 'daily_budget' => 20]);
        app(BillingService::class)->topup($campaign->user, 3000);
        $service = app(CampaignService::class);
        $service->submit($campaign);
        $service->approve($campaign->fresh());
        foreach ($campaign->placements as $p) {
            $service->markPlaced($p, ['erid' => 'x']);
        }

        foreach (range(1, 5) as $i) {
            $this->get('/go/'.$campaign->slug, ['HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome', 'REMOTE_ADDR' => '10.1.0.'.$i]);
        }
        $this->assertSame(2, $campaign->fresh()->clicks);
    }

    public function test_user_cannot_open_foreign_campaign(): void
    {
        $campaign = $this->readyCampaign();
        $other = User::factory()->create();
        $this->actingAs($other)->get('/cabinet/campaigns/'.$campaign->id)->assertNotFound();
    }
}
