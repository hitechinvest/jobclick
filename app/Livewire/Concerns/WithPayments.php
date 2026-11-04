<?php

namespace App\Livewire\Concerns;

use App\Models\Campaign;
use App\Services\Integrations\IntegrationException;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use InvalidArgumentException;

/** Пополнение баланса: онлайн через ЮKassa или счёт для юрлица. */
trait WithPayments
{
    public function payOnline(float $amount, ?int $campaignId = null)
    {
        $campaign = $campaignId ? Campaign::where('user_id', auth()->id())->find($campaignId) : null;

        try {
            $payment = app(PaymentService::class)->start(auth()->user(), round($amount, 2), $campaign);
        } catch (IntegrationException|InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return $this->redirect(request()->header('Referer') ?: route('cabinet.finance'));
        }

        return $payment->confirmation_url
            ? redirect()->away($payment->confirmation_url)
            : redirect()->route('cabinet.finance')->with('error', 'ЮKassa не вернула ссылку на оплату');
    }

    public function issueInvoice(float $amount, ?int $campaignId = null)
    {
        $campaign = $campaignId ? Campaign::where('user_id', auth()->id())->find($campaignId) : null;

        try {
            $invoice = app(InvoiceService::class)->create(auth()->user(), round($amount, 2), $campaign);
        } catch (InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return null;
        }

        session()->flash('success', 'Счёт № '.$invoice->number.' выставлен и отправлен на почту. Скачайте его ниже.');

        return redirect()->route('cabinet.finance', ['invoice' => $invoice->id]);
    }
}
