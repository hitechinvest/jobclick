@props(['text'])
<span x-data="{ open: false }" class="relative inline-flex align-middle" @mouseenter="open = true" @mouseleave="open = false" @click.stop.prevent="open = !open" @click.outside="open = false">
    <span class="tip" aria-label="Подсказка">?</span>
    <span x-cloak x-show="open" x-transition.opacity class="absolute bottom-full left-1/2 z-50 mb-2 w-72 -translate-x-1/2 rounded-2xl bg-ink px-4 py-3 text-left text-sm font-medium leading-snug text-white shadow-2xl">
        {{ $text }}
    </span>
</span>
