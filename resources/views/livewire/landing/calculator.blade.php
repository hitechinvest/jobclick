@php use App\Support\Money; @endphp
<div class="relative rounded-[2rem] bg-white/95 p-6 shadow-2xl shadow-fuchsia-900/30 ring-1 ring-white/50 backdrop-blur sm:p-8">
    <div class="absolute -right-3 -top-4 rotate-6 rounded-2xl bg-yellow-300 px-3 py-1.5 font-display text-xs font-black text-ink shadow-lg">Данные Яндекс Вордстата</div>
    <h2 class="font-display text-xl font-black sm:text-2xl">Сколько кандидатов вы получите?</h2>
    <p class="mt-1 text-sm text-ink/60">Считаем по реальным запросам людей, которые ищут такую работу.</p>

    <form wire:submit="calculate" class="mt-6 space-y-4">
        <div>
            <label class="label" for="calc-title">Кого ищете</label>
            <input id="calc-title" type="text" wire:model="title" class="input" placeholder="Например: курьер, повар, менеджер по продажам">
            @error('title') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label">Город</label>
            <x-region-picker :selected="$regions" placeholder="Москва, Казань, Новосибирск…" />
            @error('regionIds') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div x-data="{ b: {{ $budget }} }">
            <div class="flex items-baseline justify-between">
                <label class="label" for="calc-budget">Бюджет</label>
                <span class="font-display text-lg font-black text-brand-700" x-text="new Intl.NumberFormat('ru-RU').format(b) + ' ₽'"></span>
            </div>
            <input id="calc-budget" type="range" min="3000" max="200000" step="1000" x-model.number="b" @change="$wire.$set('budget', b)" class="w-full accent-fuchsia-600">
            <div class="flex justify-between text-xs font-semibold text-ink/40"><span>3 000 ₽</span><span>200 000 ₽</span></div>
            @error('budget') <p class="error">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn-hot w-full text-lg" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="calculate">Посчитать отклик</span>
            <span wire:loading wire:target="calculate" class="inline-flex items-center gap-2">
                <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                Смотрим Вордстат…
            </span>
        </button>
    </form>

    @if ($result)
        <div class="mt-6 space-y-3" wire:transition>
            @if ($result['demand_clicks'] !== null)
                <div class="rounded-3xl bg-gradient-to-br from-violet-600 via-fuchsia-600 to-pink-500 p-5 text-white">
                    <div class="text-sm font-semibold text-white/80">Людей в месяц ищут «{{ $result['phrase'] }}»</div>
                    <div class="mt-1 font-display text-3xl font-black">{{ Money::number($result['searches']) }} запросов</div>
                    <div class="mt-3 flex items-end justify-between gap-3 border-t border-white/20 pt-3">
                        <div>
                            <div class="text-sm font-semibold text-white/80">Ваше объявление получит</div>
                            <div class="font-display text-4xl font-black text-yellow-300">≈ {{ Money::number($result['demand_clicks']) }}</div>
                            <div class="text-sm font-bold">кликов в месяц</div>
                        </div>
                        <div class="text-5xl">🚀</div>
                    </div>
                </div>
            @elseif ($result['error'])
                <div class="rounded-2xl bg-amber-50 p-4 text-sm font-semibold text-amber-800">{{ $result['error'] }}. Прогноз по бюджету ниже всё равно точный.</div>
            @endif

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-2xl bg-brand-50 p-4">
                    <div class="text-xs font-bold uppercase tracking-wide text-ink/50">На {{ Money::format($budget) }}</div>
                    <div class="mt-1 font-display text-2xl font-black text-brand-700">{{ Money::number($result['budget_clicks']) }}</div>
                    <div class="text-xs font-semibold text-ink/60">кликов по {{ Money::format($result['cpc']) }}</div>
                </div>
                <div class="rounded-2xl bg-orange-50 p-4">
                    <div class="text-xs font-bold uppercase tracking-wide text-ink/50">Срок</div>
                    <div class="mt-1 font-display text-2xl font-black text-orange-600">{{ $result['days'] ? '~'.$result['days'].' дн.' : '—' }}</div>
                    <div class="text-xs font-semibold text-ink/60">чтобы потратить бюджет</div>
                </div>
            </div>

            @if ($result['recommended_budget'] && $result['recommended_budget'] > $budget)
                <p class="rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                    💡 Чтобы забрать весь спрос за месяц, нужен бюджет около {{ Money::format($result['recommended_budget']) }}.
                </p>
            @endif

            @if ($result['top_phrases'])
                <div>
                    <div class="mb-2 text-xs font-bold uppercase tracking-wide text-ink/40">Ещё ищут</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($result['top_phrases'] as $phrase)
                            <span class="chip bg-slate-100 text-xs text-ink/70">{{ $phrase['phrase'] }} · {{ Money::number($phrase['count']) }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            <button type="button" wire:click="start" class="btn-primary w-full text-lg">Запустить рекламу →</button>
        </div>
    @else
        <p class="mt-5 text-center text-xs font-semibold text-ink/45">{{ Money::format($cpcPremium) }} за клик в Москве и Петербурге · {{ Money::format($cpcDefault) }} в остальных городах</p>
    @endif
</div>
