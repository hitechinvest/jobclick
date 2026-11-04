<x-mail::message>
# {{ $alert->typeLabel() }}

{{ $alert->message }}

@if($alert->url)
<x-mail::button :url="$alert->url">
Открыть в админке
</x-mail::button>
@endif
</x-mail::message>
