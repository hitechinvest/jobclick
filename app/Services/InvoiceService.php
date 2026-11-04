<?php

namespace App\Services;

use App\Mail\InvoiceMail;
use App\Models\Campaign;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Money;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/** Счета на оплату для юрлиц: PDF с обязательным QR-кодом по ГОСТ Р 56042-2014, без НДС. */
class InvoiceService
{
    public function __construct(
        protected BillingService $billing,
        protected CampaignService $campaigns,
        protected AlertService $alerts,
    ) {}

    public static function payee(): array
    {
        $keys = ['name', 'inn', 'kpp', 'ogrn', 'address', 'bank_name', 'bik', 'account', 'corr_account', 'director', 'accountant', 'phone', 'email'];

        return collect($keys)->mapWithKeys(fn ($k) => [$k => Settings::get('requisites.'.$k)])->all();
    }

    public static function requisitesReady(): bool
    {
        $p = static::payee();

        return filled($p['name']) && filled($p['inn']) && filled($p['bik']) && filled($p['account']) && filled($p['bank_name']);
    }

    public function create(User $user, float $amount, ?Campaign $campaign = null): Invoice
    {
        if (! $user->company) {
            throw new InvalidArgumentException('Сначала укажите ИНН компании');
        }
        if (! static::requisitesReady()) {
            throw new InvalidArgumentException('Выставление счетов временно недоступно: не заполнены реквизиты получателя');
        }
        if ($amount < 1) {
            throw new InvalidArgumentException('Сумма счёта должна быть больше нуля');
        }

        $invoice = DB::transaction(function () use ($user, $amount, $campaign) {
            $prefix = Settings::get('requisites.invoice_prefix') ?: 'JC';
            $year = now()->format('Y');
            $seq = Invoice::where('number', 'like', $prefix.'-'.$year.'-%')->lockForUpdate()->count() + 1;

            return Invoice::create([
                'user_id' => $user->id,
                'campaign_id' => $campaign?->id,
                'number' => sprintf('%s-%s-%05d', $prefix, $year, $seq),
                'amount' => round($amount, 2),
                'status' => 'issued',
                'payer' => $user->company->payerSnapshot(),
                'payee' => static::payee(),
                'due_date' => now()->addWeekdays(Settings::int('requisites.invoice_due_days') ?: 5)->toDateString(),
            ]);
        });

        $this->alerts->raise('invoice', 'Выставлен счёт '.$invoice->number.' на '.Money::format($invoice->amount).' ('.$user->company->displayName().')',
            $campaign, url('/admin/invoices/'.$invoice->id));

        try {
            Mail::to($user->email)->queue(new InvoiceMail($invoice));
        } catch (Throwable $e) {
            Log::warning('Invoice mail failed', ['error' => $e->getMessage()]);
        }

        return $invoice;
    }

    /** Строка для QR-кода платёжного документа (ГОСТ Р 56042-2014, UTF-8). */
    public static function qrPayload(Invoice $invoice): string
    {
        $p = $invoice->payee;
        $clean = fn ($v) => trim(str_replace(['|', "\n", "\r"], ' ', (string) $v));

        $fields = [
            'Name' => $p['name'] ?? '',
            'PersonalAcc' => $p['account'] ?? '',
            'BankName' => $p['bank_name'] ?? '',
            'BIC' => $p['bik'] ?? '',
            'CorrespAcc' => $p['corr_account'] ?? '',
            'PayeeINN' => $p['inn'] ?? '',
            'KPP' => $p['kpp'] ?? '',
            'Sum' => (string) (int) round((float) $invoice->amount * 100),
            'Purpose' => $invoice->purpose(),
            'PayerINN' => $invoice->payer['inn'] ?? '',
        ];

        return 'ST00012|'.collect($fields)->filter(fn ($v) => $clean($v) !== '')
            ->map(fn ($v, $k) => $k.'='.$clean($v))->join('|');
    }

    public static function qrDataUri(Invoice $invoice): string
    {
        $qr = new QrCode(data: static::qrPayload($invoice), errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: 260, margin: 8);

        return (new PngWriter)->write($qr)->getDataUri();
    }

    public function pdf(Invoice $invoice): string
    {
        $logo = Settings::get('requisites.logo');
        $stamp = Settings::get('requisites.stamp');
        $toDataUri = function (?string $path) {
            if (! $path || ! Storage::disk('public')->exists($path)) {
                return null;
            }

            return 'data:image/'.pathinfo($path, PATHINFO_EXTENSION).';base64,'.base64_encode(Storage::disk('public')->get($path));
        };

        return Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'qr' => static::qrDataUri($invoice),
            'logo' => $toDataUri($logo),
            'stamp' => $toDataUri($stamp),
            'taxSystem' => Settings::TAX_SYSTEMS[Settings::int('requisites.tax_system_code')] ?? null,
        ])->setPaper('a4')->output();
    }

    /** Админ подтвердил поступление денег по счёту. */
    public function markPaid(Invoice $invoice, ?string $paymentDoc = null, ?int $adminId = null): void
    {
        if ($invoice->status !== 'issued') {
            throw new InvalidArgumentException('Счёт уже обработан');
        }

        DB::transaction(function () use ($invoice, $paymentDoc, $adminId) {
            $invoice->update(['status' => 'paid', 'paid_at' => now(), 'payment_doc' => $paymentDoc, 'confirmed_by' => $adminId]);
            $this->billing->topup($invoice->user, (float) $invoice->amount, $invoice, 'Оплата по счёту '.$invoice->number, $adminId);
        });

        \App\Models\AdminAlert::where('type', 'invoice')->where('url', url('/admin/invoices/'.$invoice->id))->update(['resolved_at' => now()]);
        $this->campaigns->processPendingPayments($invoice->user);
    }
}
