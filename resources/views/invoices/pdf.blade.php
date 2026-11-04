@php
    $p = $invoice->payee;
    $payer = $invoice->payer;
    $sum = number_format((float) $invoice->amount, 2, ',', ' ');
@endphp
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Счёт № {{ $invoice->number }}</title>
<style>
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 10pt; color: #111; margin: 0; }
    table { border-collapse: collapse; width: 100%; }
    .bank td { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }
    .small { font-size: 8pt; color: #333; }
    h1 { font-size: 15pt; margin: 18px 0 6px; border-bottom: 2px solid #000; padding-bottom: 6px; }
    .items th, .items td { border: 1px solid #000; padding: 5px; }
    .items th { background: #f0f0f0; font-size: 9pt; }
    .right { text-align: right; }
    .totals td { padding: 3px 5px; }
    .muted { color: #555; }
    .qr { text-align: center; }
    .sign td { padding-top: 26px; }
</style>
</head>
<body>
<table>
    <tr>
        <td style="width:65%; vertical-align:top">
            @if($logo)<img src="{{ $logo }}" style="max-height:48px; max-width:220px"><br>@endif
            <b>{{ $p['name'] }}</b><br>
            <span class="small">{{ $p['address'] }}@if($p['phone']), тел. {{ $p['phone'] }}@endif @if($p['email']), {{ $p['email'] }}@endif</span>
        </td>
        <td class="qr" style="width:35%">
            <img src="{{ $qr }}" style="width:120px; height:120px"><br>
            <span class="small">Оплата по QR-коду<br>в приложении банка</span>
        </td>
    </tr>
</table>

<table class="bank" style="margin-top:10px">
    <tr>
        <td colspan="2" rowspan="2" style="width:55%">{{ $p['bank_name'] }}<br><span class="small">Банк получателя</span></td>
        <td style="width:10%">БИК</td>
        <td>{{ $p['bik'] }}</td>
    </tr>
    <tr>
        <td>Сч. №</td>
        <td>{{ $p['corr_account'] }}</td>
    </tr>
    <tr>
        <td>ИНН {{ $p['inn'] }}</td>
        <td>КПП {{ $p['kpp'] }}</td>
        <td rowspan="2">Сч. №</td>
        <td rowspan="2">{{ $p['account'] }}</td>
    </tr>
    <tr>
        <td colspan="2">{{ $p['name'] }}<br><span class="small">Получатель</span></td>
    </tr>
</table>

<h1>Счёт на оплату № {{ $invoice->number }} от {{ $invoice->created_at->format('d.m.Y') }}</h1>

<table style="margin-bottom:10px">
    <tr><td style="width:18%" class="muted">Поставщик:</td><td><b>{{ $p['name'] }}</b>, ИНН {{ $p['inn'] }}@if($p['kpp']), КПП {{ $p['kpp'] }}@endif, {{ $p['address'] }}</td></tr>
    <tr><td class="muted">Покупатель:</td><td><b>{{ $payer['name'] }}</b>, ИНН {{ $payer['inn'] }}@if(!empty($payer['kpp'])), КПП {{ $payer['kpp'] }}@endif @if(!empty($payer['address'])), {{ $payer['address'] }}@endif</td></tr>
</table>

<table class="items">
    <thead>
        <tr><th style="width:5%">№</th><th>Наименование</th><th style="width:9%">Кол-во</th><th style="width:8%">Ед.</th><th style="width:16%">Цена</th><th style="width:16%">Сумма</th></tr>
    </thead>
    <tbody>
        <tr>
            <td class="right">1</td>
            <td>Рекламные услуги: пополнение баланса в сервисе JobClick @if($invoice->campaign_id)(кампания #{{ $invoice->campaign_id }})@endif</td>
            <td class="right">1</td>
            <td>усл.</td>
            <td class="right">{{ $sum }}</td>
            <td class="right">{{ $sum }}</td>
        </tr>
    </tbody>
</table>

<table class="totals" style="margin-top:6px">
    <tr><td class="right" style="width:80%"><b>Итого:</b></td><td class="right"><b>{{ $sum }}</b></td></tr>
    <tr><td class="right"><b>Без налога (НДС)</b></td><td class="right">—</td></tr>
    <tr><td class="right"><b>Всего к оплате:</b></td><td class="right"><b>{{ $sum }}</b></td></tr>
</table>

<p>Всего наименований 1, на сумму {{ $sum }} руб.<br><b>{{ \App\Support\Money::words((float) $invoice->amount) }}</b></p>
<p class="small">НДС не облагается@if($taxSystem) ({{ $taxSystem }})@endif. Назначение платежа: «{{ $invoice->purpose() }}».<br>
Оплатить не позднее {{ $invoice->due_date->format('d.m.Y') }}. Деньги зачисляются на баланс после поступления на расчётный счёт.</p>

<table class="sign">
    <tr>
        <td style="width:50%">Руководитель ______________ {{ $p['director'] }}</td>
        <td>Бухгалтер ______________ {{ $p['accountant'] ?: $p['director'] }}</td>
    </tr>
</table>
@if($stamp)
    <img src="{{ $stamp }}" style="width:150px; margin-top:-40px; margin-left:60px">
@endif
</body>
</html>
