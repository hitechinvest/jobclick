<?php

namespace App\Livewire\Cabinet;

use App\Enums\CampaignStatus;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.cabinet')]
#[Title('Кампании')]
class Campaigns extends Component
{
    use WithPagination;

    #[Url]
    public string $filter = 'all';

    public const FILTERS = [
        'all' => 'Все',
        'live' => 'Работают',
        'moderation' => 'На проверке',
        'drafts' => 'Черновики',
        'finished' => 'Завершены',
    ];

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $statuses = match ($this->filter) {
            'live' => [CampaignStatus::Active, CampaignStatus::Approved, CampaignStatus::Paused],
            'moderation' => [CampaignStatus::Moderation, CampaignStatus::NeedsChanges, CampaignStatus::PendingPayment],
            'drafts' => [CampaignStatus::Draft],
            'finished' => [CampaignStatus::Completed, CampaignStatus::Rejected, CampaignStatus::Cancelled],
            default => null,
        };

        return view('livewire.cabinet.campaigns', [
            'campaigns' => auth()->user()->campaigns()->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
                ->with('creatives')->latest('id')->paginate(12),
        ]);
    }
}
