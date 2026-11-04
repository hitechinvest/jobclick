<div>
    <h1 class="font-display text-2xl font-black">Заголовок и текст объявления</h1>
    <p class="mt-1 text-ink/60">ИИ уже подготовил варианты по вакансии. Отредактируйте или сгенерируйте заново.</p>

    <div class="mt-6 flex flex-wrap items-center gap-2 rounded-2xl bg-gradient-to-r from-violet-50 to-fuchsia-50 p-3">
        <span class="text-sm font-bold text-ink/60">Тон:</span>
        @foreach ($this::TONES as $key => $label)
            <button type="button" wire:click="$set('tone', '{{ $key }}')" class="chip text-xs {{ $tone === $key ? 'bg-fuchsia-600 text-white' : 'bg-white text-ink/70' }}">{{ $label }}</button>
        @endforeach
        <button type="button" wire:click="generateTexts" class="btn-hot btn-sm ml-auto" wire:loading.attr="disabled" wire:target="generateTexts">
            <span wire:loading.remove wire:target="generateTexts">✨ Сгенерировать заново</span>
            <span wire:loading wire:target="generateTexts">Пишем…</span>
        </button>
    </div>

    <div class="mt-6 space-y-5">
        @foreach ([
            ['title', 'Заголовок', 56, 'input', 'Название должности + главный плюс. Яндекс Директ — до 56 символов, VK — до 40 в коротком формате.'],
            ['text', 'Текст', 81, 'textarea2', 'Для Яндекс Директа и короткого описания VK — до 81 символа.'],
            ['longText', 'Длинный текст для VK', 220, 'textarea4', 'Показывается в ленте ВКонтакте и Одноклассников. Расскажите об условиях и плюсах.'],
        ] as [$field, $label, $limit, $type, $hint])
            <div wire:key="f-{{ $field }}" x-data="{ n: {{ mb_strlen($this->{$field}) }} }" x-on:input="n = $event.target.value.length">
                <div class="flex justify-between"><label class="label">{{ $label }}</label><span class="text-xs font-bold" :class="n > {{ $limit }} ? 'text-rose-600' : 'text-ink/40'" x-text="n + ' / {{ $limit }}'"></span></div>
                @if ($type === 'input')
                    <input type="text" wire:model.live.debounce.400ms="{{ $field }}" class="input text-lg font-bold" maxlength="{{ $limit + 14 }}">
                @else
                    <textarea wire:model.live.debounce.400ms="{{ $field }}" rows="{{ $type === 'textarea2' ? 2 : 4 }}" class="input" maxlength="{{ $limit + 20 }}"></textarea>
                @endif
                <p class="hint">{{ $hint }}</p>
                @error($field) <p class="error">{{ $message }}</p> @enderror
            </div>
        @endforeach
    </div>
    <div class="mt-5 rounded-2xl bg-amber-50 p-4 text-sm text-amber-900">
        ⚠️ Не указывайте требования к полу, возрасту, национальности и внешности — это запрещено законом и модерация не пропустит объявление.
    </div>
</div>
