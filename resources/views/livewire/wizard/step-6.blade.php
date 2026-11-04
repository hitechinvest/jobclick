@php use App\Support\Money; $f = $forecast; @endphp
<div class="space-y-6">
    <div>
        <h1 class="font-display text-2xl font-black">Бюджет и прогноз</h1>
        <p class="mt-1 text-ink/60">Платите только за клики. Неизрасходованный бюджет вернётся на баланс.</p>
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label class="label">Бюджет кампании, ₽</label>
            <input type="number" wire:model.live.debounce.600ms="totalBudget" min="{{ (int) ($f['min_total_budget'] ?? 3000) }}" step="500" class="input font-display text-2xl font-black">
            @error('totalBudget') <p class="error">{{ $message }}</p> @enderror
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach ([5000, 10000, 20000, 50000] as $preset)
                    <button type="button" wire:click="$set('totalBudget', {{ $preset }})" class="chip bg-brand-50 text-xs text-ink/70 hover:bg-brand-100">{{ Money::format($preset) }}</button>
                @endforeach
            </div>
        </div>
        <div>
            <label class="mb-1.5 flex cursor-pointer items-center gap-2 text-sm font-bold text-ink/80">
                <input type="checkbox" wire:model.live="useDaily" class="h-4 w-4 rounded accent-fuchsia-600">
                <span>Ограничить расход в день <span class="font-normal text-ink/40">— необязательно</span></span>
            </label>
            @if ($useDaily)
                <input type="number" wire:model.live.debounce.600ms="dailyBudget" min="{{ (int) ($f['min_daily_budget'] ?? 500) }}" step="100" class="input text-xl font-bold">
                @error('dailyBudget') <p class="error">{{ $message }}</p> @enderror
                <p class="hint">Минимум {{ Money::format($f['min_daily_budget'] ?? 500) }}. Когда лимит исчерпан, показы продолжатся на следующий день.</p>
            @else
                <p class="hint">Без лимита бюджет расходуется так быстро, как приходят кандидаты.</p>
            @endif
        </div>
    </div>

    @if ($f)
        <div class="relative" wire:loading.class="opacity-50" wire:target="totalBudget,dailyBudget,useDaily">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-3xl bg-gradient-to-br from-violet-600 via-fuchsia-600 to-pink-500 p-5 text-white shadow-xl shadow-fuchsia-500/20 sm:col-span-2">
                    <div class="text-sm font-semibold text-white/80">Прогноз кликов за бюджет</div>
                    <div class="mt-1 font-display text-4xl font-black">≈ {{ Money::number($f['budget_clicks']) }}</div>
                    <div class="text-sm text-white/80">от {{ Money::number($f['clicks_low']) }} до {{ Money::number($f['clicks_high']) }} · клик {{ Money::format($f['cpc']) }}</div>
                </div>
                <div class="rounded-3xl bg-orange-50 p-5">
                    <div class="text-sm font-semibold text-ink/55">Срок кампании</div>
                    <div class="mt-1 font-display text-3xl font-black text-orange-600">{{ $f['days'] ? '~'.$f['days'].' дн.' : '—' }}</div>
                </div>
            </div>
            @if ($f['demand_clicks'] !== null)
                <div class="mt-4 rounded-3xl bg-brand-50/70 p-5">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <div class="font-bold">📊 Спрос по Вордстату: «{{ $f['phrase'] }}»</div>
                        <div class="text-sm font-semibold text-ink/55">{{ Money::number($f['searches']) }} запросов в месяц</div>
                    </div>
                    <p class="mt-1 text-sm text-ink/65">Объявление может получить до <b>{{ Money::number($f['demand_clicks']) }} кликов в месяц</b> в выбранных городах.</p>
                    @if ($f['recommended_budget'] && $f['recommended_budget'] > ($totalBudget ?? 0))
                        <button type="button" wire:click="useRecommended" class="btn-light btn-sm mt-3">Забрать весь спрос за месяц: {{ Money::format($f['recommended_budget']) }}</button>
                    @endif
                </div>
            @elseif ($f['error'])
                <p class="mt-3 text-sm text-ink/50">{{ $f['error'] }}</p>
            @endif
            <p class="mt-3 text-xs text-ink/45">Прогноз — оценка. Фактическое число кликов зависит от привлекательности объявления и конкуренции.</p>
        </div>
    @endif
</div>
