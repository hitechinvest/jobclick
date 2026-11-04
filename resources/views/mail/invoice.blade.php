<x-mail::message>
# Счёт № {{ $invoice->number }}

Во вложении — счёт на {{ \App\Support\Money::format($invoice->amount, true) }} для пополнения баланса JobClick.

Оплатить можно по QR-коду в счёте из приложения банка. Срок оплаты — до {{ $invoice->due_date->format('d.m.Y') }}.
Как только деньги поступят, мы зачислим их на баланс и автоматически отправим кампанию на модерацию.

<x-mail::button :url="route('cabinet.finance')">
Финансы в кабинете
</x-mail::button>

Команда JobClick
</x-mail::message>
