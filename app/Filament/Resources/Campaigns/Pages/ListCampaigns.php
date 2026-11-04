<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Enums\CampaignStatus;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Models\Campaign;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListCampaigns extends ListRecords
{
    protected static string $resource = CampaignResource::class;

    public function getTabs(): array
    {
        $tab = fn (string $label, array $statuses) => Tab::make($label)
            ->badge(Campaign::whereIn('status', $statuses)->count() ?: null)
            ->modifyQueryUsing(fn ($query) => $query->whereIn('status', $statuses));

        return [
            'moderation' => $tab('На модерации', [CampaignStatus::Moderation])->badgeColor('warning'),
            'placement' => $tab('Ждут размещения', [CampaignStatus::Approved])->badgeColor('info'),
            'live' => $tab('В показах', [CampaignStatus::Active, CampaignStatus::Paused]),
            'payment' => $tab('Ждут оплаты', [CampaignStatus::PendingPayment, CampaignStatus::NeedsChanges]),
            'all' => Tab::make('Все'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return Campaign::where('status', CampaignStatus::Moderation)->exists() ? 'moderation' : 'all';
    }
}
