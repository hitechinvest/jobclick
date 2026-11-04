<x-mail::message>
# Ваш код для входа

<x-mail::panel>
<div style="font-size:32px;font-weight:800;letter-spacing:8px;text-align:center">{{ $code }}</div>
</x-mail::panel>

Код действует 10 минут. Или просто нажмите кнопку:

<x-mail::button :url="$link" color="success">
Войти в JobClick
</x-mail::button>

Если вы не запрашивали вход, просто проигнорируйте это письмо.
</x-mail::message>
