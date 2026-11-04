<footer class="bg-ink text-white/70">
    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-4">
        <div class="md:col-span-2">
            <x-logo light />
            <p class="mt-4 max-w-md text-sm">Реклама вакансий в Яндекс Директе и VK Рекламе без агентства и рекламных кабинетов. Платите только за клики кандидатов.</p>
        </div>
        <div class="space-y-2 text-sm font-semibold">
            <div class="font-display text-white">Сервис</div>
            <a href="{{ route('home') }}#how" class="block hover:text-white">Как работает</a>
            <a href="{{ route('home') }}#prices" class="block hover:text-white">Цены</a>
            <a href="{{ route('login') }}" class="block hover:text-white">Личный кабинет</a>
        </div>
        <div class="space-y-2 text-sm font-semibold">
            <div class="font-display text-white">Документы</div>
            <a href="{{ route('offer') }}" class="block hover:text-white">Оферта</a>
            <a href="{{ route('privacy') }}" class="block hover:text-white">Политика обработки данных</a>
            @if ($email = \App\Support\Settings::get('requisites.email'))
                <a href="mailto:{{ $email }}" class="block hover:text-white">{{ $email }}</a>
            @endif
        </div>
    </div>
    <div class="border-t border-white/10 py-5 text-center text-xs">© {{ date('Y') }} JobClick. {{ \App\Support\Settings::get('requisites.name') }}</div>
</footer>
