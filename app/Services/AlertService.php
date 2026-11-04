<?php

namespace App\Services;

use App\Mail\AdminAlertMail;
use App\Models\AdminAlert;
use App\Models\Campaign;
use App\Support\Settings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Алерты админу: запись в админке («Требуют действия») + письмо на адреса из настроек. */
class AlertService
{
    public function raise(string $type, string $message, ?Campaign $campaign = null, ?string $url = null): AdminAlert
    {
        $alert = AdminAlert::create([
            'type' => $type,
            'message' => $message,
            'campaign_id' => $campaign?->id,
            'url' => $url ?? ($campaign ? url('/admin/campaigns/'.$campaign->id) : null),
        ]);

        foreach (Settings::adminEmails() as $email) {
            try {
                Mail::to($email)->queue(new AdminAlertMail($alert));
            } catch (Throwable $e) {
                Log::warning('Admin alert mail failed', ['error' => $e->getMessage()]);
            }
        }

        return $alert;
    }

    public function resolveFor(Campaign $campaign, array $types): void
    {
        AdminAlert::where('campaign_id', $campaign->id)->whereIn('type', $types)->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }
}
