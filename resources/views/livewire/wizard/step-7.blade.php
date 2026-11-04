@php use App\Support\Money; $c = $campaign; $short = max(0, round($need - $available, 2)); @endphp
<div class="space-y-7">
    <div>
        <h1 class="font-display text-2xl font-black">Рекламодатель и запуск</h1>
        <p class="mt-1 text-ink/60">Укажите ИНН — по нему мы промаркируем рекламу (ERID) и, если нужно, выставим счёт.</p>
    </div>

    <x-company-form />

    <div class="rounded-3xl bg-brand-50/70 p-5">
        <div class="font-display font-black">Ваша кампания</div>
        <dl class="mt-3 grid gap-x-6 gap-y-1.5 text-sm sm:grid-cols-2">
            <div class="flex justify-between gap-2"><dt class="text-ink/55">Площадок</dt><dd class="font-bold">{{ count($c->platforms ?? []) }}</dd></div>
            <div class="flex justify-between gap-2"><dt class="text-ink/55">Города</dt><dd class="truncate font-bold">{{ $c->regionNames() }}</dd></div>
            <div class="flex justify-between gap-2"><dt class="text-ink/55">Цена клика</dt><dd class="font-bold">{{ Money::format($c->cpc ?? 0) }}</dd></div>
            <div class="flex justify-between gap-2"><dt class="text-ink/55">Прогноз</dt><dd class="font-bold">≈ {{ Money::number($c->forecast_clicks) }} кликов</dd></div>
            <div class="flex justify-between gap-2"><dt class="text-ink/55">Бюджет</dt><dd class="font-bold">{{ Money::format($c->total_budget) }}</dd></div>
            <div class="flex justify-between gap-2"><dt class="text-ink/55">Свободно на балансе</dt><dd class="font-bold">{{ Money::format($available, true) }}</dd></div>
        </dl>
    </div>

    <label class="flex cursor-pointer items-start gap-3 text-sm">
        <input type="checkbox" wire:model="confirm" class="mt-0.5 h-5 w-5 rounded accent-fuchsia-600">
        <span>Я подтверждаю, что являюсь рекламодателем или его представителем, принимаю <a href="{{ route('offer') }}" target="_blank" class="font-bold text-brand-700 underline">оферту</a> и согласен на передачу данных в ОРД для маркировки рекламы.</span>
    </label>
    @error('confirm') <p class="error">{{ $message }}</p> @enderror

    @if ($short <= 0)
        <button type="button" wire:click="launch('balance')" class="btn-hot w-full py-4 text-lg" wire:loading.attr="disabled" wire:target="launch">
            🚀 Оплатить с баланса {{ Money::format($need) }} и отправить на модерацию
        </button>
    @else
        <div class="rounded-3xl bg-gradient-to-r from-orange-50 to-pink-50 p-5 ring-1 ring-pink-100">
            <div class="font-bold">К оплате: {{ Money::format($short, true) }}</div>
            <p class="text-sm text-ink/60">После поступления денег кампания автоматически уйдёт на модерацию.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <button type="button" wire:click="launch('online')" class="btn-hot" wire:loading.attr="disabled" wire:target="launch">💳 Картой или СБП</button>
                <button type="button" wire:click="launch('invoice')" class="btn-light" wire:loading.attr="disabled" wire:target="launch">🧾 Счёт с QR для юрлица</button>
            </div>
            <p class="hint">НДС не облагается. Счёт придёт на почту и будет доступен в разделе «Финансы».</p>
        </div>
    @endif
</div>
