@props(['title' => null, 'light' => false])
<!doctype html>
<html lang="ru">
<head>
    <x-head :title="$title" />
</head>
<body class="min-h-screen font-sans">
    <x-site-header :light="$light" />
    <main>{{ $slot }}</main>
    <x-site-footer />
    @livewireScripts
</body>
</html>
