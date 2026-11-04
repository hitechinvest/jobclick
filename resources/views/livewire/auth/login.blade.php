<div class="w-full max-w-md">
    <div class="card p-8 sm:p-10">
        <x-flash />
        @if (! $sent)
            <h1 class="font-display text-2xl font-black">Вход и регистрация</h1>
            <p class="mt-2 text-ink/60">Пароль не нужен — пришлём код на почту. Первый вход создаст аккаунт.</p>

            <form wire:submit="send" class="mt-7 space-y-4">
                <div>
                    <label class="label" for="email">E-mail</label>
                    <input id="email" type="email" wire:model="email" class="input" placeholder="hr@company.ru" autocomplete="email" autofocus>
                    @error('email') <p class="error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="send">Получить код</span>
                    <span wire:loading wire:target="send">Отправляем…</span>
                </button>
            </form>

            @if ($providers->isNotEmpty())
                <div class="my-6 flex items-center gap-3 text-xs font-bold uppercase tracking-wider text-ink/40">
                    <span class="h-px flex-1 bg-ink/10"></span>или<span class="h-px flex-1 bg-ink/10"></span>
                </div>
                <div class="grid gap-3">
                    @if ($providers->has('yandex'))
                        <a href="{{ route('oauth.redirect', 'yandex') }}" class="btn bg-[#fc3f1d] text-white hover:bg-[#e2361a]">
                            <span class="grid h-6 w-6 place-items-center rounded-full bg-white font-black text-[#fc3f1d]">Я</span> Войти с Яндекс ID
                        </a>
                    @endif
                    @if ($providers->has('mailru'))
                        <a href="{{ route('oauth.redirect', 'mailru') }}" class="btn bg-[#005ff9] text-white hover:bg-[#0052d6]">
                            <span class="grid h-6 w-6 place-items-center rounded-full bg-[#ff9e00] font-black text-white">@</span> Войти через Mail.ru
                        </a>
                    @endif
                </div>
            @endif
        @else
            <h1 class="font-display text-2xl font-black">Введите код</h1>
            <p class="mt-2 text-ink/60">Отправили 6 цифр на <b class="text-ink">{{ $email }}</b>. Письмо может прийти за минуту, загляните в «Спам».</p>

            <form wire:submit="verify" class="mt-7 space-y-4">
                <div>
                    <input type="text" inputmode="numeric" maxlength="6" wire:model="code" class="input text-center font-display text-3xl tracking-[0.5em]" placeholder="••••••" autocomplete="one-time-code" autofocus>
                    @error('code') <p class="error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">Войти</button>
            </form>
            <div class="mt-5 flex justify-between text-sm font-semibold">
                <button type="button" wire:click="back" class="text-brand-700 hover:underline">← Другой e-mail</button>
                <button type="button" wire:click="send" class="text-brand-700 hover:underline">Отправить ещё раз</button>
            </div>
        @endif
    </div>
    <p class="mt-6 text-center text-sm text-white/80">Продолжая, вы принимаете <a href="{{ route('offer') }}" class="underline">оферту</a> и <a href="{{ route('privacy') }}" class="underline">политику обработки данных</a>.</p>
</div>
