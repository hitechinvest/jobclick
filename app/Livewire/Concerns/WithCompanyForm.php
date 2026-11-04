<?php

namespace App\Livewire\Concerns;

use App\Enums\CampaignStatus;
use App\Models\Company;
use App\Services\Integrations\DaDataClient;
use Livewire\Attributes\Renderless;

/** Реквизиты рекламодателя по ИНН с подсказками DaData. */
trait WithCompanyForm
{
    public array $company = [
        'inn' => '', 'kpp' => '', 'ogrn' => '', 'type' => 'LEGAL', 'name_full' => '', 'name_short' => '',
        'legal_address' => '', 'okved' => '', 'management_name' => '', 'state' => null,
    ];

    public function loadCompany(): void
    {
        if ($existing = auth()->user()->company) {
            $this->company = array_merge($this->company, $existing->only(array_keys($this->company)));
            $this->company = array_map(fn ($v) => $v ?? '', $this->company);
        }
    }

    #[Renderless]
    public function suggestCompany(string $query): array
    {
        return collect(app(DaDataClient::class)->suggestParty($query))
            ->map(fn ($s) => collect($s)->except('raw')->all())->all();
    }

    public function pickCompany(array $data): void
    {
        foreach (array_keys($this->company) as $key) {
            if (array_key_exists($key, $data)) {
                $this->company[$key] = $data[$key] ?? '';
            }
        }
    }

    protected function companyRules(): array
    {
        return [
            'company.inn' => ['required', 'regex:/^(\d{10}|\d{12})$/'],
            'company.kpp' => ['nullable', 'regex:/^\d{9}$/'],
            'company.ogrn' => ['nullable', 'regex:/^(\d{13}|\d{15})$/'],
            'company.name_full' => ['required', 'string', 'max:500'],
            'company.legal_address' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function companyMessages(): array
    {
        return [
            'company.inn.required' => 'Укажите ИНН',
            'company.inn.regex' => 'ИНН — 10 цифр для организации или 12 для ИП',
            'company.kpp.regex' => 'КПП — 9 цифр',
            'company.ogrn.regex' => 'ОГРН — 13 цифр (ОГРНИП — 15)',
            'company.name_full.required' => 'Укажите название организации',
        ];
    }

    public function saveCompany(): ?Company
    {
        $this->validate($this->companyRules(), $this->companyMessages());
        $user = auth()->user();
        $current = $user->company;

        if ($current && $current->inn !== $this->company['inn']
            && $user->campaigns()->whereIn('status', [CampaignStatus::Approved, CampaignStatus::Active, CampaignStatus::Paused, CampaignStatus::Moderation])->exists()) {
            $this->addError('company.inn', 'ИНН нельзя сменить, пока есть кампании на модерации или в показах');

            return null;
        }

        $data = collect($this->company)->map(fn ($v) => $v === '' ? null : $v)->all();
        $data['type'] = strlen((string) $data['inn']) === 12 ? 'INDIVIDUAL' : 'LEGAL';
        $data['name_short'] ??= $data['name_full'];

        return Company::updateOrCreate(['user_id' => $user->id], $data);
    }
}
