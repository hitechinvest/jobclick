<!doctype html>
<html lang="ru">
<head>
    <x-head :title="$title ?? null" />
</head>
<body class="min-h-screen font-sans">
    <div class="relative min-h-screen overflow-hidden bg-gradient-to-br from-violet-700 via-fuchsia-600 to-orange-500">
        <div class="pointer-events-none absolute -left-24 -top-24 h-96 w-96 rounded-full bg-yellow-300/30 blur-3xl animate-float-slow"></div>
        <div class="pointer-events-none absolute -bottom-32 -right-24 h-[28rem] w-[28rem] rounded-full bg-cyan-300/30 blur-3xl animate-float"></div>
        <div class="relative mx-auto flex min-h-screen max-w-6xl flex-col px-4 py-8 sm:px-6">
            <x-logo light />
            <div class="flex flex-1 items-center justify-center py-10">
                {{ $slot }}
            </div>
        </div>
    </div>
    @livewireScripts
</body>
</html>
