<?php

namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Mail\InvoiceMail;
use App\Models\Campaign;
use App\Models\Invoice;
use App\Services\CampaignService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/** Письмо с результатами через 3 дня после запуска и напоминание по неоплаченным счетам. */
class SendReports extends Command
{
    protected $signature = 'jobclick:send-reports';

    protected $description = 'Отчёт через 3 дня после старта и напоминания по счетам';

    public function handle(CampaignService $campaigns): int
    {
        Campaign::where('status', CampaignStatus::Active)
            ->whereBetween('started_at', [now()->subDays(4), now()->subDays(3)])
            ->each(function (Campaign $campaign) use ($campaigns) {
                if (Cache::add('report3:'.$campaign->id, 1, now()->addDays(10))) {
                    $campaigns->notify($campaign, 'report');
                }
            });

        Invoice::where('status', 'issued')->whereNull('reminded_at')->where('created_at', '<', now()->subDays(3))
            ->each(function (Invoice $invoice) {
                Mail::to($invoice->user->email)->queue(new InvoiceMail($invoice));
                $invoice->update(['reminded_at' => now()]);
            });

        return self::SUCCESS;
    }
}
