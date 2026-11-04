<?php

namespace App\Livewire\Cabinet;

use App\Livewire\Concerns\WithPayments;
use App\Services\Integrations\YooKassaClient;
use App\Services\InvoiceService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.cabinet')]
#[Title('Финансы')]
class Finance extends Component
{
    use WithPagination, WithPayments;

    public int $amount = 10000;

    #[Url]
    public ?int $invoice = null;

    public function topupOnline()
    {
        $this->validate(['amount' => 'required|integer|min:100|max:10000000'], ['amount.min' => 'Минимум 100 ₽']);

        return $this->payOnline((float) $this->amount);
    }

    public function topupInvoice()
    {
        $this->validate(['amount' => 'required|integer|min:1000|max:10000000'], ['amount.min' => 'Счёт — от 1 000 ₽']);

        return $this->issueInvoice((float) $this->amount);
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.cabinet.finance', [
            'user' => $user,
            'invoices' => $user->invoices()->latest('id')->limit(20)->get(),
            'payments' => $user->payments()->latest('id')->limit(10)->get(),
            'transactions' => $user->transactions()->with('campaign')->latest('id')->paginate(20),
            'onlineEnabled' => app(YooKassaClient::class)->isConfigured(),
            'invoiceEnabled' => InvoiceService::requisitesReady(),
        ]);
    }
}
