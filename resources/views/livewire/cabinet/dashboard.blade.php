@php use App\Support\Money; $max = max(1, $days->max('clicks')); @endphp
<div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-black">Привет{{ $user->name ? ', '.$user->name : '' }}! 👋</h1>
            <p class="mt-1 text-ink/60">Вот как идёт поиск кандидатов.</p>
        </div>
        <a href="{{ route('cabinet.campaigns.create') }}" class="btn-hot">+ Запустить рекламу вакансии</a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-3xl bg-gradient-to-br from-violet-600 to-fuchsia-600 p-6 text-white shadow-xl shadow-fuchsia-500/20">
            <div class="text-sm font-bold text-white/75">Баланс</div>
            <div class="mt-2 font-display text-3xl font-black">{{ Money::format($user->balance) }}</div>
            <div class="mt-1 text-sm text-white/75">свободно {{ Money::format($user->available()) }}</div>
        </div>
        <div class="card">
            <div class="text-sm font-bold text-ink/50">Активных кампаний</div>
            <div class="mt-2 font-display text-3xl font-black text-brand-700">{{ $active }}</div>
        </div>
        <div class="card">
            <div class="text-sm font-bold text-ink/50">Кликов за 7 дней</div>
            <div class="mt-2 font-display text-3xl font-black text-pink-600">{{ Money::number($days->sum('clicks')) }}</div>
        </div>
        <div class="card">
            <div class="text-sm font-bold text-ink/50">Потрачено за 7 дней</div>
            <div class="mt-2 font-display text-3xl font-black text-orange-500">{{ Money::format($days->sum('spent')) }}</div>
        </div>
    </div>

    @if ($attention->isNotEmpty())
        <div class="card border-2 border-amber-200 bg-amber-50/50">
            <h2 class="font-display text-lg font-black">Ждут вашего действия</h2>
            <div class="mt-4 divide-y divide-amber-100">
                @foreach ($attention as $c)
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div>
                            <div class="font-bold">{{ $c->displayTitle() }}</div>
                            <x-status-badge :status="$c->status" class="mt-1" />
                            @if ($c->moderation_comment && $c->status === \App\Enums\CampaignStatus::NeedsChanges)
                                <div class="mt-1 text-sm text-amber-800">Модератор: {{ $c->moderation_comment }}</div>
                            @endif
                        </div>
                        <a href="{{ $c->status === \App\Enums\CampaignStatus::PendingPayment ? route('cabinet.campaigns.show', $c) : route('cabinet.campaigns.edit', $c) }}" class="btn-primary btn-sm">
                            {{ $c->status === \App\Enums\CampaignStatus::PendingPayment ? 'Оплатить' : 'Продолжить' }}
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <div class="card">
            <h2 class="font-display text-lg font-black">Клики по дням</h2>
            <div class="mt-6 flex h-48 items-end gap-3">
                @foreach ($days as $date => $d)
                    <div class="flex flex-1 flex-col items-center gap-2">
                        <div class="text-xs font-bold text-ink/60">{{ $d['clicks'] }}</div>
                        <div class="w-full rounded-t-xl bg-gradient-to-t from-fuchsia-600 to-orange-400" style="height: {{ max(4, $d['clicks'] / $max * 150) }}px"></div>
                        <div class="text-xs font-semibold text-ink/40">{{ \Carbon\Carbon::parse($date)->translatedFormat('D') }}</div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-lg font-black">Последние кампании</h2>
                <a href="{{ route('cabinet.campaigns') }}" wire:navigate class="text-sm font-bold text-brand-700">Все →</a>
            </div>
            <div class="mt-4 space-y-3">
                @forelse ($campaigns as $c)
                    <a href="{{ route('cabinet.campaigns.show', $c) }}" wire:navigate class="block rounded-2xl bg-brand-50/50 p-4 transition hover:bg-brand-50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="font-bold leading-tight">{{ $c->displayTitle() }}</div>
                            <x-status-badge :status="$c->status" />
                        </div>
                        <div class="mt-2 text-sm text-ink/60">{{ $c->clicks }} кликов · {{ Money::format($c->spent) }} из {{ Money::format($c->total_budget ?? 0) }}</div>
                    </a>
                @empty
                    <div class="rounded-2xl bg-brand-50 p-6 text-center">
                        <div class="text-4xl">🚀</div>
                        <p class="mt-2 font-semibold">Пока нет кампаний</p>
                        <a href="{{ route('cabinet.campaigns.create') }}" class="btn-primary btn-sm mt-4">Создать первую</a>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
