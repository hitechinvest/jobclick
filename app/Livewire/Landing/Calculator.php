<?php

namespace App\Livewire\Landing;

use App\Livewire\Concerns\WithRegionPicker;
use App\Models\Region;
use App\Services\ForecastService;
use App\Support\Settings;
use Livewire\Component;

/** Калькулятор на главной: название вакансии + город + бюджет → спрос из Вордстата и прогноз кликов. */
class Calculator extends Component
{
    use WithRegionPicker;

    public string $title = '';

    public int $budget = 10000;

    public ?array $result = null;

    public function mount(): void
    {
        $saved = session('calc', []);
        $this->title = $saved['title'] ?? '';
        $this->budget = (int) ($saved['budget'] ?? 10000);
        $this->regionIds = $saved['region_ids'] ?? [Region::MOSCOW];
    }

    public function calculate(ForecastService $forecast): void
    {
        $this->validate([
            'title' => 'required|string|min:2|max:100',
            'budget' => 'required|integer|min:'.(int) Settings::get('tariff.min_total_budget').'|max:10000000',
            'regionIds' => 'required|array|min:1',
        ], [
            'title.required' => 'Напишите, кого ищете — например, «курьер»',
            'regionIds.required' => 'Выберите город',
            'budget.min' => 'Минимальный бюджет — :min ₽',
        ]);

        $this->result = $forecast->forecast($this->title, $this->regionIds, $this->budget);
        session(['calc' => ['title' => $this->title, 'budget' => $this->budget, 'region_ids' => $this->regionIds]]);
    }

    public function updatedBudget(): void
    {
        if ($this->result && $this->budget >= (int) Settings::get('tariff.min_total_budget')) {
            $this->calculate(app(ForecastService::class));
        }
    }

    protected function regionsChanged(): void
    {
        $this->result = null;
    }

    public function start()
    {
        session(['calc' => ['title' => $this->title, 'budget' => $this->budget, 'region_ids' => $this->regionIds]]);

        return redirect()->route(auth()->check() ? 'cabinet.campaigns.create' : 'login');
    }

    public function render()
    {
        return view('livewire.landing.calculator', [
            'regions' => $this->selectedRegions(),
            'cpcPremium' => Settings::float('tariff.cpc_premium'),
            'cpcDefault' => Settings::float('tariff.cpc_default'),
        ]);
    }
}
