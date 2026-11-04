<div class="space-y-7">
    <div>
        <h1 class="font-display text-2xl font-black">Кому показывать</h1>
        <p class="mt-1 text-ink/60">Не сужайте аудиторию слишком сильно — чем шире, тем дешевле и быстрее придут кандидаты.</p>
    </div>

    <div>
        <label class="label">Город или регион</label>
        <x-region-picker :selected="$regions" placeholder="Москва, Санкт-Петербург, Казань…" />
        @error('regionIds') <p class="error">{{ $message }}</p> @enderror
        <p class="hint">Клик в городах Москва и Санкт-Петербург — {{ \App\Support\Money::format(\App\Support\Settings::float('tariff.cpc_premium')) }}, в остальных — {{ \App\Support\Money::format(\App\Support\Settings::float('tariff.cpc_default')) }}.</p>
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <label class="label">Пол</label>
            <div class="flex gap-2">
                @foreach ($this::GENDERS as $key => $label)
                    <button type="button" wire:click="$set('gender', '{{ $key }}')" class="btn-sm flex-1 rounded-xl font-bold {{ $gender === $key ? 'bg-fuchsia-600 text-white shadow-lg shadow-fuchsia-500/30' : 'bg-brand-50 text-ink/70' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <div>
            <label class="label">Возраст</label>
            <div class="flex items-center gap-2">
                <input type="number" wire:model="ageFrom" min="14" max="80" class="input text-center">
                <span class="font-bold text-ink/40">—</span>
                <input type="number" wire:model="ageTo" min="14" max="80" class="input text-center">
            </div>
            @error('ageTo') <p class="error">{{ $message }}</p> @enderror
            <label class="mt-2 flex cursor-pointer items-center gap-2 text-sm font-semibold text-ink/70">
                <input type="checkbox" wire:model="ageUnknown" class="h-4 w-4 rounded accent-fuchsia-600"> Показывать, если возраст неизвестен
            </label>
        </div>
    </div>

    <div>
        <label class="label">Уровень дохода <x-tip text="Оценка платформ по поведению пользователя. Для массовых вакансий подойдёт «Любой» или «Низкий/Средний», для руководителей — «Высокий»." /></label>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            @foreach ($this::INCOMES as $key => $label)
                <button type="button" wire:click="$set('income', '{{ $key }}')" class="btn-sm rounded-xl font-bold {{ $income === $key ? 'bg-fuchsia-600 text-white shadow-lg shadow-fuchsia-500/30' : 'bg-brand-50 text-ink/70' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div>
        <label class="label">Интересы и привычки <span class="font-normal text-ink/40">— необязательно</span></label>
        <div class="flex flex-wrap gap-2">
            @foreach ($interestOptions as $name)
                <label wire:key="int-{{ md5($name) }}" class="cursor-pointer">
                    <input type="checkbox" wire:model.live="interests" value="{{ $name }}" class="peer hidden">
                    <span class="chip bg-brand-50 text-sm text-ink/70 transition peer-checked:bg-gradient-to-r peer-checked:from-fuchsia-500 peer-checked:to-violet-600 peer-checked:text-white">{{ $name }}</span>
                </label>
            @endforeach
        </div>
        <input type="text" wire:model="interestsCustom" class="input mt-3" placeholder="Свои интересы через запятую: рыбалка, автоспорт…">
    </div>
</div>
