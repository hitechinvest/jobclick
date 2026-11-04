@php
    use App\Enums\CampaignStatus;
    use App\Enums\Platform;
    use App\Support\Money;
    $c = $campaign;
    $max = max(1, $byDay->max());
@endphp
<div class="space-y-6">
    <a href="{{ route('cabinet.campaigns') }}" wire:navigate class="text-sm font-bold text-brand-700">← Все кампании</a>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 max-w-full">
            <x-status-badge :status="$c->status" />
            <h1 class="mt-2 font-display text-2xl font-black sm:text-3xl">{{ $c->displayTitle() }}</h1>
            @if ($c->source_url)<a href="{{ $c->source_url }}" target="_blank" rel="noopener" class="mt-1 block max-w-full truncate text-sm font-semibold text-brand-700 hover:underline">{{ $c->source_url }}</a>@endif
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($c->status->isEditable())
                <a href="{{ route('cabinet.campaigns.edit', $c) }}" class="btn-light btn-sm">✏️ Редактировать</a>
            @endif
            @if ($c->status === CampaignStatus::Active)
                <button wire:click="pause" class="btn-light btn-sm">⏸ Пауза</button>
            @endif
            @if ($c->status === CampaignStatus::Paused)
                <button wire:click="resume" class="btn-primary btn-sm">▶ Возобновить</button>
            @endif
            @if (in_array($c->status, [CampaignStatus::Draft, CampaignStatus::PendingPayment, CampaignStatus::Moderation, CampaignStatus::NeedsChanges, CampaignStatus::Approved, CampaignStatus::Active, CampaignStatus::Paused], true))
                <button wire:click="stop" wire:confirm="Остановить кампанию? Остаток бюджета вернётся на баланс." class="btn-danger btn-sm">⏹ Остановить</button>
            @endif
        </div>
    </div>

    @if ($c->status === CampaignStatus::NeedsChanges && $c->moderation_comment)
        <div class="rounded-3xl bg-amber-50 p-5 ring-1 ring-amber-200">
            <div class="font-bold text-amber-900">Модератор просит поправить:</div>
            <p class="mt-1 text-amber-900">{{ $c->moderation_comment }}</p>
            <a href="{{ route('cabinet.campaigns.edit', $c) }}" class="btn-primary btn-sm mt-3">Исправить и отправить</a>
        </div>
    @endif
    @if ($c->status === CampaignStatus::Rejected && $c->moderation_comment)
        <div class="rounded-3xl bg-rose-50 p-5 text-rose-800 ring-1 ring-rose-200"><b>Причина отклонения:</b> {{ $c->moderation_comment }}</div>
    @endif

    @if ($c->status === CampaignStatus::PendingPayment)
        <div class="rounded-3xl bg-gradient-to-r from-orange-400 via-pink-500 to-fuchsia-600 p-6 text-white shadow-xl">
            <div class="font-display text-xl font-black">Осталось оплатить {{ Money::format($missing, true) }}</div>
            <p class="mt-1 text-white/85">Как только деньги поступят, кампания автоматически уйдёт на модерацию.</p>
            <div class="mt-4 flex flex-wrap gap-3">
                @if ($missing > 0)
                    <button wire:click="payOnline({{ $missing }}, {{ $c->id }})" class="btn bg-white text-fuchsia-700">💳 Картой или СБП</button>
                    <button wire:click="issueInvoice({{ $missing }}, {{ $c->id }})" class="btn bg-white/20 text-white ring-1 ring-white/40">🧾 Счёт для юрлица</button>
                @else
                    <button wire:click="retry" class="btn bg-white text-fuchsia-700">Отправить на модерацию</button>
                @endif
            </div>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card"><div class="text-sm font-bold text-ink/50">Клики</div><div class="mt-1 font-display text-3xl font-black text-pink-600">{{ Money::number($c->clicks) }}</div><div class="text-xs text-ink/50">прогноз ≈ {{ Money::number($c->forecast_clicks) }}</div></div>
        <div class="card"><div class="text-sm font-bold text-ink/50">Потрачено</div><div class="mt-1 font-display text-3xl font-black text-orange-500">{{ Money::format($c->spent) }}</div><div class="text-xs text-ink/50">из {{ Money::format($c->total_budget ?? 0) }}</div></div>
        <div class="card"><div class="text-sm font-bold text-ink/50">Остаток бюджета</div><div class="mt-1 font-display text-3xl font-black text-brand-700">{{ Money::format($c->reserved) }}</div><div class="text-xs text-ink/50">цена клика {{ Money::format($c->cpc ?? 0) }}</div></div>
        <div class="card"><div class="text-sm font-bold text-ink/50">Дневной лимит</div><div class="mt-1 font-display text-3xl font-black">{{ $c->daily_budget ? Money::format($c->daily_budget) : '—' }}</div><div class="text-xs text-ink/50">сегодня {{ Money::format($c->spentToday()) }}</div></div>
    </div>

    @if ($c->status->isLive())
        <div class="card flex flex-wrap items-end gap-3">
            <div>
                <label class="label">Пополнить бюджет кампании</label>
                <input type="number" min="500" step="500" wire:model="addAmount" class="input w-44">
                @error('addAmount') <p class="error">{{ $message }}</p> @enderror
            </div>
            <button wire:click="addBudget" class="btn-primary">Добавить с баланса</button>
            <span class="pb-3 text-sm text-ink/50">свободно {{ Money::format(auth()->user()->available()) }}</span>
        </div>
    @endif

    <div class="grid items-start gap-6 lg:grid-cols-[1.5fr_1fr]">
        <div class="card">
            <h2 class="font-display text-lg font-black">Клики за 14 дней</h2>
            <div class="mt-6 flex h-44 items-end gap-1.5">
                @foreach ($byDay as $date => $count)
                    <div class="group flex flex-1 flex-col items-center gap-1" title="{{ \Carbon\Carbon::parse($date)->format('d.m') }}: {{ $count }}">
                        <div class="text-[10px] font-bold text-ink/50 opacity-0 group-hover:opacity-100">{{ $count }}</div>
                        <div class="w-full rounded-t-lg bg-gradient-to-t from-violet-600 to-pink-400" style="height: {{ max(3, $count / $max * 140) }}px"></div>
                        <div class="text-[10px] font-semibold text-ink/40">{{ \Carbon\Carbon::parse($date)->format('d') }}</div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card">
            <h2 class="font-display text-lg font-black">Площадки</h2>
            <div class="mt-4 space-y-3">
                @forelse ($c->placements as $p)
                    <div class="rounded-2xl bg-brand-50/60 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-bold">{{ $p->platform->label() }}</span>
                            <span class="badge-{{ $p->status === 'placed' ? 'success' : ($p->status === 'planned' ? 'info' : 'gray') }}">{{ $p->statusLabel() }}</span>
                        </div>
                        <div class="mt-1 text-sm text-ink/60">@php $n = $byPlatform[$p->platform->value] ?? 0; @endphp{{ $n }} {{ Money::plural($n, 'клик', 'клика', 'кликов') }} @if($p->erid) · ERID <span class="font-mono">{{ $p->erid }}</span>@endif</div>
                    </div>
                @empty
                    @foreach ($c->platformEnums() as $platform)
                        <div class="flex items-center justify-between rounded-2xl bg-brand-50/60 p-4"><span class="font-bold">{{ $platform->label() }}</span><span class="badge-gray">после модерации</span></div>
                    @endforeach
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-2">
        <div class="card">
            <h2 class="font-display text-lg font-black">Объявление</h2>
            @php $preview = $creatives->first()?->firstWhere('format', '16:9'); @endphp
            <div class="mt-4 overflow-hidden rounded-2xl ring-1 ring-black/5">
                @if ($preview)<img src="{{ $preview->url() }}" class="aspect-[16/9] w-full object-cover" alt="">@endif
                <div class="p-4">
                    <div class="font-bold text-[#2a5bd7]">{{ $c->title }}</div>
                    <div class="mt-1 text-sm text-ink/70">{{ $c->text }}</div>
                    @if ($c->long_text)<div class="mt-3 border-t pt-3 text-sm text-ink/60"><span class="badge-info mb-1">Для VK</span><br>{{ $c->long_text }}</div>@endif
                </div>
            </div>
            @if ($creatives->count() > 1)
                <div class="mt-4 grid grid-cols-4 gap-2">
                    @foreach ($creatives as $set)
                        <img src="{{ $set->firstWhere('format', '1:1')?->url() }}" class="aspect-square rounded-xl object-cover" alt="">
                    @endforeach
                </div>
            @endif
        </div>
        <div class="card space-y-4">
            <h2 class="font-display text-lg font-black">Аудитория</h2>
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                <dt class="font-bold text-ink/50">География</dt><dd>{{ $c->regionNames() ?: '—' }}</dd>
                <dt class="font-bold text-ink/50">Пол</dt><dd>{{ ['any' => 'Любой', 'male' => 'Мужчины', 'female' => 'Женщины'][$c->gender] }}</dd>
                <dt class="font-bold text-ink/50">Возраст</dt><dd>{{ $c->age_from }}–{{ $c->age_to }}{{ $c->age_unknown ? ', и с неизвестным возрастом' : '' }}</dd>
                <dt class="font-bold text-ink/50">Доход</dt><dd>{{ \App\Livewire\Wizard\CampaignWizard::INCOMES[$c->income] ?? '—' }}</dd>
                <dt class="font-bold text-ink/50">Интересы</dt><dd>{{ collect($c->interests ?? [])->join(', ') ?: '—' }}{{ $c->interests_custom ? '; '.$c->interests_custom : '' }}</dd>
            </dl>
            <h2 class="pt-2 font-display text-lg font-black">История</h2>
            <ol class="space-y-3 border-l-2 border-brand-100 pl-4">
                @foreach ($c->events as $e)
                    <li class="relative">
                        <span class="absolute -left-[1.4rem] top-1.5 h-3 w-3 rounded-full bg-gradient-to-br from-fuchsia-500 to-orange-400"></span>
                        <div class="text-sm font-bold">{{ $e->label() }}</div>
                        @if ($e->message)<div class="text-sm text-ink/60">{{ $e->message }}</div>@endif
                        <div class="text-xs text-ink/40">{{ $e->created_at->format('d.m.Y H:i') }}</div>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</div>
