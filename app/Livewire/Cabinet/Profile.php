<?php

namespace App\Livewire\Cabinet;

use App\Http\Controllers\Auth\AuthController;
use App\Livewire\Concerns\WithCompanyForm;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.cabinet')]
#[Title('Профиль')]
class Profile extends Component
{
    use WithCompanyForm;

    public string $name = '';

    public function mount(): void
    {
        $this->name = (string) auth()->user()->name;
        $this->loadCompany();
    }

    public function saveName(): void
    {
        $this->validate(['name' => 'nullable|string|max:100']);
        auth()->user()->update(['name' => $this->name ?: null]);
        session()->flash('success', 'Сохранено');
    }

    public function save(): void
    {
        if ($this->saveCompany()) {
            session()->flash('success', 'Реквизиты компании сохранены');
        }
    }

    public function render()
    {
        return view('livewire.cabinet.profile', [
            'user' => auth()->user(),
            'providers' => AuthController::PROVIDERS,
        ]);
    }
}
