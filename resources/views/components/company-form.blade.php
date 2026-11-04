<div x-data="{
        q: '', items: [], open: false, timer: null,
        search() {
            clearTimeout(this.timer);
            if (this.q.length < 3) { this.items = []; return; }
            this.timer = setTimeout(async () => { this.items = await $wire.suggestCompany(this.q); this.open = true; }, 250);
        },
        pick(item) { $wire.pickCompany(item); this.q = item.name_short; this.open = false; },
    }" class="space-y-4">
    <div class="relative" @click.outside="open = false">
        <label class="label">Найти компанию</label>
        <input type="text" x-model="q" @input="search()" @focus="items.length && (open = true)" class="input" placeholder="Начните вводить ИНН или название: 7707083893, ООО Ромашка" autocomplete="off">
        <div x-cloak x-show="open && items.length" class="absolute z-40 mt-2 max-h-80 w-full overflow-auto rounded-2xl bg-white p-1.5 shadow-2xl ring-1 ring-black/5">
            <template x-for="item in items" :key="item.inn + item.kpp">
                <button type="button" @click="pick(item)" class="block w-full rounded-xl px-3 py-2.5 text-left hover:bg-brand-50">
                    <div class="font-bold" x-text="item.name_short"></div>
                    <div class="text-xs text-ink/55"><span x-text="'ИНН ' + item.inn"></span> · <span x-text="item.legal_address"></span></div>
                    <div x-show="item.state && item.state !== 'ACTIVE'" class="text-xs font-bold text-rose-600">Организация ликвидируется или ликвидирована</div>
                </button>
            </template>
        </div>
        <p class="hint">Данные подтянутся из ЕГРЮЛ. Нужны для маркировки рекламы (вы — конечный рекламодатель) и счетов.</p>
    </div>

    @if ($this->company['state'] && $this->company['state'] !== 'ACTIVE')
        <div class="rounded-2xl bg-rose-50 p-3 text-sm font-bold text-rose-700">По данным ЕГРЮЛ организация не действует ({{ $this->company['state'] }}). Модерация может отклонить кампанию.</div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label class="label">Полное наименование</label>
            <input type="text" wire:model="company.name_full" class="input">
            @error('company.name_full') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label">ИНН</label>
            <input type="text" wire:model="company.inn" class="input" inputmode="numeric">
            @error('company.inn') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label">КПП</label>
            <input type="text" wire:model="company.kpp" class="input" inputmode="numeric">
            @error('company.kpp') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label">ОГРН / ОГРНИП</label>
            <input type="text" wire:model="company.ogrn" class="input" inputmode="numeric">
            @error('company.ogrn') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label">Руководитель</label>
            <input type="text" wire:model="company.management_name" class="input">
        </div>
        <div class="sm:col-span-2">
            <label class="label">Юридический адрес</label>
            <input type="text" wire:model="company.legal_address" class="input">
        </div>
    </div>
</div>
