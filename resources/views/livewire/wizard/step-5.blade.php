@php
    use App\Enums\Platform;
    $all = count($platforms) === count(Platform::cases());
    $groups = ['Яндекс' => [Platform::YandexSearch, Platform::YandexRsya], 'VK' => [Platform::Vkontakte, Platform::Odnoklassniki, Platform::VkNetwork]];
    $groupIcons = ['Яндекс' => ['from-red-500 to-orange-400', 'Я'], 'VK' => ['from-sky-500 to-blue-600', 'VK']];
@endphp
<div class="space-y-5">
    <div>
        <h1 class="font-display text-2xl font-black">Где показывать</h1>
        <p class="mt-1 text-ink/60">Чем больше площадок, тем быстрее наберутся клики.</p>
    </div>

    <button type="button" wire:click="toggleAllPlatforms" class="flex w-full items-center gap-4 rounded-3xl p-5 text-left transition {{ $all ? 'bg-gradient-to-r from-fuchsia-600 via-violet-600 to-indigo-600 text-white shadow-xl shadow-fuchsia-500/30' : 'bg-brand-50 hover:bg-brand-100' }}">
        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-sm font-black {{ $all ? 'bg-white text-fuchsia-700' : 'bg-white ring-2 ring-brand-200' }}">{{ $all ? '✓' : '' }}</span>
        <span class="flex-1">
            <span class="font-display text-lg font-black">Все площадки</span>
            <span class="ml-1 badge bg-yellow-300 text-ink">Рекомендуем</span>
            <span class="block text-sm {{ $all ? 'text-white/80' : 'text-ink/55' }}">Максимальный охват и самая быстрая отдача</span>
        </span>
        <span @click.stop><x-tip :text="Platform::ALL_HINT" /></span>
    </button>

    @foreach ($groups as $group => $items)
        @php $groupOn = count(array_intersect(array_map(fn ($p) => $p->value, $items), $platforms)) === count($items); @endphp
        <div class="rounded-3xl bg-white p-4 ring-1 ring-brand-100">
            <div class="flex items-center gap-3">
                <button type="button" wire:click="toggleGroup('{{ $group }}')" class="flex flex-1 items-center gap-3 text-left">
                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-md text-xs font-black {{ $groupOn ? 'bg-fuchsia-600 text-white' : 'ring-2 ring-brand-200' }}">{{ $groupOn ? '✓' : '' }}</span>
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br {{ $groupIcons[$group][0] }} text-xs font-black text-white">{{ $groupIcons[$group][1] }}</span>
                    <span class="font-display font-black">{{ $group === 'VK' ? Platform::VK_GROUP_LABEL : 'Яндекс Директ' }}</span>
                </button>
                @if ($group === 'VK')<x-tip :text="Platform::VK_GROUP_HINT" />@endif
            </div>
            <div class="mt-3 grid gap-2 sm:grid-cols-{{ count($items) }}">
                @foreach ($items as $p)
                    <label wire:key="pl-{{ $p->value }}" class="flex cursor-pointer items-start gap-3 rounded-2xl p-3 transition {{ in_array($p->value, $platforms, true) ? 'bg-fuchsia-50 ring-2 ring-fuchsia-400' : 'bg-slate-50 hover:bg-brand-50' }}">
                        <input type="checkbox" wire:model.live="platforms" value="{{ $p->value }}" class="mt-0.5 h-5 w-5 rounded accent-fuchsia-600">
                        <span class="flex-1 text-sm font-bold">{{ $p->label() }}</span>
                        <x-tip :text="$p->hint()" />
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach
    @error('platforms') <p class="error">{{ $message }}</p> @enderror
</div>
