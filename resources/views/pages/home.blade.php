@php
    use App\Enums\Platform;
    use App\Support\Money;
    use App\Support\Settings;
    $cpcPremium = Settings::float('tariff.cpc_premium');
    $cpcDefault = Settings::float('tariff.cpc_default');
    $minBudget = Settings::float('tariff.min_total_budget');
@endphp
<x-layouts.site light>
    {{-- HERO --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-violet-700 via-fuchsia-600 to-orange-500 bg-[length:200%_200%] animate-gradient pb-24 pt-28 text-white sm:pt-32">
        <div class="pointer-events-none absolute -left-32 top-10 h-[30rem] w-[30rem] rounded-full bg-yellow-300/30 blur-3xl animate-float-slow"></div>
        <div class="pointer-events-none absolute -right-20 bottom-0 h-[26rem] w-[26rem] rounded-full bg-cyan-300/30 blur-3xl animate-float"></div>
        <div class="pointer-events-none absolute left-[46%] top-[62%] hidden text-6xl lg:block animate-float">💼</div>
        <div class="pointer-events-none absolute bottom-16 left-8 hidden text-5xl lg:block animate-float-slow">⚡</div>

        <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-[1.1fr_1fr]">
            <div>
                <span class="chip bg-white/15 text-white ring-1 ring-white/30 backdrop-blur">🔥 Кандидаты уже завтра — без агентства</span>
                <h1 class="mt-6 font-display text-4xl font-black leading-[1.05] sm:text-6xl">
                    Закройте вакансию <span class="relative whitespace-nowrap text-yellow-300">в 3 раза быстрее<svg class="absolute -bottom-2 left-0 w-full" viewBox="0 0 300 12" fill="none"><path d="M2 9c60-6 180-8 296-2" stroke="#fde047" stroke-width="5" stroke-linecap="round"/></svg></span>
                </h1>
                <p class="mt-6 max-w-xl text-lg font-medium text-white/90 sm:text-xl">
                    Вставьте ссылку на вакансию — ИИ напишет объявление и нарисует картинку, а мы покажем его тысячам кандидатов в Яндексе, ВКонтакте и Одноклассниках.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('login') }}" class="btn bg-white px-7 py-4 text-lg text-fuchsia-700 shadow-2xl shadow-fuchsia-900/30 hover:-translate-y-0.5">Запустить за 5 минут →</a>
                    <a href="#how" class="btn bg-white/15 px-7 py-4 text-lg text-white ring-1 ring-white/40 backdrop-blur hover:bg-white/25">Как это работает</a>
                </div>
                <div class="mt-10 grid max-w-xl grid-cols-3 gap-3">
                    @foreach ([['от '.Money::format($cpcDefault), 'за клик кандидата'], ['5 площадок', 'Яндекс и VK сразу'], ['5 минут', 'на запуск кампании']] as [$big, $small])
                        <div class="rounded-2xl bg-white/15 p-4 ring-1 ring-white/25 backdrop-blur">
                            <div class="font-display text-base font-black whitespace-nowrap sm:text-xl">{{ $big }}</div>
                            <div class="text-xs font-semibold text-white/80 sm:text-sm">{{ $small }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="text-ink">
                <livewire:landing.calculator />
            </div>
        </div>
    </section>

    {{-- PLATFORMS STRIP --}}
    <section id="platforms" class="relative -mt-10 px-4 sm:px-6">
        <div class="mx-auto max-w-6xl rounded-[2rem] bg-white p-6 shadow-2xl shadow-violet-900/10 sm:p-8">
            <p class="text-center text-sm font-bold uppercase tracking-widest text-ink/40">Показываем вашу вакансию там, где сидят кандидаты</p>
            <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                @php
                    $colors = [
                        'yandex_search' => ['from-red-500 to-orange-400', 'Я'],
                        'yandex_rsya' => ['from-amber-400 to-yellow-300', 'РСЯ'],
                        'vkontakte' => ['from-sky-500 to-blue-600', 'VK'],
                        'odnoklassniki' => ['from-orange-400 to-amber-500', 'OK'],
                        'vk_network' => ['from-indigo-500 to-violet-600', 'VK+'],
                    ];
                @endphp
                @foreach (Platform::cases() as $platform)
                    <div class="group flex items-center gap-3 rounded-2xl bg-slate-50 p-3 transition hover:-translate-y-1 hover:bg-white hover:shadow-xl">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-gradient-to-br {{ $colors[$platform->value][0] }} font-display text-sm font-black text-white shadow-lg">{{ $colors[$platform->value][1] }}</span>
                        <span class="text-sm font-bold leading-tight">{{ $platform->label() }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- HOW IT WORKS --}}
    <section id="how" class="mx-auto max-w-7xl px-4 py-24 sm:px-6">
        <div class="mx-auto max-w-2xl text-center">
            <span class="chip bg-fuchsia-100 text-fuchsia-700">Проще, чем разместить вакансию</span>
            <h2 class="mt-4 font-display text-3xl font-black sm:text-5xl">Четыре шага до <span class="gradient-text">первых откликов</span></h2>
        </div>
        <div class="mt-14 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['🔗', 'Вставьте ссылку', 'С hh.ru, Авито, Зарплата.ру, SuperJob, ГдеРабота или «Работа России» — подтянем название, зарплату и картинки.', 'from-violet-500 to-fuchsia-500'],
                ['✨', 'ИИ сделает объявление', 'Цепляющий заголовок и текст под лимиты Директа и VK. Картинку нарисует нейросеть — или загрузите свою.', 'from-fuchsia-500 to-pink-500'],
                ['🎯', 'Выберите кандидатов', 'Город, пол, возраст, доход и интересы. Сразу видно, сколько кликов принесёт бюджет.', 'from-pink-500 to-orange-400'],
                ['🚀', 'Получайте отклики', 'Модерация до 1 рабочего дня, и показы пошли. Платите только за переходы на вакансию.', 'from-orange-400 to-yellow-400'],
            ] as $i => [$icon, $title, $text, $gradient])
                <div class="card group relative overflow-hidden transition hover:-translate-y-2">
                    <div class="absolute -right-6 -top-6 font-display text-8xl font-black text-brand-50 transition group-hover:text-fuchsia-50">{{ $i + 1 }}</div>
                    <div class="relative grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br {{ $gradient }} text-2xl shadow-lg">{{ $icon }}</div>
                    <h3 class="relative mt-5 font-display text-lg font-black">{{ $title }}</h3>
                    <p class="relative mt-2 text-ink/65">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- AD PREVIEW --}}
    <section class="relative overflow-hidden bg-ink py-24 text-white">
        <div class="pointer-events-none absolute -left-40 top-0 h-[30rem] w-[30rem] rounded-full bg-fuchsia-600/30 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-40 bottom-0 h-[30rem] w-[30rem] rounded-full bg-orange-500/20 blur-3xl"></div>
        <div class="relative mx-auto grid max-w-7xl items-center gap-14 px-4 sm:px-6 lg:grid-cols-2">
            <div>
                <span class="chip bg-white/10 text-yellow-300">ИИ-копирайтер и ИИ-дизайнер</span>
                <h2 class="mt-4 font-display text-3xl font-black sm:text-5xl">Объявление, на которое <span class="text-yellow-300">хочется кликнуть</span></h2>
                <p class="mt-5 text-lg text-white/75">Нейросеть читает вакансию и пишет текст, который продаёт работу: зарплата, график, главные плюсы. Без дискриминационных формулировок — модерация сетей пропустит.</p>
                <ul class="mt-8 space-y-4">
                    @foreach (['Заголовок и тексты под лимиты Директа и VK', 'Картинка без дизайнера за 30 секунд', 'Автонарезка под все форматы площадок', 'Маркировка рекламы и ERID — берём на себя'] as $item)
                        <li class="flex items-center gap-3 font-semibold"><span class="grid h-7 w-7 place-items-center rounded-full bg-gradient-to-br from-lime-300 to-emerald-400 text-sm text-ink">✓</span>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="relative mx-auto w-full max-w-md">
                <div class="absolute -left-10 -top-8 rotate-[-8deg] rounded-2xl bg-yellow-300 px-4 py-2 font-display text-sm font-black text-ink shadow-xl animate-float">+148 откликов</div>
                <div class="rotate-2 overflow-hidden rounded-[1.75rem] bg-white text-ink shadow-2xl">
                    <div class="flex items-center gap-3 px-4 py-3">
                        <span class="grid h-10 w-10 place-items-center rounded-full bg-gradient-to-br from-sky-500 to-blue-600 font-black text-white">VK</span>
                        <div><div class="text-sm font-bold">Вкусная пекарня</div><div class="text-xs text-ink/50">Реклама · 18+</div></div>
                    </div>
                    <div class="relative aspect-[4/3] bg-gradient-to-br from-amber-200 via-orange-300 to-pink-400">
                        <div class="absolute inset-0 grid place-items-center text-8xl">🥐</div>
                    </div>
                    <div class="space-y-1 p-4">
                        <div class="font-bold">Пекарь в утреннюю смену — от 75 000 ₽</div>
                        <div class="text-sm text-ink/65">График 2/2, бесплатные обеды и форма. Обучим с нуля. Откликнитесь сегодня!</div>
                        <div class="pt-2"><span class="btn-sm inline-flex rounded-lg bg-sky-500 px-4 py-2 text-sm font-bold text-white">Откликнуться</span></div>
                    </div>
                </div>
                <div class="absolute -bottom-10 -right-6 w-60 -rotate-3 rounded-2xl bg-white p-4 text-ink shadow-2xl">
                    <div class="text-xs font-bold text-emerald-600">Яндекс · Реклама</div>
                    <div class="mt-1 text-sm font-bold text-[#2a5bd7]">Курьер на авто — до 120 000 ₽</div>
                    <div class="text-xs text-ink/60">Свободный график, выплаты каждый день. Откликнитесь за 1 минуту.</div>
                </div>
            </div>
        </div>
    </section>

    {{-- FEATURES --}}
    <section class="mx-auto max-w-7xl px-4 py-24 sm:px-6">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="font-display text-3xl font-black sm:text-5xl">Всё, чтобы <span class="gradient-text">быстро нанять</span></h2>
            <p class="mt-4 text-lg text-ink/60">Без рекламных кабинетов, договоров с агентствами и минимальных пакетов за 50 000 ₽.</p>
        </div>
        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['📊', 'Прогноз по Вордстату', 'Видите реальный спрос на вакансию в вашем городе и сколько кликов даст бюджет — ещё до оплаты.', 'bg-violet-100'],
                ['💸', 'Оплата только за клики', 'Списываем фиксированную цену за переход кандидата. Показы бесплатно, неистраченное возвращается на баланс.', 'bg-pink-100'],
                ['🧾', 'Счёт для юрлица с QR', 'Оплатите картой, СБП или по счёту — QR-код сканируется приложением банка за секунды.', 'bg-orange-100'],
                ['🛡️', 'Модерация и маркировка', 'Проверяем объявление до запуска и получаем ERID — реклама по закону, без штрафов.', 'bg-emerald-100'],
                ['🖼️', 'Библиотека картинок', 'Загруженные и сгенерированные картинки сохраняются навсегда — используйте в новых кампаниях.', 'bg-sky-100'],
                ['📈', 'Статистика в реальном времени', 'Клики по дням и площадкам, остаток бюджета, письмо с результатами через 3 дня.', 'bg-yellow-100'],
            ] as [$icon, $title, $text, $bg])
                <div class="card transition hover:-translate-y-1 hover:shadow-2xl">
                    <div class="grid h-14 w-14 place-items-center rounded-2xl {{ $bg }} text-3xl">{{ $icon }}</div>
                    <h3 class="mt-5 font-display text-lg font-black">{{ $title }}</h3>
                    <p class="mt-2 text-ink/65">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- PRICES --}}
    <section id="prices" class="px-4 pb-24 sm:px-6">
        <div class="mx-auto max-w-6xl overflow-hidden rounded-[2.5rem] bg-gradient-to-br from-fuchsia-600 via-violet-700 to-indigo-800 p-8 text-white shadow-2xl shadow-violet-900/30 sm:p-14">
            <div class="grid items-center gap-10 lg:grid-cols-2">
                <div>
                    <h2 class="font-display text-3xl font-black sm:text-5xl">Честная цена за каждый клик</h2>
                    <p class="mt-4 text-lg text-white/80">Никаких абонентских плат. Пополняете баланс, запускаете кампанию — деньги списываются только за переходы кандидатов на вашу вакансию.</p>
                    <a href="{{ route('login') }}" class="btn mt-8 bg-yellow-300 px-7 py-4 text-lg text-ink hover:-translate-y-0.5 hover:bg-yellow-200">Начать от {{ Money::format($minBudget) }}</a>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-3xl bg-white p-6 text-ink">
                        <div class="text-sm font-bold text-ink/50">Москва и Санкт-Петербург</div>
                        <div class="mt-2 font-display text-5xl font-black gradient-text">{{ Money::format($cpcPremium) }}</div>
                        <div class="mt-1 font-semibold text-ink/60">за клик</div>
                    </div>
                    <div class="rounded-3xl bg-yellow-300 p-6 text-ink">
                        <div class="text-sm font-bold text-ink/60">Все остальные города</div>
                        <div class="mt-2 font-display text-5xl font-black">{{ Money::format($cpcDefault) }}</div>
                        <div class="mt-1 font-semibold text-ink/70">за клик</div>
                    </div>
                    <div class="rounded-3xl bg-white/10 p-6 ring-1 ring-white/20 sm:col-span-2">
                        <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm font-semibold">
                            <span>✓ Бюджет от {{ Money::format($minBudget) }}</span>
                            <span>✓ Дневной лимит по желанию</span>
                            <span>✓ Возврат неизрасходованного</span>
                            <span>✓ Без НДС</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="mx-auto max-w-3xl px-4 pb-24 sm:px-6">
        <h2 class="text-center font-display text-3xl font-black sm:text-4xl">Частые вопросы</h2>
        <div class="mt-10 space-y-3" x-data="{ open: 0 }">
            @foreach ([
                ['Нужен ли мне кабинет в Яндекс Директе или VK?', 'Нет. Мы размещаем рекламу со своих аккаунтов и сами получаем маркировку ERID. Вам нужны только ссылка на вакансию и ИНН компании.'],
                ['Как быстро начнутся показы?', 'Модерация занимает до одного рабочего дня. После одобрения мы размещаем объявление на площадках, и вы получаете письмо о запуске.'],
                ['Что если вакансия закроется раньше?', 'Остановите кампанию в кабинете в один клик — неизрасходованный бюджет вернётся на баланс. Если вакансию перенесут в архив, мы остановим показы сами.'],
                ['Как считается прогноз кликов?', 'Мы берём реальное число поисковых запросов из Яндекс Вордстата по вашей вакансии в выбранных городах и оцениваем, какую часть этих людей приведёт объявление.'],
                ['Можно ли оплатить по счёту?', 'Да. Укажите ИНН — счёт с QR-кодом сформируется автоматически. Оплатить можно и картой или через СБП онлайн.'],
                ['Какие ссылки на вакансии подходят?', 'hh.ru, Авито Работа, Зарплата.ру, SuperJob, ГдеРабота, «Работа России» и ваш карьерный сайт. Если сайт не отдаёт данные, просто вставьте текст вакансии.'],
            ] as $i => [$q, $a])
                <div class="card p-0">
                    <button type="button" @click="open = open === {{ $i }} ? null : {{ $i }}" class="flex w-full items-center justify-between gap-4 px-6 py-5 text-left font-display font-bold">
                        {{ $q }}
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-700 transition" :class="open === {{ $i }} ? 'rotate-45 bg-fuchsia-500 text-white' : ''">+</span>
                    </button>
                    <div x-cloak x-show="open === {{ $i }}" x-collapse class="px-6 pb-6 text-ink/70">{{ $a }}</div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- FINAL CTA --}}
    <section class="px-4 pb-24 sm:px-6">
        <div class="relative mx-auto max-w-6xl overflow-hidden rounded-[2.5rem] bg-gradient-to-r from-orange-400 via-pink-500 to-fuchsia-600 px-8 py-16 text-center text-white shadow-2xl shadow-pink-500/30">
            <div class="pointer-events-none absolute -left-10 -top-10 text-9xl opacity-20 animate-float">🎯</div>
            <div class="pointer-events-none absolute -bottom-10 -right-6 text-9xl opacity-20 animate-float-slow">🚀</div>
            <h2 class="relative font-display text-3xl font-black sm:text-5xl">Ваши кандидаты уже ищут работу</h2>
            <p class="relative mx-auto mt-4 max-w-2xl text-lg text-white/90">Покажите им вакансию сегодня — первые клики уже завтра.</p>
            <a href="{{ route('login') }}" class="btn relative mt-8 bg-white px-8 py-4 text-lg text-fuchsia-700 shadow-xl hover:-translate-y-0.5">Создать кампанию бесплатно →</a>
            <p class="relative mt-3 text-sm text-white/80">Регистрация за 30 секунд по e-mail</p>
        </div>
    </section>
</x-layouts.site>
