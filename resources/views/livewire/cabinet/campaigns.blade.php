@php use App\Support\Money; @endphp
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="font-display text-3xl font-black">Кампании</h1>
        <a href="{{ route('cabinet.campaigns.create') }}" class="btn-hot">+ Новая кампания</a>
    </div>
    <div class="flex flex-wrap gap-2">
        @foreach (\App\Livewire\Cabinet\Campaigns::FILTERS as $key => $label)
            <button type="button" wire:click="$set('filter', '{{ $key }}')" class="chip {{ $filter === $key ? 'bg-fuchsia-600 text-white shadow-lg shadow-fuchsia-500/30' : 'bg-white text-ink/70 ring-1 ring-black/5 hover:bg-brand-50' }}">{{ $label }}</button>
        @endforeach
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($campaigns as $c)
            @php $cover = $c->creatives->firstWhere('format', '16:9'); @endphp
            <a href="{{ $c->status === \App\Enums\CampaignStatus::Draft ? route('cabinet.campaigns.edit', $c) : route('cabinet.campaigns.show', $c) }}" wire:navigate
               class="group overflow-hidden rounded-3xl bg-white shadow-[0_10px_40px_-12px_rgba(76,29,149,0.18)] ring-1 ring-black/[0.04] transition hover:-translate-y-1">
                <div class="relative aspect-[16/8] bg-gradient-to-br from-violet-200 via-fuchsia-200 to-orange-200">
                    @if ($cover)
                        <img src="{{ $cover->url() }}" class="h-full w-full object-cover" alt="">
                    @else
                        <div class="grid h-full place-items-center text-5xl">🎯</div>
                    @endif
                    <x-status-badge :status="$c->status" class="absolute left-3 top-3 shadow" />
                </div>
                <div class="p-5">
                    <div class="font-display font-bold leading-tight">{{ $c->displayTitle() }}</div>
                    <div class="mt-1 truncate text-sm text-ink/50">{{ $c->regionNames() ?: 'Город не выбран' }}</div>
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-brand-50">
                        <div class="h-full rounded-full bg-gradient-to-r from-fuchsia-500 to-orange-400" style="width: {{ $c->spentPercent() }}%"></div>
                    </div>
                    <div class="mt-2 flex justify-between text-sm font-semibold text-ink/60">
                        <span>{{ $c->clicks }} кликов</span>
                        <span>{{ Money::format($c->spent) }} / {{ Money::format($c->total_budget ?? 0) }}</span>
                    </div>
                </div>
            </a>
        @empty
            <div class="card col-span-full py-14 text-center">
                <div class="text-5xl">📭</div>
                <p class="mt-3 font-bold">Здесь пока пусто</p>
                <a href="{{ route('cabinet.campaigns.create') }}" class="btn-primary mt-5">Создать кампанию</a>
            </div>
        @endforelse
    </div>
    {{ $campaigns->links() }}
</div>
