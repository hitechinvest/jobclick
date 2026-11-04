@php use App\Support\Money; @endphp
<div class="space-y-6">
    {{-- Прогресс --}}
    <div class="card p-4 sm:p-5">
        <div class="flex items-center gap-1.5 overflow-x-auto sm:gap-2">
            @foreach ($steps as $n => $label)
                @php $done = $n < $step; $current = $n === $step; $reachable = $n <= max($step, $campaign->wizard_step); @endphp
                <button type="button" wire:click="goTo({{ $n }})" @disabled(! $reachable)
                        class="flex min-w-fit items-center gap-2 rounded-2xl px-2.5 py-2 text-sm font-bold transition sm:px-3 {{ $current ? 'bg-gradient-to-r from-fuchsia-600 to-violet-600 text-white shadow-lg shadow-fuchsia-500/30' : ($done ? 'text-brand-700 hover:bg-brand-50' : 'text-ink/35') }}">
                    <span class="grid h-7 w-7 place-items-center rounded-full text-xs {{ $current ? 'bg-white/25' : ($done ? 'bg-brand-100' : 'bg-slate-100') }}">{{ $done ? '✓' : $n }}</span>
                    <span class="hidden md:inline">{{ $label }}</span>
                </button>
                @if (! $loop->last)<span class="h-0.5 min-w-3 flex-1 rounded {{ $done ? 'bg-brand-300' : 'bg-slate-100' }}"></span>@endif
            @endforeach
        </div>
    </div>

    @if ($campaign->status === \App\Enums\CampaignStatus::NeedsChanges && $campaign->moderation_comment)
        <div class="rounded-3xl bg-amber-50 p-5 text-amber-900 ring-1 ring-amber-200"><b>Модератор просит поправить:</b> {{ $campaign->moderation_comment }}</div>
    @endif

    <div class="grid gap-6 {{ $step > 1 ? 'lg:grid-cols-[1fr_360px]' : '' }}">
        <div class="card p-6 sm:p-8">
            <div wire:key="wizard-step-{{ $step }}">
                @include('livewire.wizard.step-'.$step)
            </div>

            <div class="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-brand-50 pt-6">
                @if ($step > 1)
                    <button type="button" wire:click="back" class="btn-ghost">← Назад</button>
                @else
                    <span></span>
                @endif
                @if ($step < 7)
                    <button type="button" wire:click="next" class="btn-primary px-8" wire:loading.attr="disabled" wire:target="next">
                        <span wire:loading.remove wire:target="next">Дальше →</span>
                        <span wire:loading wire:target="next">Сохраняем…</span>
                    </button>
                @endif
            </div>
        </div>

        @if ($step > 1)
            <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
                <div class="text-xs font-bold uppercase tracking-wider text-ink/40">Так увидят кандидаты</div>
                <div class="overflow-hidden rounded-3xl bg-white shadow-xl ring-1 ring-black/5">
                    <div class="flex items-center gap-2.5 px-4 py-3">
                        <span class="grid h-9 w-9 place-items-center rounded-full bg-gradient-to-br from-sky-500 to-blue-600 text-xs font-black text-white">VK</span>
                        <div class="min-w-0"><div class="truncate text-sm font-bold">{{ auth()->user()->company?->displayName() ?? 'Ваша компания' }}</div><div class="text-xs text-ink/45">Реклама</div></div>
                    </div>
                    <div class="aspect-[4/3] bg-gradient-to-br from-violet-200 via-fuchsia-200 to-orange-200">
                        @if ($previewImage)<img src="{{ $previewImage }}" class="h-full w-full object-cover" alt="">@else<div class="grid h-full place-items-center text-6xl">🖼️</div>@endif
                    </div>
                    <div class="space-y-1 p-4">
                        <div class="font-bold leading-snug">{{ $title ?: 'Заголовок объявления' }}</div>
                        <div class="text-sm text-ink/65">{{ $longText ?: ($text ?: 'Текст объявления') }}</div>
                        <div class="pt-2"><span class="inline-flex rounded-lg bg-sky-500 px-4 py-1.5 text-sm font-bold text-white">Откликнуться</span></div>
                    </div>
                </div>
                <div class="rounded-3xl bg-white p-4 shadow-xl ring-1 ring-black/5">
                    <div class="flex gap-3">
                        @if ($previewImage)<img src="{{ $previewImage }}" class="h-20 w-20 shrink-0 rounded-xl object-cover" alt="">@endif
                        <div class="min-w-0">
                            <div class="text-xs font-bold text-emerald-700">Реклама · Яндекс</div>
                            <div class="text-sm font-bold leading-snug text-[#2a5bd7]">{{ $title ?: 'Заголовок объявления' }}</div>
                            <div class="text-xs text-ink/60">{{ $text ?: 'Текст объявления' }}</div>
                        </div>
                    </div>
                </div>
            </aside>
        @endif
    </div>
</div>
