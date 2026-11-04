<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BillingService;
use App\Services\InsufficientFundsException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserve_charge_release(): void
    {
        $billing = app(BillingService::class);
        $campaign = $this->readyCampaign(['total_budget' => 3000, 'cpc' => 10]);
        $user = $campaign->user;

        $billing->topup($user, 5000);
        $billing->reserve($campaign, 3000);
        $this->assertEquals(2000, $user->fresh()->available());

        $this->assertTrue($billing->charge($campaign, 10));
        $campaign->refresh();
        $this->assertEquals(2990, (float) $campaign->reserved);
        $this->assertEquals(10, (float) $campaign->spent);
        $this->assertSame(1, $campaign->clicks);
        $this->assertEquals(4990, (float) $user->fresh()->balance);

        $billing->release($campaign);
        $this->assertEquals(0, (float) $campaign->fresh()->reserved);
        $this->assertEquals(4990, $user->fresh()->available());
        $this->assertSame(4, $user->transactions()->count());
    }

    public function test_cannot_reserve_more_than_available(): void
    {
        $campaign = $this->readyCampaign();
        app(BillingService::class)->topup($campaign->user, 1000);

        $this->expectException(InsufficientFundsException::class);
        app(BillingService::class)->reserve($campaign, 3000);
    }

    public function test_charge_fails_when_campaign_reserve_is_empty(): void
    {
        $campaign = $this->readyCampaign();
        $this->assertFalse(app(BillingService::class)->charge($campaign, 10));
        $this->assertEquals(0, (float) User::find($campaign->user_id)->balance);
    }
}
