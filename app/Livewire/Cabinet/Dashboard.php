<?php

namespace App\Livewire\Cabinet;

use App\Enums\CampaignStatus;
use App\Models\ClickEvent;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.cabinet')]
#[Title('Главная')]
class Dashboard extends Component
{
    public function render()
    {
        $user = auth()->user();
        $campaignIds = $user->campaigns()->pluck('id');
        $since = now()->subDays(6)->startOfDay();

        $clicks = ClickEvent::whereIn('campaign_id', $campaignIds)->where('billable', true)->where('created_at', '>=', $since)
            ->get(['created_at', 'charged'])->groupBy(fn ($e) => $e->created_at->toDateString());

        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->toDateString())
            ->mapWithKeys(fn ($d) => [$d => ['clicks' => $clicks->get($d)?->count() ?? 0, 'spent' => (float) ($clicks->get($d)?->sum('charged') ?? 0)]]);

        return view('livewire.cabinet.dashboard', [
            'user' => $user,
            'active' => $user->campaigns()->whereIn('status', [CampaignStatus::Active, CampaignStatus::Approved, CampaignStatus::Moderation])->count(),
            'days' => $days,
            'campaigns' => $user->campaigns()->latest('id')->limit(5)->get(),
            'attention' => $user->campaigns()->whereIn('status', [CampaignStatus::NeedsChanges, CampaignStatus::PendingPayment, CampaignStatus::Draft])->latest('id')->get(),
        ]);
    }
}
