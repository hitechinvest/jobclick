@props(['light' => false])
<a href="{{ route('home') }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <span class="grid h-10 w-10 place-items-center rounded-2xl bg-gradient-to-br from-orange-400 via-pink-500 to-violet-600 text-xl shadow-lg shadow-pink-500/30">🎯</span>
    <span class="font-display text-xl font-black tracking-tight {{ $light ? 'text-white' : 'text-ink' }}">Job<span class="{{ $light ? 'text-yellow-300' : 'gradient-text' }}">Click</span></span>
</a>
