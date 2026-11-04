@php $v = $campaign->vacancy; @endphp
<div class="mx-auto max-w-3xl">
    <div class="text-center">
        <div class="text-5xl">🔗</div>
        <h1 class="mt-3 font-display text-2xl font-black sm:text-3xl">Ссылка на вакансию</h1>
        <p class="mt-2 text-ink/60">Вставьте ссылку — заберём название, зарплату, описание и картинки. На эту страницу будут переходить кандидаты.</p>
    </div>

    <form wire:submit="loadVacancy" class="mt-8">
        <div class="flex flex-col gap-3 sm:flex-row">
            <input type="url" wire:model="sourceUrl" class="input text-lg" placeholder="https://hh.ru/vacancy/123456789" autofocus>
            <button type="submit" class="btn-hot shrink-0 px-6" wire:loading.attr="disabled" wire:target="loadVacancy">
                <span wire:loading.remove wire:target="loadVacancy">Загрузить</span>
                <span wire:loading wire:target="loadVacancy">Читаем вакансию…</span>
            </button>
        </div>
        @error('sourceUrl') <p class="error">{{ $message }}</p> @enderror
        <div class="mt-4 flex flex-wrap justify-center gap-2">
            @foreach (['hh.ru', 'Авито', 'Зарплата.ру', 'SuperJob', 'ГдеРабота', 'Работа России', 'ваш сайт'] as $site)
                <span class="chip bg-brand-50 text-xs text-ink/60">{{ $site }}</span>
            @endforeach
        </div>
    </form>

    <div wire:loading.flex wire:target="loadVacancy" class="mt-8 flex-col items-center gap-3 rounded-3xl bg-brand-50 p-8 text-center">
        <svg class="h-10 w-10 animate-spin text-fuchsia-600" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
        <div class="font-bold">Загружаем вакансию и пишем объявление…</div>
        <div class="text-sm text-ink/55">Это займёт 5–20 секунд</div>
    </div>

    @if ($v && ($v['title'] ?? null))
        <div wire:loading.remove wire:target="loadVacancy" class="mt-8 rounded-3xl bg-gradient-to-br from-emerald-50 to-lime-50 p-6 ring-1 ring-emerald-200">
            <div class="flex items-center gap-2 text-sm font-bold text-emerald-700">✓ Вакансия загружена · {{ \App\Services\Parsers\VacancyParser::SITES[$campaign->source_site] ?? '' }}</div>
            <div class="mt-2 font-display text-xl font-black">{{ $v['title'] }}</div>
            <div class="mt-2 flex flex-wrap gap-2 text-sm">
                @foreach (['salary' => '💰', 'city' => '📍', 'employer' => '🏢', 'schedule' => '🕐'] as $key => $icon)
                    @if (! empty($v[$key]))<span class="chip bg-white text-ink/70">{{ $icon }} {{ \Illuminate\Support\Str::limit($v[$key], 60) }}</span>@endif
                @endforeach
            </div>
            @if (! empty($v['description']))
                <p class="mt-3 line-clamp-3 text-sm text-ink/60">{{ $v['description'] }}</p>
            @endif
            @if (! empty($v['images']))
                <div class="mt-3 text-sm font-semibold text-emerald-700">Нашли картинок: {{ count($v['images']) }}</div>
            @endif
        </div>
    @endif

    <div class="mt-6">
        @if (! $showManual)
            <button type="button" wire:click="$set('showManual', true)" class="text-sm font-bold text-brand-700 hover:underline">Сайт не загружается? Вставьте текст вакансии вручную</button>
        @else
            <div class="rounded-3xl bg-brand-50/60 p-5">
                <label class="label">Текст вакансии</label>
                <textarea wire:model="manualText" rows="7" class="input" placeholder="Название должности, зарплата, обязанности, условия…"></textarea>
                @error('manualText') <p class="error">{{ $message }}</p> @enderror
                <button type="button" wire:click="useManualText" class="btn-primary btn-sm mt-3" wire:loading.attr="disabled" wire:target="useManualText">Сделать объявление из текста</button>
            </div>
        @endif
    </div>
</div>
