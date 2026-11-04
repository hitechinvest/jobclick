<div class="space-y-6">
    <h1 class="font-display text-3xl font-black">Профиль</h1>
    <div class="grid gap-6 lg:grid-cols-[1fr_1.6fr]">
        <div class="space-y-6">
            <div class="card space-y-4">
                <div>
                    <div class="label">E-mail для входа</div>
                    <div class="font-bold">{{ $user->email }}</div>
                </div>
                <form wire:submit="saveName">
                    <label class="label">Как к вам обращаться</label>
                    <div class="flex gap-2"><input type="text" wire:model="name" class="input" placeholder="Имя"><button class="btn-light">OK</button></div>
                </form>
                <div>
                    <div class="label">Быстрый вход</div>
                    @foreach ($providers as $key => $label)
                        <div class="flex items-center justify-between py-1.5 text-sm">
                            <span class="font-semibold">{{ $label }}</span>
                            <span class="{{ $user->{$key.'_id'} ? 'badge-success' : 'badge-gray' }}">{{ $user->{$key.'_id'} ? 'привязан' : 'не привязан' }}</span>
                        </div>
                    @endforeach
                    <p class="hint">Аккаунты привязываются автоматически при входе с тем же e-mail.</p>
                </div>
            </div>
        </div>
        <form wire:submit="save" class="card space-y-5">
            <div>
                <h2 class="font-display text-lg font-black">Компания-рекламодатель</h2>
                <p class="text-sm text-ink/60">Одна компания на аккаунт. Её ИНН передаётся в ОРД для маркировки рекламы.</p>
            </div>
            <x-company-form />
            <button type="submit" class="btn-primary">Сохранить реквизиты</button>
        </form>
    </div>
</div>
