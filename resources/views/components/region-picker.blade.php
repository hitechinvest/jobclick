@props(['selected' => [], 'placeholder' => 'Начните вводить город', 'dark' => false])
<div x-data="{
        q: '', items: [], open: false, active: 0, timer: null,
        search() {
            clearTimeout(this.timer);
            this.timer = setTimeout(async () => {
                this.items = await $wire.searchRegions(this.q);
                this.active = 0; this.open = true;
            }, 180);
        },
        pick(item) { $wire.addRegion(item.id); this.q = ''; this.open = false; },
    }" class="relative" @click.outside="open = false">
    <div class="flex min-h-[52px] flex-wrap items-center gap-2 rounded-2xl bg-brand-50/60 px-3 py-2 ring-1 ring-brand-200/70 focus-within:bg-white focus-within:ring-2 focus-within:ring-fuchsia-500">
        @foreach ($selected as $region)
            <span wire:key="region-{{ $region['id'] }}" class="chip bg-gradient-to-r from-fuchsia-500 to-violet-600 py-1 pl-3 pr-1.5 text-white">
                {{ $region['label'] }}
                <button type="button" wire:click="removeRegion({{ $region['id'] }})" class="grid h-5 w-5 place-items-center rounded-full bg-white/25 text-xs hover:bg-white/40" aria-label="Убрать">✕</button>
            </span>
        @endforeach
        <input type="text" x-model="q" @input="search()" @focus="search()"
               @keydown.arrow-down.prevent="active = Math.min(active + 1, items.length - 1)"
               @keydown.arrow-up.prevent="active = Math.max(active - 1, 0)"
               @keydown.enter.prevent="items[active] && pick(items[active])"
               class="min-w-[10rem] flex-1 border-0 bg-transparent px-1 py-1 text-ink placeholder:text-ink/35 focus:outline-none focus:ring-0"
               placeholder="{{ count($selected) ? 'Добавить ещё' : $placeholder }}">
    </div>
    <div x-cloak x-show="open && items.length" x-transition class="absolute z-40 mt-2 max-h-72 w-full overflow-auto rounded-2xl bg-white p-1.5 shadow-2xl ring-1 ring-black/5">
        <template x-for="(item, i) in items" :key="item.id">
            <button type="button" @click="pick(item)" @mouseenter="active = i"
                    :class="i === active ? 'bg-brand-50 text-brand-800' : 'text-ink'"
                    class="block w-full rounded-xl px-3 py-2 text-left text-sm font-semibold" x-text="item.label"></button>
        </template>
    </div>
</div>
