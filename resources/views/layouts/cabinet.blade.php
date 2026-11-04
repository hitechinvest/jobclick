@php
    $user = auth()->user();
    $nav = [
        ['cabinet.dashboard', 'Главная', '🏠'],
        ['cabinet.campaigns', 'Кампании', '🎯'],
        ['cabinet.finance', 'Финансы', '💳'],
        ['cabinet.library', 'Картинки', '🖼️'],
        ['cabinet.profile', 'Профиль', '👤'],
    ];
@endphp
<!doctype html>
<html lang="ru">
<head>
    <x-head :title="$title ?? 'Кабинет'" />
</head>
<body class="min-h-screen font-sans">
<div class="min-h-screen lg:flex">
    <aside class="hidden w-72 shrink-0 flex-col bg-gradient-to-b from-violet-800 via-fuchsia-800 to-ink p-6 text-white lg:flex">
        <x-logo light />
        <a href="{{ route('cabinet.campaigns.create') }}" class="btn-hot mt-8 w-full">+ Новая кампания</a>
        <nav class="mt-8 space-y-1">
            @foreach ($nav as [$route, $label, $icon])
                <a href="{{ route($route) }}" wire:navigate
                   class="flex items-center gap-3 rounded-2xl px-4 py-3 font-bold transition {{ request()->routeIs($route.'*') ? 'bg-white text-fuchsia-700 shadow-lg' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                    <span class="text-lg">{{ $icon }}</span>{{ $label }}
                </a>
            @endforeach
        </nav>
        <div class="mt-auto rounded-3xl bg-white/10 p-5 ring-1 ring-white/15">
            <div class="text-xs font-bold uppercase tracking-wider text-white/60">Баланс</div>
            <div class="mt-1 font-display text-2xl font-black">{{ \App\Support\Money::format($user->balance) }}</div>
            @if ((float) $user->reserved > 0)
                <div class="mt-1 text-xs text-white/70">в кампаниях: {{ \App\Support\Money::format($user->reserved) }}</div>
            @endif
            <a href="{{ route('cabinet.finance') }}" wire:navigate class="btn-sm mt-3 inline-flex w-full justify-center rounded-xl bg-yellow-300 font-bold text-ink hover:bg-yellow-200">Пополнить</a>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="mt-4">
            @csrf
            <button class="w-full truncate rounded-xl px-3 py-2 text-left text-sm text-white/60 hover:bg-white/10 hover:text-white">↩ Выйти ({{ $user->email }})</button>
        </form>
    </aside>

    <div class="flex-1">
        <header class="sticky top-0 z-30 flex items-center justify-between gap-3 bg-white/90 px-4 py-3 shadow-sm backdrop-blur lg:hidden">
            <x-logo />
            <div class="flex items-center gap-2">
                <a href="{{ route('cabinet.finance') }}" class="chip bg-brand-50 text-brand-800">{{ \App\Support\Money::format($user->balance) }}</a>
                <a href="{{ route('cabinet.campaigns.create') }}" class="btn-hot btn-sm">+</a>
            </div>
        </header>
        <nav class="flex gap-1 overflow-x-auto bg-white px-3 pb-3 lg:hidden">
            @foreach ($nav as [$route, $label, $icon])
                <a href="{{ route($route) }}" wire:navigate class="chip shrink-0 {{ request()->routeIs($route.'*') ? 'bg-fuchsia-600 text-white' : 'bg-brand-50 text-ink/70' }}">{{ $icon }} {{ $label }}</a>
            @endforeach
        </nav>
        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-8">
            <x-flash />
            {{ $slot }}
        </main>
    </div>
</div>
@livewireScripts
</body>
</html>
