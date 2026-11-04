<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-black">Библиотека картинок</h1>
            <p class="mt-1 text-ink/60">Все загруженные и сгенерированные картинки хранятся здесь всегда.</p>
        </div>
        <label class="btn-hot cursor-pointer">
            <input type="file" wire:model="uploads" multiple accept="image/jpeg,image/png,image/webp" class="hidden">
            <span wire:loading.remove wire:target="uploads">⬆ Загрузить</span>
            <span wire:loading wire:target="uploads">Загружаем…</span>
        </label>
    </div>
    @error('uploads') <p class="error">{{ $message }}</p> @enderror
    @error('uploads.*') <p class="error">{{ $message }}</p> @enderror

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        @forelse ($images as $img)
            <div wire:key="img-{{ $img->id }}" class="group overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-black/5">
                <div class="relative aspect-square bg-brand-50">
                    <img src="{{ $img->url() }}" class="h-full w-full object-cover" alt="" loading="lazy">
                    <button wire:click="delete({{ $img->id }})" wire:confirm="Удалить картинку?" class="absolute right-2 top-2 grid h-8 w-8 place-items-center rounded-full bg-white/90 text-sm opacity-0 shadow transition group-hover:opacity-100">🗑</button>
                </div>
                <div class="flex items-center justify-between gap-2 p-3 text-xs">
                    <span class="font-semibold text-ink/60">{{ $img->width }}×{{ $img->height }}</span>
                    <span class="{{ $img->isSuitable() ? 'badge-success' : 'badge-warning' }}">{{ $img->isSuitable() ? 'Подходит' : 'Маленькая' }}</span>
                </div>
                <div class="px-3 pb-3 text-xs text-ink/45">{{ \App\Models\Image::SOURCES[$img->source] ?? '' }}</div>
            </div>
        @empty
            <div class="card col-span-full py-14 text-center">
                <div class="text-5xl">🖼️</div>
                <p class="mt-3 font-bold">Картинок пока нет</p>
                <p class="text-ink/60">Загрузите свои или сгенерируйте ИИ при создании кампании.</p>
            </div>
        @endforelse
    </div>
    {{ $images->links() }}
</div>
