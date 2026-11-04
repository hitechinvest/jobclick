<div>
    <h1 class="font-display text-2xl font-black">Картинки</h1>
    <p class="mt-1 text-ink/60">Выберите до 5 картинок — мы нарежем их под все форматы площадок (1:1, 16:9, 4:5).</p>

    <div class="mt-6 flex flex-wrap gap-2">
        @foreach (['library' => '🖼️ Моя библиотека', 'vacancy' => '🔗 Из вакансии ('.count($vacancyImages).')', 'generate' => '✨ Сгенерировать', 'upload' => '⬆ Загрузить'] as $tab => $label)
            <button type="button" wire:click="$set('imageTab', '{{ $tab }}')" class="chip {{ $imageTab === $tab ? 'bg-fuchsia-600 text-white shadow-lg shadow-fuchsia-500/30' : 'bg-brand-50 text-ink/70' }}">{{ $label }}</button>
        @endforeach
    </div>
    @error('images') <p class="error">{{ $message }}</p> @enderror

    <div class="mt-5">
        @if ($imageTab === 'library')
            <div class="grid grid-cols-3 gap-3 sm:grid-cols-4">
                @forelse ($library as $img)
                    @php $on = in_array($img->id, $selectedImages, true); @endphp
                    <button type="button" wire:key="lib-{{ $img->id }}" wire:click="toggleImage({{ $img->id }})"
                            class="group relative aspect-square overflow-hidden rounded-2xl ring-4 transition {{ $on ? 'ring-fuchsia-500' : 'ring-transparent hover:ring-brand-200' }}">
                        <img src="{{ $img->url() }}" class="h-full w-full object-cover" alt="" loading="lazy">
                        <span class="absolute right-1.5 top-1.5 grid h-7 w-7 place-items-center rounded-full text-sm font-black {{ $on ? 'bg-fuchsia-600 text-white' : 'bg-white/80 text-transparent group-hover:text-ink/30' }}">✓</span>
                        @unless ($img->isSuitable())<span class="absolute bottom-1.5 left-1.5 badge-warning">маленькая</span>@endunless
                    </button>
                @empty
                    <div class="col-span-full rounded-2xl bg-brand-50 p-8 text-center text-ink/60">Библиотека пуста — возьмите картинку из вакансии, сгенерируйте или загрузите свою.</div>
                @endforelse
            </div>
        @elseif ($imageTab === 'vacancy')
            <div class="grid grid-cols-3 gap-3 sm:grid-cols-4">
                @forelse ($vacancyImages as $i => $url)
                    <div wire:key="vac-{{ $i }}" x-data="{ w: 0, h: 0, failed: false }" x-show="!failed" class="relative aspect-square overflow-hidden rounded-2xl bg-brand-50">
                        <img src="{{ $url }}" referrerpolicy="no-referrer" class="h-full w-full object-cover" alt="" loading="lazy" x-on:load="w = $el.naturalWidth; h = $el.naturalHeight" x-on:error="failed = true">
                        <span class="absolute left-1.5 top-1.5" x-show="w"><span :class="w >= 450 && h >= 450 ? 'badge-success' : 'badge-warning'" x-text="w + '×' + h"></span></span>
                        <button type="button" wire:click="importImage(@js($url))" class="absolute inset-x-1.5 bottom-1.5 rounded-xl bg-white/95 py-1.5 text-xs font-bold text-fuchsia-700 shadow hover:bg-white" wire:loading.attr="disabled">+ Выбрать</button>
                    </div>
                @empty
                    <div class="col-span-full rounded-2xl bg-brand-50 p-8 text-center text-ink/60">На странице вакансии картинок не нашлось.</div>
                @endforelse
            </div>
            <p class="hint">Для сетей нужны картинки от 450×450. Зелёный размер — подходит.</p>
        @elseif ($imageTab === 'generate')
            @if (! $qwenReady)
                <div class="rounded-2xl bg-amber-50 p-5 text-amber-900">Генерация картинок скоро появится — администратор подключает нейросеть. Пока загрузите свою картинку.</div>
            @else
                <div class="space-y-4 rounded-3xl bg-gradient-to-br from-violet-50 via-fuchsia-50 to-orange-50 p-5">
                    <div>
                        <label class="label">Что нарисовать</label>
                        <textarea wire:model="prompt" rows="4" class="input"></textarea>
                        <p class="hint">По умолчанию — по заголовку объявления. Опишите сцену: кто, где, какая атмосфера.</p>
                        @error('prompt') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex gap-1.5">
                            @foreach (['1:1' => 'Квадрат', '16:9' => 'Широкая', '4:5' => 'Вертикальная'] as $f => $label)
                                <button type="button" wire:click="$set('genFormat', '{{ $f }}')" class="chip text-xs {{ $genFormat === $f ? 'bg-ink text-white' : 'bg-white text-ink/70' }}">{{ $label }}</button>
                            @endforeach
                        </div>
                        @if ($qwenProReady)
                            <label class="flex cursor-pointer items-center gap-2 text-sm font-bold">
                                <input type="checkbox" wire:model.live="pro" class="h-5 w-5 rounded accent-fuchsia-600"> Pro-качество
                            </label>
                        @endif
                    </div>
                    <button type="button" wire:click="generateImage" class="btn-hot" wire:loading.attr="disabled" wire:target="generateImage">
                        <span wire:loading.remove wire:target="generateImage">✨ Сгенерировать картинку</span>
                        <span wire:loading wire:target="generateImage">Рисуем… до минуты</span>
                    </button>
                </div>
            @endif
        @else
            <label class="flex cursor-pointer flex-col items-center gap-2 rounded-3xl border-2 border-dashed border-brand-300 bg-brand-50/50 p-10 text-center transition hover:bg-brand-50">
                <input type="file" wire:model="uploads" multiple accept="image/jpeg,image/png,image/webp" class="hidden">
                <span class="text-4xl">⬆</span>
                <span class="font-bold" wire:loading.remove wire:target="uploads">Выберите файлы JPG, PNG или WebP до 10 МБ</span>
                <span class="font-bold" wire:loading wire:target="uploads">Загружаем…</span>
                <span class="text-sm text-ink/50">Картинки сохранятся в библиотеке навсегда</span>
            </label>
            @error('uploads') <p class="error">{{ $message }}</p> @enderror
            @error('uploads.*') <p class="error">{{ $message }}</p> @enderror
        @endif
    </div>

    @if ($selectedImages)
        <div class="mt-6 rounded-3xl bg-brand-50/60 p-4">
            <div class="text-sm font-bold">Выбрано: {{ count($selectedImages) }} из 5 · главный объект на картинке</div>
            <div class="mt-3 flex flex-wrap gap-4">
                @foreach (\App\Models\Image::whereIn('id', $selectedImages)->get() as $img)
                    <div wire:key="sel-{{ $img->id }}" class="w-36">
                        <img src="{{ $img->url() }}" class="aspect-square w-full rounded-2xl object-cover" alt="">
                        <select wire:model="focus.{{ $img->id }}" class="input mt-2 px-2 py-1.5 text-xs">
                            @foreach (\App\Services\ImageService::FOCUS as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
