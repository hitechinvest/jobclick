@php $r = \App\Services\InvoiceService::payee(); @endphp
<x-layouts.site title="Оферта">
    <section class="mx-auto max-w-3xl px-4 pb-20 pt-32 sm:px-6">
        <div class="card space-y-4 p-8 leading-relaxed text-ink/80">
            <h1 class="font-display text-3xl font-black text-ink">Публичная оферта</h1>
            <p>{{ $r['name'] ?: 'Исполнитель' }} (ИНН {{ $r['inn'] ?: '—' }}) предлагает рекламодателям услуги по размещению рекламы вакансий в Яндекс Директе и VK Рекламе через сервис JobClick.</p>
            <h2 class="font-display text-lg font-bold text-ink">1. Предмет</h2>
            <p>Исполнитель размещает рекламные объявления Заказчика на выбранных площадках, а Заказчик оплачивает переходы (клики) по объявлениям по тарифам, опубликованным на сайте.</p>
            <h2 class="font-display text-lg font-bold text-ink">2. Рекламодатель и маркировка</h2>
            <p>Заказчик является конечным рекламодателем и указывает свой ИНН. Заказчик соглашается на передачу своих данных в операторы рекламных данных (ОРД) и Единый реестр интернет-рекламы для получения идентификатора ERID.</p>
            <h2 class="font-display text-lg font-bold text-ink">3. Модерация</h2>
            <p>Исполнитель вправе отказать в размещении объявлений, нарушающих законодательство о рекламе и о занятости населения (включая дискриминационные требования), с возвратом средств на баланс.</p>
            <h2 class="font-display text-lg font-bold text-ink">4. Оплата</h2>
            <p>Оплата производится авансом путём пополнения баланса онлайн или по счёту. НДС не облагается. Неизрасходованный бюджет кампании возвращается на баланс Заказчика.</p>
            <p class="text-sm text-ink/50">Полный текст оферты размещается администрацией сервиса@if(\App\Support\Settings::get('requisites.offer_url')): <a class="underline" href="{{ \App\Support\Settings::get('requisites.offer_url') }}">открыть документ</a>@endif.</p>
        </div>
    </section>
</x-layouts.site>
