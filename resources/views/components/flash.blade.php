@foreach (['success' => 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'error' => 'bg-rose-50 text-rose-700 ring-rose-200', 'info' => 'bg-sky-50 text-sky-800 ring-sky-200'] as $type => $classes)
    @if (session($type))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 7000)" class="mb-6 flex items-start justify-between gap-4 rounded-2xl px-5 py-4 font-semibold ring-1 {{ $classes }}">
            <span>{{ session($type) }}</span>
            <button type="button" @click="show = false" class="opacity-50 hover:opacity-100">✕</button>
        </div>
    @endif
@endforeach
