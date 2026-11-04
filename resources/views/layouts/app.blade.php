<!doctype html>
<html lang="ru">
<head>
    <x-head :title="$title ?? null" />
</head>
<body class="min-h-screen font-sans">
    {{ $slot }}
    @livewireScripts
</body>
</html>
