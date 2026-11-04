@props(['light' => false])
<header x-data="{ open: false, scrolled: false }" @scroll.window="scrolled = window.scrollY > 20"
        :class="scrolled ? 'bg-white/90 shadow-lg shadow-violet-900/5 backdrop-blur' : ''"
        class="fixed inset-x-0 top-0 z-50 transition">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <span :class="scrolled ? 'hidden' : ''"><x-logo :light="$light" /></span>
        <span x-cloak :class="scrolled ? '' : 'hidden'"><x-logo /></span>
        <nav class="hidden items-center gap-1 font-bold md:flex" :class="scrolled ? 'text-ink' : '{{ $light ? 'text-white' : 'text-ink' }}'">
            <a href="{{ route('home') }}#how" class="rounded-xl px-3 py-2 hover:bg-white/15">Как работает</a>
            <a href="{{ route('home') }}#platforms" class="rounded-xl px-3 py-2 hover:bg-white/15">Площадки</a>
            <a href="{{ route('home') }}#prices" class="rounded-xl px-3 py-2 hover:bg-white/15">Цены</a>
            <a href="{{ route('home') }}#faq" class="rounded-xl px-3 py-2 hover:bg-white/15">Вопросы</a>
        </nav>
        <div class="flex items-center gap-2">
            @auth
                <a href="{{ route('cabinet.dashboard') }}" class="btn-hot btn-sm">Кабинет</a>
            @else
                <a href="{{ route('login') }}" class="btn-sm hidden rounded-xl px-3 py-2 font-bold sm:inline-flex" :class="scrolled ? 'text-ink hover:bg-brand-50' : '{{ $light ? 'text-white hover:bg-white/15' : 'text-ink' }}'">Войти</a>
                <a href="{{ route('login') }}" class="btn-hot btn-sm">Запустить рекламу</a>
            @endauth
        </div>
    </div>
</header>
