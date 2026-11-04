@php
    $c = $campaign;
    $url = route('cabinet.campaigns.show', $c);
@endphp
<x-mail::message>
# {{ \App\Mail\CampaignStatusMail::SUBJECTS[$type] ?? 'Новости по кампании' }}

Кампания: **«{{ $c->displayTitle() }}»**

@switch($type)
@case('submitted')
Мы получили кампанию и бюджет {{ \App\Support\Money::format($c->total_budget) }}. Модератор проверит объявление — обычно это занимает до одного рабочего дня. Мы напишем, как только всё будет готово.
@break
@case('approved')
Модерация пройдена! Сейчас мы размещаем объявление на выбранных площадках. Как только показы начнутся — пришлём письмо.
@break
@case('rejected')
К сожалению, кампанию нельзя запустить.

**Причина:** {{ $extra['reason'] ?? '' }}

Деньги вернулись на ваш баланс — их можно использовать для новой кампании.
@break
@case('needs_changes')
Модератор просит поправить объявление:

<x-mail::panel>
{{ $extra['reason'] ?? '' }}
</x-mail::panel>

Внесите правки и отправьте кампанию повторно — платить ещё раз не нужно.
@break
@case('started')
Показы идут! Ваше объявление увидят кандидаты на площадках:

@foreach($c->placements as $p)
- {{ $p->platform->label() }}@if($p->erid) — ERID {{ $p->erid }}@endif

@endforeach

Статистика кликов обновляется в личном кабинете в реальном времени.
@break
@case('budget_80')
Израсходовано {{ \App\Support\Money::format($c->spent) }} из {{ \App\Support\Money::format($c->total_budget) }}, получено {{ $c->clicks }} кликов. Чтобы показы не остановились, пополните бюджет кампании.
@break
@case('completed')
@if(($extra['reason'] ?? '') === 'budget')
Бюджет кампании израсходован.
@elseif(($extra['reason'] ?? '') === 'vacancy')
Вакансия закрыта или перенесена в архив, поэтому показы остановлены.
@else
Показы остановлены.
@endif
Итого: {{ $c->clicks }} кликов за {{ \App\Support\Money::format($c->spent) }}. Остаток бюджета вернулся на баланс.
@break
@case('report')
За первые дни: {{ $c->clicks }} кликов, израсходовано {{ \App\Support\Money::format($c->spent) }} из {{ \App\Support\Money::format($c->total_budget) }}.
@break
@endswitch

<x-mail::button :url="$url">
Открыть кампанию
</x-mail::button>

Команда JobClick
</x-mail::message>
