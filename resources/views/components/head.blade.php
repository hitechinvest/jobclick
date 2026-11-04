@props(['title' => null, 'description' => null])
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ? $title.' — JobClick' : 'JobClick — реклама вакансий в Яндексе и VK за 5 минут' }}</title>
<meta name="description" content="{{ $description ?? 'Запустите рекламу вакансии в Яндекс Директе, РСЯ, ВКонтакте и Одноклассниках за 5 минут. ИИ напишет объявление и сделает картинку. Оплата только за клики — от 7 ₽.' }}">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎯</text></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Unbounded:wght@500;700;800;900&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
