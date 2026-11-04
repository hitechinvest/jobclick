<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Console\Command;
use Throwable;

class SyncPayments extends Command
{
    protected $signature = 'jobclick:sync-payments';

    protected $description = 'Сверка «подвисших» онлайн-платежей с ЮKassa';

    public function handle(PaymentService $payments): int
    {
        Payment::where('status', 'pending')->whereNotNull('provider_id')->where('created_at', '>', now()->subDays(2))
            ->each(function (Payment $payment) use ($payments) {
                try {
                    $payments->sync($payment);
                } catch (Throwable $e) {
                    $this->warn('Платёж #'.$payment->id.': '.$e->getMessage());
                }
            });

        Payment::where('status', 'pending')->where('created_at', '<=', now()->subDays(2))->update(['status' => 'canceled']);

        return self::SUCCESS;
    }
}
