<?php

namespace App\Livewire\Cabinet;

use App\Enums\CampaignStatus;
use App\Livewire\Concerns\WithPayments;
use App\Models\Campaign;
use App\Services\CampaignService;
use App\Services\InsufficientFundsException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.cabinet')]
class CampaignShow extends Component
{
    use WithPayments;

    public Campaign $campaign;

    public int $addAmount = 3000;

    public function mount(Campaign $campaign): void
    {
        abort_unless($campaign->user_id === auth()->id(), 404);
        $this->campaign = $campaign;
    }

    protected function act(callable $action, string $success): void
    {
        try {
            $action(app(CampaignService::class));
            session()->flash('success', $success);
        } catch (InsufficientFundsException $e) {
            session()->flash('error', $e->getMessage().'. Пополните баланс.');
        } catch (InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }
        $this->campaign->refresh();
    }

    public function pause(): void
    {
        $this->act(fn ($s) => $s->pause($this->campaign), 'Кампания поставлена на паузу — остановим показы в течение пары часов.');
    }

    public function resume(): void
    {
        $this->act(fn ($s) => $s->resume($this->campaign), 'Показы возобновятся в ближайшее время.');
    }

    public function stop(): void
    {
        $this->act(fn ($s) => $s->cancel($this->campaign), 'Кампания остановлена, остаток бюджета вернулся на баланс.');
    }

    public function retry(): void
    {
        $this->act(function ($s) {
            $missing = $s->submit($this->campaign);
            if ($missing > 0) {
                throw new InvalidArgumentException('На балансе не хватает '.number_format($missing, 2, ',', ' ').' ₽');
            }
        }, 'Кампания отправлена на модерацию!');
    }

    public function addBudget(): void
    {
        $this->validate(['addAmount' => 'required|integer|min:500|max:10000000']);
        $this->act(fn ($s) => $s->addBudget($this->campaign, (float) $this->addAmount), 'Бюджет кампании увеличен.');
    }

    public function render()
    {
        $c = $this->campaign;
        $since = now()->subDays(13)->startOfDay();
        $clicks = $c->clickEvents()->where('billable', true)->where('created_at', '>=', $since)->get(['created_at', 'platform']);
        $byDay = collect(range(13, 0))->map(fn ($i) => now()->subDays($i)->toDateString())
            ->mapWithKeys(fn ($d) => [$d => $clicks->filter(fn ($e) => $e->created_at->toDateString() === $d)->count()]);

        $missing = $c->status === CampaignStatus::PendingPayment
            ? max(0, round((float) $c->total_budget - (float) $c->reserved - auth()->user()->available(), 2)) : 0;

        return view('livewire.cabinet.campaign-show', [
            'byDay' => $byDay,
            'byPlatform' => $c->clickEvents()->where('billable', true)->selectRaw('platform, count(*) as cnt')->groupBy('platform')->pluck('cnt', 'platform'),
            'missing' => $missing,
            'creatives' => $c->creatives()->get()->groupBy('image_id'),
        ])->title($c->displayTitle());
    }
}
