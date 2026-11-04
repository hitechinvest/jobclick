@php use App\Support\Money; @endphp
<div class="space-y-6">
    <h1 class="font-display text-3xl font-black">Финансы</h1>

    <div class="grid gap-6 lg:grid-cols-[1fr_1.3fr]">
        <div class="rounded-3xl bg-gradient-to-br from-violet-600 via-fuchsia-600 to-pink-500 p-7 text-white shadow-xl shadow-fuchsia-500/20">
            <div class="text-sm font-bold text-white/75">Баланс</div>
            <div class="mt-2 font-display text-4xl font-black">{{ Money::format($user->balance, true) }}</div>
            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-2xl bg-white/15 p-3"><div class="text-white/70">Свободно</div><div class="font-bold">{{ Money::format($user->available(), true) }}</div></div>
                <div class="rounded-2xl bg-white/15 p-3"><div class="text-white/70">В кампаниях</div><div class="font-bold">{{ Money::format($user->reserved, true) }}</div></div>
            </div>
        </div>
        <div class="card">
            <h2 class="font-display text-lg font-black">Пополнить баланс</h2>
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ([5000, 10000, 20000, 50000] as $preset)
                    <button type="button" wire:click="$set('amount', {{ $preset }})" class="chip {{ $amount === $preset ? 'bg-fuchsia-600 text-white' : 'bg-brand-50 text-ink/70' }}">{{ Money::format($preset) }}</button>
                @endforeach
            </div>
            <div class="mt-4">
                <label class="label">Сумма, ₽</label>
                <input type="number" min="100" step="100" wire:model="amount" class="input">
                @error('amount') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <button wire:click="topupOnline" class="btn-primary" @disabled(! $onlineEnabled)>💳 Картой или СБП</button>
                <button wire:click="topupInvoice" class="btn-light" @disabled(! $invoiceEnabled)>🧾 Счёт для юрлица</button>
            </div>
            @if (! $onlineEnabled || ! $invoiceEnabled)
                <p class="hint">Часть способов оплаты временно недоступна — администратор подключает их.</p>
            @endif
            @unless ($user->company)
                <p class="hint">Для счёта укажите реквизиты компании в <a href="{{ route('cabinet.profile') }}" class="font-bold text-brand-700">профиле</a>.</p>
            @endunless
            <p class="hint">НДС не облагается. Деньги по счёту зачисляются после поступления на расчётный счёт.</p>
        </div>
    </div>

    @if ($invoices->isNotEmpty())
        <div class="card">
            <h2 class="font-display text-lg font-black">Счета</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-ink/50"><tr><th class="py-2">Номер</th><th>Дата</th><th>Сумма</th><th>Статус</th><th></th></tr></thead>
                    <tbody class="divide-y divide-brand-50">
                        @foreach ($invoices as $inv)
                            <tr class="{{ $invoice === $inv->id ? 'bg-yellow-50' : '' }}">
                                <td class="py-3 font-bold">{{ $inv->number }}</td>
                                <td>{{ $inv->created_at->format('d.m.Y') }}</td>
                                <td>{{ Money::format($inv->amount, true) }}</td>
                                <td><span class="badge-{{ ['issued' => 'warning', 'paid' => 'success', 'cancelled' => 'gray'][$inv->status] }}">{{ \App\Models\Invoice::STATUSES[$inv->status] }}</span></td>
                                <td class="text-right"><a href="{{ route('cabinet.invoices.pdf', $inv) }}" target="_blank" class="btn-light btn-sm">Скачать PDF</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card">
        <h2 class="font-display text-lg font-black">История операций</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-ink/50"><tr><th class="py-2">Дата</th><th>Операция</th><th>Кампания</th><th class="text-right">Сумма</th><th class="text-right">Баланс</th></tr></thead>
                <tbody class="divide-y divide-brand-50">
                    @forelse ($transactions as $t)
                        <tr>
                            <td class="py-2.5 whitespace-nowrap text-ink/60">{{ $t->created_at->format('d.m.Y H:i') }}</td>
                            <td class="font-semibold">{{ $t->type->label() }}<div class="text-xs font-normal text-ink/50">{{ $t->comment }}</div></td>
                            <td>@if($t->campaign)<a href="{{ route('cabinet.campaigns.show', $t->campaign) }}" class="text-brand-700 hover:underline">#{{ $t->campaign_id }}</a>@endif</td>
                            <td class="text-right font-bold whitespace-nowrap {{ (float) $t->amount < 0 ? 'text-rose-600' : (in_array($t->type->value, ['topup', 'adjust']) ? 'text-emerald-600' : 'text-ink/60') }}">{{ (float) $t->amount > 0 && in_array($t->type->value, ['topup', 'adjust']) ? '+' : '' }}{{ Money::format($t->amount, true) }}</td>
                            <td class="text-right whitespace-nowrap text-ink/60">{{ Money::format($t->balance_after, true) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-ink/50">Операций пока нет</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $transactions->links() }}</div>
    </div>
</div>
