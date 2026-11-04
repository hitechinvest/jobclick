<?php

namespace App\Filament\Widgets;

use App\Enums\CampaignStatus;
use App\Models\AdminAlert;
use App\Models\Campaign;
use App\Models\ClickEvent;
use App\Models\Invoice;
use App\Models\NetworkStat;
use App\Models\Transaction;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $month = now()->startOfMonth();
        $revenue = -1 * (float) Transaction::where('type', 'charge')->where('created_at', '>=', $month)->sum('amount');
        $networkSpend = (float) NetworkStat::where('date', '>=', $month->toDateString())->sum('spend');

        return [
            Stat::make('Требуют действия', AdminAlert::whereNull('resolved_at')->count())->color('danger')
                ->url(route('filament.admin.resources.admin-alerts.index')),
            Stat::make('На модерации', Campaign::where('status', CampaignStatus::Moderation)->count())->color('warning')
                ->description('ждут размещения: '.Campaign::where('status', CampaignStatus::Approved)->count()),
            Stat::make('В показах', Campaign::whereIn('status', [CampaignStatus::Active, CampaignStatus::Paused])->count())
                ->description('кликов сегодня: '.ClickEvent::where('billable', true)->where('created_at', '>=', now()->startOfDay())->count()),
            Stat::make('Выручка за месяц', Money::format($revenue))->color('success')
                ->description('расход в сетях: '.Money::format($networkSpend).' · маржа '.Money::format($revenue - $networkSpend)),
            Stat::make('Неоплаченные счета', Invoice::where('status', 'issued')->count())
                ->description(Money::format(Invoice::where('status', 'issued')->sum('amount'))),
        ];
    }
}
