<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\ClickEvent;
use Illuminate\Http\Request;

/** Клик по трекинг-ссылке: запись, дедупликация, списание, пороги 80% / 100% бюджета. */
class TrackingService
{
    public const BOT_PATTERN = '/bot|crawl|spider|slurp|curl|wget|python|httpclient|okhttp|java\/|go-http|headless|preview|facebookexternalhit|vkshare|telegrambot|whatsapp/i';

    public const DEDUP_MINUTES = 10;

    public function __construct(protected BillingService $billing, protected CampaignService $campaigns, protected AlertService $alerts) {}

    public function redirectUrl(Campaign $campaign, ?string $platform): string
    {
        $url = $campaign->landingUrl() ?: url('/');
        $utm = http_build_query(array_filter([
            'utm_source' => $platform ?: 'jobclick',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'jobclick_'.$campaign->id,
        ]));

        return $url.(str_contains($url, '?') ? '&' : '?').$utm;
    }

    public function handle(Campaign $campaign, Request $request): ClickEvent
    {
        $ua = mb_substr((string) $request->userAgent(), 0, 500);
        $ipHash = hash('sha256', $request->ip().'|'.config('app.key'));
        $platform = $request->query('p');
        $platform = is_string($platform) ? mb_substr(preg_replace('/[^a-z_]/', '', $platform), 0, 24) : null;

        $billable = $campaign->status === CampaignStatus::Active
            && $ua !== '' && ! preg_match(self::BOT_PATTERN, $ua)
            && ! ClickEvent::where('campaign_id', $campaign->id)->where('ip_hash', $ipHash)
                ->where('created_at', '>=', now()->subMinutes(self::DEDUP_MINUTES))->exists()
            && ! ($campaign->daily_budget && $campaign->spentToday() + (float) $campaign->cpc > (float) $campaign->daily_budget + 0.001);

        $event = ClickEvent::create([
            'campaign_id' => $campaign->id,
            'platform' => $platform ?: null,
            'ip_hash' => $ipHash,
            'user_agent' => $ua,
            'referer' => mb_substr((string) $request->headers->get('referer'), 0, 1000) ?: null,
            'billable' => false,
        ]);

        if ($billable && $this->billing->charge($campaign, (float) $campaign->cpc, $event)) {
            $event->update(['billable' => true, 'charged' => $campaign->cpc]);
            $this->checkThresholds($campaign->fresh());
        }

        return $event;
    }

    public function checkThresholds(Campaign $campaign): void
    {
        $budget = (float) $campaign->total_budget;
        if ($budget <= 0) {
            return;
        }

        if (! $campaign->alert_80_at && (float) $campaign->spent >= $budget * 0.8) {
            $campaign->update(['alert_80_at' => now()]);
            $campaign->log('budget_80');
            $this->alerts->raise('budget_80', 'Израсходовано 80% бюджета «'.$campaign->displayTitle().'»', $campaign);
            $this->campaigns->notify($campaign, 'budget_80');
        }

        if ((float) $campaign->reserved < (float) $campaign->cpc) {
            $this->campaigns->complete($campaign, 'budget');
        }
    }
}
