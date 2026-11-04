@props(['status'])
@php
    $status = $status instanceof \App\Enums\CampaignStatus ? $status : \App\Enums\CampaignStatus::from($status);
    $dot = ['gray' => 'bg-slate-400', 'warning' => 'bg-amber-500', 'info' => 'bg-sky-500', 'success' => 'bg-emerald-500 animate-pulse', 'danger' => 'bg-rose-500'][$status->color()];
@endphp
<span {{ $attributes->merge(['class' => 'badge-'.$status->color()]) }}><span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>{{ $status->label() }}</span>
